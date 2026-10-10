<?php

namespace Tests\Feature;

use App\Livewire\Inventario\ListaAmarilla as ListaAmarillaPanel;
use App\Livewire\Sistema\SesionApp;
use App\Livewire\Sistema\TipoDeCambio as TipoDeCambioPanel;
use App\Models\AccesoBiometrico;
use App\Models\CotizacionDolar;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Ajustes;
use App\Support\ListaAmarilla;
use App\Support\TipoDeCambio;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cierre de sesión por inactividad (ajuste), entrar con huella desde un
 * teléfono registrado, el dólar del día y la lista amarilla.
 */
class SesionDolarYListaAmarillaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true, 'password' => 'secreta123'])->syncRoles('admin');
    }

    private function vendedor(): User
    {
        return User::factory()->create(['is_active' => true, 'password' => 'secreta123'])->syncRoles('vendedor');
    }

    private const DISPOSITIVO = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    // ---- Inactividad ---------------------------------------------------------

    public function test_el_login_y_el_perfil_traen_el_tiempo_de_inactividad(): void
    {
        $usuario = $this->vendedor();

        $this->postJson('/api/v1/auth/login', [
            'usuario' => $usuario->email,
            'password' => 'secreta123',
            'dispositivo' => 'App Android',
        ])->assertOk()->assertJsonPath('ajustes.inactividad_minutos', 10);

        Sanctum::actingAs($usuario);
        $this->getJson('/api/v1/auth/perfil')
            ->assertOk()
            ->assertJsonPath('data.id', $usuario->id)
            ->assertJsonPath('ajustes.inactividad_minutos', 10);
    }

    public function test_solo_el_admin_cambia_el_tiempo_y_solo_a_opciones_validas(): void
    {
        Sanctum::actingAs($this->vendedor());
        $this->postJson('/api/v1/auth/inactividad', ['minutos' => 30])->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/v1/auth/inactividad', ['minutos' => 7])->assertUnprocessable();
        $this->postJson('/api/v1/auth/inactividad', ['minutos' => 30])
            ->assertOk()
            ->assertJsonPath('ajustes.inactividad_minutos', 30);

        $this->assertSame(30, app(Ajustes::class)->inactividadMinutos());
    }

    public function test_el_panel_cambia_el_tiempo_y_quita_telefonos(): void
    {
        $admin = $this->admin();
        $vendedor = $this->vendedor();
        $acceso = AccesoBiometrico::query()->create([
            'user_id' => $vendedor->id,
            'dispositivo_id' => self::DISPOSITIVO,
            'nombre' => 'Galaxy A15',
            'llave_hash' => AccesoBiometrico::hashDe('x'),
        ]);

        $this->actingAs($admin)->get(route('usuarios.index'))->assertOk()->assertSee('Sesión en la app del teléfono');

        Livewire::actingAs($admin)->test(SesionApp::class)
            ->assertSee('Galaxy A15')
            ->set('minutos', 15)
            ->call('quitar', $acceso->id);

        $this->assertSame(15, app(Ajustes::class)->inactividadMinutos());
        $this->assertModelMissing($acceso);
    }

    // ---- Huella ----------------------------------------------------------------

    public function test_un_telefono_registrado_entra_sin_contrasena(): void
    {
        $usuario = $this->vendedor();
        Sanctum::actingAs($usuario);

        $llave = $this->postJson('/api/v1/auth/huella', [
            'dispositivo_id' => self::DISPOSITIVO,
            'nombre' => 'Galaxy A15',
        ])->assertCreated()->json('llave');

        $this->assertSame(64, strlen($llave));
        // La llave no queda guardada tal cual.
        $this->assertDatabaseMissing('accesos_biometricos', ['llave_hash' => $llave]);

        app('auth')->forgetGuards();

        $this->postJson('/api/v1/auth/huella/entrar', [
            'dispositivo_id' => self::DISPOSITIVO,
            'llave' => $llave,
            'dispositivo' => 'App Android',
        ])->assertOk()
            ->assertJsonPath('usuario.id', $usuario->id)
            ->assertJsonStructure(['token', 'ajustes' => ['inactividad_minutos']]);

        $this->postJson('/api/v1/auth/huella/entrar', [
            'dispositivo_id' => self::DISPOSITIVO,
            'llave' => str_repeat('x', 64),
            'dispositivo' => 'App Android',
        ])->assertUnprocessable()->assertJsonValidationErrors('llave');
    }

    public function test_cambiar_la_contrasena_o_bloquear_borra_los_telefonos(): void
    {
        $usuario = $this->vendedor();
        $crear = fn (string $id) => AccesoBiometrico::query()->create([
            'user_id' => $usuario->id,
            'dispositivo_id' => $id,
            'llave_hash' => AccesoBiometrico::hashDe('llave'),
        ]);

        $crear(self::DISPOSITIVO);
        $usuario->update(['password' => 'otra-clave-123']);
        $this->assertSame(0, $usuario->accesosBiometricos()->count());

        $crear(self::DISPOSITIVO);
        $usuario->update(['is_active' => false]);
        $this->assertSame(0, $usuario->accesosBiometricos()->count());
    }

    public function test_un_telefono_es_de_una_sola_persona_y_se_puede_quitar(): void
    {
        $ana = $this->vendedor();
        $luis = $this->vendedor();

        Sanctum::actingAs($ana);
        $this->postJson('/api/v1/auth/huella', ['dispositivo_id' => self::DISPOSITIVO])->assertCreated();

        Sanctum::actingAs($luis);
        $this->postJson('/api/v1/auth/huella', ['dispositivo_id' => self::DISPOSITIVO])->assertCreated();

        $this->assertSame(1, AccesoBiometrico::query()->count());
        $this->assertSame($luis->id, AccesoBiometrico::query()->value('user_id'));

        $this->deleteJson('/api/v1/auth/huella', ['dispositivo_id' => self::DISPOSITIVO])->assertOk();
        $this->assertSame(0, AccesoBiometrico::query()->count());
    }

    // ---- Dólar -------------------------------------------------------------------

    /** @return list<array<string, mixed>> */
    private function cuerpo(float $paraleloCompra = 11.86, float $paraleloVenta = 11.89, float $oficial = 11.85): array
    {
        return [
            ['moneda' => 'USD', 'casa' => 'oficial', 'nombre' => 'Oficial', 'compra' => $oficial, 'venta' => $oficial, 'fechaActualizacion' => '2026-10-09T00:00:00.000Z'],
            ['moneda' => 'USD', 'casa' => 'binance', 'nombre' => 'Binance', 'compra' => $paraleloCompra, 'venta' => $paraleloVenta, 'fechaActualizacion' => '2026-10-09T21:02:39.888Z'],
        ];
    }

    private function fuente(float $paraleloCompra = 11.86, float $paraleloVenta = 11.89, float $oficial = 11.85): void
    {
        Http::fake(['bo.dolarapi.com/*' => Http::response($this->cuerpo($paraleloCompra, $paraleloVenta, $oficial))]);
    }

    public function test_el_dolar_se_lee_y_se_sirve_a_la_app(): void
    {
        $this->fuente();

        Sanctum::actingAs($this->vendedor());
        $this->getJson('/api/v1/tipo-de-cambio')
            ->assertOk()
            ->assertJsonPath('data.paralelo.compra', 11.86)
            ->assertJsonPath('data.paralelo.venta', 11.89)
            ->assertJsonPath('data.oficial.venta', 11.85)
            ->assertJsonPath('data.paralelo.desactualizado', false);

        $this->assertSame(2, CotizacionDolar::query()->count());
    }

    public function test_el_mismo_valor_no_duplica_filas_y_un_cambio_si(): void
    {
        $servicio = app(TipoDeCambio::class);

        Http::fake(['bo.dolarapi.com/*' => Http::sequence()
            ->push($this->cuerpo())
            ->push($this->cuerpo())
            ->push($this->cuerpo(paraleloVenta: 11.95))]);

        $servicio->actualizar();
        $servicio->actualizar();
        $this->assertSame(2, CotizacionDolar::query()->count());

        $servicio->actualizar();
        $this->assertSame(3, CotizacionDolar::query()->count());
        $this->assertSame(11.95, $servicio->actual(refrescar: false)['paralelo']['venta']);
    }

    public function test_si_la_fuente_cae_se_mantiene_el_ultimo_valor(): void
    {
        Http::fake(['bo.dolarapi.com/*' => Http::sequence()
            ->push($this->cuerpo())
            ->push('caído', 503)]);

        $servicio = app(TipoDeCambio::class);
        $servicio->actualizar();

        $this->travel(8)->hours();

        $this->assertFalse($servicio->actualizar());
        $actual = $servicio->actual(refrescar: false);
        $this->assertSame(11.89, $actual['paralelo']['venta']);
        $this->assertTrue($actual['paralelo']['desactualizado']);
    }

    public function test_un_valor_absurdo_de_la_fuente_no_se_guarda(): void
    {
        $this->fuente(paraleloCompra: 0, paraleloVenta: 0);

        app(TipoDeCambio::class)->actualizar();

        $this->assertSame(0, CotizacionDolar::query()->where('tipo', 'paralelo')->count());
        $this->assertSame(1, CotizacionDolar::query()->where('tipo', 'oficial')->count());
    }

    public function test_el_dolar_se_ve_en_la_barra_del_panel(): void
    {
        $this->fuente();

        Livewire::withoutLazyLoading()->actingAs($this->vendedor())->test(TipoDeCambioPanel::class)
            ->assertSee('Paralelo')
            ->assertSee('11,89')
            ->assertSee('Oficial BCB')
            ->assertSee('11,85');
    }

    public function test_sin_datos_del_dolar_la_barra_no_muestra_nada(): void
    {
        Http::fake(['bo.dolarapi.com/*' => Http::response('caído', 503)]);

        Livewire::withoutLazyLoading()->actingAs($this->vendedor())->test(TipoDeCambioPanel::class)
            ->assertDontSee('Paralelo')
            ->assertDontSee('Oficial BCB');
    }

    // ---- Lista amarilla -------------------------------------------------------

    private function aparato(Producto $producto, int $diasEnTienda, string $estado = 'en_stock'): Unidad
    {
        return Unidad::factory()->create([
            'producto_id' => $producto->id,
            'estado' => $estado,
            'costo_unitario' => 500,
            'ingresado_en' => now()->subDays($diasEnTienda),
        ]);
    }

    public function test_la_lista_cuenta_los_dias_a_hoy_y_solo_lo_que_sigue_en_tienda(): void
    {
        $licuadora = Producto::factory()->create(['nombre' => 'Licuadora LK50', 'precio_venta' => 1100]);
        $tv = Producto::factory()->create(['nombre' => 'Smart TV 50', 'precio_venta' => 4000]);

        $this->aparato($licuadora, 200);
        $this->aparato($licuadora, 400);
        $this->aparato($licuadora, 20);            // nuevo: no entra
        $this->aparato($tv, 300, 'vendido');        // ya se vendió: no entra
        $this->aparato($tv, 190, 'reservado');      // en un carrito: sigue en la tienda

        $r = app(ListaAmarilla::class)->consultar(180, conCostos: true);

        $this->assertSame(3, $r['resumen']['unidades']);
        $this->assertSame(2, $r['resumen']['productos']);

        $primero = $r['productos'][0];
        $this->assertSame('Licuadora LK50', $primero['producto']);
        $this->assertSame(2, $primero['unidades']);
        $this->assertSame(400, $primero['dias_max']);
        $this->assertSame('critico', $primero['nivel']);       // 400 >= 2 × 180
        $this->assertSame(1000.0, $primero['capital']);

        $this->assertSame('atencion', $r['productos'][1]['nivel']);

        // Con un año de umbral solo queda el de 400 días.
        $this->assertSame(1, app(ListaAmarilla::class)->consultar(365)['resumen']['unidades']);
    }

    public function test_la_api_no_manda_costos_a_quien_no_los_ve(): void
    {
        $this->aparato(Producto::factory()->create(), 250);

        Sanctum::actingAs($this->vendedor());
        $this->getJson('/api/v1/inventario/lista-amarilla')
            ->assertOk()
            ->assertJsonPath('meta.umbral', 180)
            ->assertJsonPath('meta.resumen.unidades', 1)
            ->assertJsonPath('meta.resumen.capital', null)
            ->assertJsonPath('data.0.capital', null)
            ->assertJsonPath('data.0.aparatos.0.costo', null)
            ->assertJsonPath('meta.puede_configurar', false);

        $this->postJson('/api/v1/inventario/lista-amarilla/umbral', ['dias' => 90])->assertForbidden();

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/v1/inventario/lista-amarilla/umbral', ['dias' => 90])->assertOk();
        $this->assertSame(90, app(Ajustes::class)->listaAmarillaDias());
    }

    public function test_la_lista_amarilla_del_panel(): void
    {
        $this->aparato(Producto::factory()->create(['nombre' => 'Microondas Viejo']), 210);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('inventario.lista-amarilla'))->assertOk()->assertSee('Lista amarilla');

        Livewire::actingAs($admin)->test(ListaAmarillaPanel::class)
            ->assertSee('Microondas Viejo')
            ->set('dias', 365)
            ->assertDontSee('Microondas Viejo')
            ->set('umbralNuevo', 120)
            ->call('guardarUmbral')
            ->assertSet('dias', 120)
            ->assertSee('Microondas Viejo');

        $this->assertSame(120, app(Ajustes::class)->listaAmarillaDias());
    }
}
