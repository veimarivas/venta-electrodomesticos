<?php

namespace Tests\Feature;

use App\Livewire\Asistencia\Index as AsistenciaPanel;
use App\Livewire\Tiendas\Index as TiendasPanel;
use App\Models\Asistencia;
use App\Models\Tienda;
use App\Models\User;
use App\Support\RegistroDeAsistencia;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Asistencia por ubicación: marcar dentro del radio de una tienda, atrasos,
 * historial del mes, corrección de salidas y tiendas.
 */
class AsistenciaTest extends TestCase
{
    use RefreshDatabase;

    // Tienda Centro y Tienda Norte, a unos 3 km una de otra.
    private const CENTRO = [-17.7833000, -63.1821000];

    private const NORTE = [-17.7560000, -63.1800000];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function vendedor(): User
    {
        return User::factory()->create(['is_active' => true, 'name' => 'ana'])->syncRoles('vendedor');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('admin');
    }

    private function tienda(string $nombre, array $punto, array $extra = []): Tienda
    {
        return Tienda::query()->create(array_merge([
            'nombre' => $nombre,
            'latitud' => $punto[0],
            'longitud' => $punto[1],
            'radio_metros' => 30,
            'tolerancia_minutos' => 10,
        ], $extra));
    }

    /** Un punto a unos `metros` al norte del dado. */
    private function aMetros(array $punto, float $metros): array
    {
        return ['latitud' => $punto[0] + $metros / 111320, 'longitud' => $punto[1], 'precision' => 8];
    }

    public function test_la_distancia_se_mide_en_metros(): void
    {
        $d = RegistroDeAsistencia::distancia(...[...self::CENTRO, ...[self::CENTRO[0] + 100 / 111320, self::CENTRO[1]]]);

        $this->assertEqualsWithDelta(100, $d, 1);
    }

    public function test_marca_entrada_dentro_del_radio_y_no_fuera(): void
    {
        $this->tienda('Centro', self::CENTRO);
        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/asistencia/entrada', $this->aMetros(self::CENTRO, 300))
            ->assertUnprocessable()
            ->assertJsonPath('errors.ubicacion.0', fn (string $m) => str_contains($m, 'Estás a 300 m de Centro'));

        $this->postJson('/api/v1/asistencia/entrada', $this->aMetros(self::CENTRO, 12))
            ->assertCreated()
            ->assertJsonPath('asistencia.tienda', 'Centro')
            ->assertJsonPath('asistencia.entrada_distancia', 12);

        // Ya tiene un turno abierto.
        $this->postJson('/api/v1/asistencia/entrada', $this->aMetros(self::CENTRO, 5))->assertUnprocessable();
    }

    public function test_puede_entrar_en_cualquier_tienda(): void
    {
        $this->tienda('Centro', self::CENTRO);
        $this->tienda('Norte', self::NORTE);
        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/asistencia/entrada', $this->aMetros(self::NORTE, 10))
            ->assertCreated()
            ->assertJsonPath('asistencia.tienda', 'Norte');
    }

    public function test_gps_falso_o_senal_debil_no_marcan(): void
    {
        $this->tienda('Centro', self::CENTRO);
        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/asistencia/entrada', [...$this->aMetros(self::CENTRO, 3), 'simulada' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ubicacion.0', fn (string $m) => str_contains($m, 'simulada'));

        $this->postJson('/api/v1/asistencia/entrada', [...$this->aMetros(self::CENTRO, 3), 'precision' => 85])
            ->assertUnprocessable()
            ->assertJsonPath('errors.ubicacion.0', fn (string $m) => str_contains($m, 'débil'));

        $this->assertSame(0, Asistencia::query()->count());
    }

    public function test_una_tienda_inactiva_o_sin_ubicacion_no_cuenta(): void
    {
        $this->tienda('Centro', self::CENTRO, ['activa' => false]);
        Tienda::query()->create(['nombre' => 'Sin mapa']);
        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/asistencia/entrada', $this->aMetros(self::CENTRO, 2))
            ->assertUnprocessable()
            ->assertJsonPath('errors.ubicacion.0', fn (string $m) => str_contains($m, 'no hay tiendas'));
    }

    public function test_la_salida_se_marca_en_la_misma_tienda(): void
    {
        $this->tienda('Centro', self::CENTRO);
        $this->tienda('Norte', self::NORTE);
        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/asistencia/salida', $this->aMetros(self::CENTRO, 3))
            ->assertUnprocessable()
            ->assertJsonPath('errors.ubicacion.0', 'No tienes una entrada abierta hoy.');

        $this->postJson('/api/v1/asistencia/entrada', $this->aMetros(self::CENTRO, 3))->assertCreated();
        $this->travel(4)->hours();

        // Dentro de otra tienda no vale: la salida es donde entró.
        $this->postJson('/api/v1/asistencia/salida', $this->aMetros(self::NORTE, 3))->assertUnprocessable();

        $this->postJson('/api/v1/asistencia/salida', $this->aMetros(self::CENTRO, 6))
            ->assertOk()
            ->assertJsonPath('asistencia.minutos', 240)
            ->assertJsonPath('asistencia.abierta', false);

        // Puede volver a entrar (después del almuerzo): segundo turno del día.
        $this->postJson('/api/v1/asistencia/entrada', $this->aMetros(self::CENTRO, 3))->assertCreated();
        $this->assertSame(2, Asistencia::query()->count());
    }

    public function test_el_atraso_cuenta_desde_la_hora_de_entrada_pasada_la_tolerancia(): void
    {
        $this->tienda('Centro', self::CENTRO, ['hora_entrada' => '08:00', 'tolerancia_minutos' => 10]);
        $ana = $this->vendedor();
        $luis = User::factory()->create(['is_active' => true])->syncRoles('vendedor');
        $registro = app(RegistroDeAsistencia::class);
        [$lat, $lng] = self::CENTRO;

        $this->travelTo(now()->setTime(8, 25));
        $tarde = $registro->marcarEntrada($ana, $lat, $lng, 5, false);
        $this->assertSame(25, $tarde->minutos_atraso);

        $this->travelTo(now()->setTime(8, 7));
        $aTiempo = $registro->marcarEntrada($luis, $lat, $lng, 5, false);
        $this->assertSame(0, $aTiempo->minutos_atraso);

        // La segunda entrada del día no cuenta como atraso.
        $this->travelTo(now()->setTime(13, 0));
        $registro->marcarSalida($ana, $lat, $lng, 5, false);
        $this->travelTo(now()->setTime(14, 30));
        $this->assertNull($registro->marcarEntrada($ana, $lat, $lng, 5, false)->minutos_atraso);
    }

    public function test_cada_uno_ve_su_historial_y_el_admin_el_de_todos(): void
    {
        $centro = $this->tienda('Centro', self::CENTRO);
        $ana = $this->vendedor();
        $luis = User::factory()->create(['is_active' => true, 'name' => 'luis'])->syncRoles('vendedor');

        foreach ([[$ana, 8], [$luis, 6]] as [$quien, $horas]) {
            Asistencia::query()->create([
                'user_id' => $quien->id, 'tienda_id' => $centro->id, 'fecha' => now()->startOfMonth()->toDateString(),
                'entrada_en' => now()->startOfMonth()->setTime(8, 0),
                'salida_en' => now()->startOfMonth()->setTime(8 + $horas, 0),
                'entrada_latitud' => self::CENTRO[0], 'entrada_longitud' => self::CENTRO[1], 'entrada_distancia' => 4,
                'minutos_atraso' => $quien->is($ana) ? 15 : 0,
            ]);
        }

        Sanctum::actingAs($ana);
        $this->getJson('/api/v1/asistencia/historial?user_id='.$luis->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.trabajador', 'ana')
            ->assertJsonPath('data.0.minutos', 480)
            ->assertJsonPath('data.0.atrasos', 1)
            ->assertJsonPath('meta.todos', false);

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/asistencia/historial')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.todos', true);
    }

    public function test_el_admin_pone_la_salida_olvidada_y_queda_anotado(): void
    {
        $centro = $this->tienda('Centro', self::CENTRO);
        $ana = $this->vendedor();
        $ayer = now()->subDay();
        $turno = Asistencia::query()->create([
            'user_id' => $ana->id, 'tienda_id' => $centro->id, 'fecha' => $ayer->toDateString(),
            'entrada_en' => $ayer->copy()->setTime(8, 0),
            'entrada_latitud' => self::CENTRO[0], 'entrada_longitud' => self::CENTRO[1], 'entrada_distancia' => 4,
        ]);

        $this->assertTrue($turno->sinSalida());

        Sanctum::actingAs($ana);
        $this->postJson("/api/v1/asistencia/{$turno->id}/corregir", ['salida' => '17:00', 'motivo' => 'olvidé'])
            ->assertForbidden();

        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/asistencia/{$turno->id}/corregir", ['salida' => '07:00', 'motivo' => 'Olvidó marcar'])
            ->assertUnprocessable();
        $this->postJson("/api/v1/asistencia/{$turno->id}/corregir", ['salida' => '17:30', 'motivo' => 'Olvidó marcar'])
            ->assertOk()
            ->assertJsonPath('asistencia.minutos', 570)
            ->assertJsonPath('asistencia.corregida', true);

        $this->assertSame($admin->id, $turno->fresh()->corregida_por);
    }

    public function test_solo_el_admin_fija_la_ubicacion_desde_el_telefono(): void
    {
        $tienda = Tienda::query()->create(['nombre' => 'Nueva']);

        Sanctum::actingAs($this->vendedor());
        $this->postJson("/api/v1/tiendas/{$tienda->id}/ubicacion", ['latitud' => -17.78, 'longitud' => -63.18])
            ->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->postJson("/api/v1/tiendas/{$tienda->id}/ubicacion", ['latitud' => -17.78, 'longitud' => -63.18, 'precision' => 150])
            ->assertUnprocessable();
        $this->postJson("/api/v1/tiendas/{$tienda->id}/ubicacion", ['latitud' => -17.7801234, 'longitud' => -63.1809876, 'precision' => 6])
            ->assertOk()
            ->assertJsonPath('tienda.latitud', -17.7801234);
    }

    public function test_el_estado_trae_las_tiendas_para_medir_en_el_telefono(): void
    {
        $this->tienda('Centro', self::CENTRO, ['hora_entrada' => '08:30']);
        Sanctum::actingAs($this->vendedor());

        $this->getJson('/api/v1/asistencia')
            ->assertOk()
            ->assertJsonPath('abierta', null)
            ->assertJsonPath('tiendas.0.nombre', 'Centro')
            ->assertJsonPath('tiendas.0.radio_metros', 30)
            ->assertJsonPath('tiendas.0.hora_entrada', '08:30')
            ->assertJsonPath('precision_maxima', 50)
            ->assertJsonPath('puede_ver_todos', false);
    }

    // ---- Panel ---------------------------------------------------------------

    public function test_el_panel_registra_una_tienda_pegando_coordenadas_de_google_maps(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('tiendas.index'))->assertOk()->assertSee('Tiendas');

        Livewire::actingAs($admin)->test(TiendasPanel::class)
            ->call('nueva')
            ->set('nombre', 'Tienda Centro')
            ->set('pegado', 'https://www.google.com/maps/@-17.7833123,-63.1821456,19z')
            ->assertSet('latitud', '-17.7833123')
            ->assertSet('longitud', '-63.1821456')
            ->set('horaEntrada', '08:30')
            ->call('guardar')
            ->assertHasNoErrors();

        $tienda = Tienda::query()->sole();
        $this->assertSame(30, $tienda->radio_metros);
        $this->assertSame('08:30', $tienda->horaEntradaCorta());
    }

    public function test_el_historial_del_panel_y_sus_descargas(): void
    {
        $centro = $this->tienda('Centro', self::CENTRO);
        $ana = $this->vendedor();
        $hoy = now()->startOfDay();
        Asistencia::query()->create([
            'user_id' => $ana->id, 'tienda_id' => $centro->id, 'fecha' => $hoy->toDateString(),
            'entrada_en' => $hoy->copy()->setTime(8, 0), 'salida_en' => $hoy->copy()->setTime(12, 0),
            'entrada_latitud' => self::CENTRO[0], 'entrada_longitud' => self::CENTRO[1], 'entrada_distancia' => 4,
        ]);
        $admin = $this->admin();

        $this->actingAs($this->vendedor())->get(route('asistencia.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('asistencia.index'))->assertOk()->assertSee('Asistencia del personal');

        Livewire::actingAs($admin)->test(AsistenciaPanel::class)
            ->assertSee('ana')
            ->assertSee('4 h 00 min');

        $csv = $this->actingAs($admin)->get(route('asistencia.exportar', ['formato' => 'csv', 'mes' => now()->format('Y-m')]));
        $csv->assertOk();
        $this->assertStringContainsString('ana;', $csv->streamedContent());
        $this->assertStringContainsString('Centro', $csv->streamedContent());

        $this->actingAs($admin)->get(route('asistencia.exportar', ['formato' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
