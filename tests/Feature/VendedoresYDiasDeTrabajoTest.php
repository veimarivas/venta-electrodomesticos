<?php

namespace Tests\Feature;

use App\Livewire\Personal\ReiniciarAcceso;
use App\Livewire\Personal\Vendedores;
use App\Models\Asistencia;
use App\Models\DiaDeTrabajo;
use App\Models\Persona;
use App\Models\Tienda;
use App\Models\Trabajador;
use App\Models\User;
use App\Support\DiasDeTrabajo;
use App\Support\RegistroDeAsistencia;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vendedores (2026-10-24): crear la cuenta, reiniciar el acceso con
 * contraseña nueva obligatoria y los días de trabajo del mes.
 */
class VendedoresYDiasDeTrabajoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('admin');
    }

    private function trabajador(string $carnet = '7654321'): Trabajador
    {
        $persona = Persona::factory()->create([
            'nombres' => 'Ana María',
            'apellido_paterno' => 'Rojas',
            'apellido_materno' => 'Paz',
            'carnet' => $carnet,
        ]);

        return Trabajador::factory()->create(['persona_id' => $persona->id, 'fecha_baja' => null]);
    }

    // ---- Cuentas ----------------------------------------------------------

    public function test_crear_la_cuenta_desde_vendedores_pide_cambiarla_al_entrar(): void
    {
        $trabajador = $this->trabajador();
        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/vendedores/{$trabajador->id}/cuenta")
            ->assertCreated()
            ->assertJsonPath('acceso.usuario', 'arojaspaz')
            ->assertJsonPath('acceso.contrasena_temporal', '7654321');

        $cuenta = $trabajador->persona->fresh()->user;
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertTrue($cuenta->hasRole('vendedor'));

        // La lista ya la trae con su cuenta.
        $this->getJson('/api/v1/vendedores')
            ->assertOk()
            ->assertJsonPath('data.0.cuenta.usuario', 'arojaspaz');
    }

    public function test_con_la_contrasena_temporal_solo_se_puede_cambiarla(): void
    {
        $trabajador = $this->trabajador();
        Sanctum::actingAs($this->admin());
        $this->postJson("/api/v1/vendedores/{$trabajador->id}/cuenta")->assertCreated();

        $login = $this->postJson('/api/v1/auth/login', [
            'usuario' => 'arojaspaz', 'password' => '7654321', 'dispositivo' => 'prueba',
        ])->assertOk()->assertJsonPath('usuario.debe_cambiar_password', true);

        $token = $login->json('token');
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/ventas')
            ->assertForbidden()
            ->assertJsonPath('debe_cambiar_password', true);

        // Repetir el carnet no vale.
        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'password_actual' => '7654321',
            'password_nuevo' => '7654321',
            'password_nuevo_confirmation' => '7654321',
        ])->assertStatus(422);

        $this->withToken($token)->putJson('/api/v1/auth/password', [
            'password_actual' => '7654321',
            'password_nuevo' => 'Nueva2026x',
            'password_nuevo_confirmation' => 'Nueva2026x',
        ])->assertOk()->assertJsonPath('usuario.debe_cambiar_password', false);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/auth/perfil')->assertOk();
        $this->assertFalse($trabajador->persona->fresh()->user->debe_cambiar_password);
    }

    public function test_reiniciar_vuelve_al_carnet_y_cierra_las_sesiones(): void
    {
        $trabajador = $this->trabajador();
        $cuenta = User::factory()->create([
            'persona_id' => $trabajador->persona_id,
            'name' => 'olvidadizo',
            'password' => Hash::make('OtraCosa99'),
            'debe_cambiar_password' => false,
        ])->syncRoles('vendedor');
        $cuenta->createToken('telefono');

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/usuarios/{$cuenta->id}/reiniciar")
            ->assertOk()
            ->assertJsonPath('acceso.usuario', 'arojaspaz')
            ->assertJsonPath('acceso.contrasena_temporal', '7654321');

        $cuenta->refresh();
        $this->assertTrue(Hash::check('7654321', $cuenta->password));
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertSame(0, $cuenta->tokens()->count());
    }

    public function test_nadie_reinicia_su_propio_acceso_ni_sin_permiso(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/usuarios/{$admin->id}/reiniciar")->assertStatus(422);

        $trabajador = $this->trabajador();
        $cuenta = User::factory()->create(['persona_id' => $trabajador->persona_id])->syncRoles('vendedor');

        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('vendedor'));
        $this->postJson("/api/v1/usuarios/{$cuenta->id}/reiniciar")->assertForbidden();
    }

    public function test_el_panel_reinicia_desde_el_modal_y_crea_la_cuenta(): void
    {
        $trabajador = $this->trabajador();
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Vendedores::class)
            ->call('confirmarCrearCuenta', $trabajador->id)
            ->assertSet('cuentaUsuario', 'arojaspaz')
            ->call('crearCuenta')
            ->assertSet('cuentaCreada', true)
            ->assertSet('cuentaTemporal', '7654321');

        $cuenta = $trabajador->persona->fresh()->user;
        $cuenta->update(['password' => 'Propia2026x', 'debe_cambiar_password' => false]);

        Livewire::actingAs($admin)->test(ReiniciarAcceso::class)
            ->call('abrir', $cuenta->id)
            ->assertDispatched('abrir-modal-reiniciar-acceso')
            ->call('confirmar')
            ->assertSet('hecho', true)
            ->assertDispatched('acceso-reiniciado');

        $this->assertTrue($cuenta->fresh()->debe_cambiar_password);
    }

    public function test_el_panel_manda_a_cambiar_la_contrasena_temporal(): void
    {
        $vendedor = User::factory()->create(['is_active' => true, 'debe_cambiar_password' => true])->syncRoles('vendedor')->fresh();

        $this->actingAs($vendedor)->get('/dashboard')->assertRedirect(route('profile.edit'));
        $this->actingAs($vendedor)->get('/perfil')->assertOk()->assertSee('Elige tu propia contraseña');
    }

    // ---- Días de trabajo --------------------------------------------------

    public function test_se_guarda_el_mes_entero_y_lo_desmarcado_se_borra(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');
        Sanctum::actingAs($this->admin());

        $this->putJson('/api/v1/dias-de-trabajo', [
            'user_id' => $vendedor->id, 'mes' => '2026-11', 'fechas' => ['2026-11-02', '2026-11-03', '2026-11-04'],
        ])->assertOk()->assertJsonCount(3, 'data.fechas');

        $this->putJson('/api/v1/dias-de-trabajo', [
            'user_id' => $vendedor->id, 'mes' => '2026-11', 'fechas' => ['2026-11-03', '2026-11-05'],
        ])->assertOk()->assertJsonPath('data.fechas', ['2026-11-03', '2026-11-05']);

        $this->assertSame(2, DiaDeTrabajo::query()->count());

        // Una fecha de otro mes no se cuela.
        $this->putJson('/api/v1/dias-de-trabajo', [
            'user_id' => $vendedor->id, 'mes' => '2026-11', 'fechas' => ['2026-12-01'],
        ])->assertStatus(422);
    }

    public function test_el_vendedor_ve_sus_dias_pero_no_los_programa_ni_ve_los_ajenos(): void
    {
        $vendedor = User::factory()->create(['is_active' => true])->syncRoles('vendedor');
        $otro = User::factory()->create()->syncRoles('vendedor');
        app(DiasDeTrabajo::class)->guardar($vendedor, 2026, 11, ['2026-11-02']);

        Sanctum::actingAs($vendedor);

        $this->getJson('/api/v1/dias-de-trabajo?mes=2026-11')->assertOk()->assertJsonPath('data.fechas', ['2026-11-02']);
        $this->getJson("/api/v1/dias-de-trabajo?mes=2026-11&user_id={$otro->id}")->assertForbidden();
        $this->putJson('/api/v1/dias-de-trabajo', [
            'user_id' => $vendedor->id, 'mes' => '2026-11', 'fechas' => [],
        ])->assertForbidden();
    }

    public function test_como_el_mes_anterior_repite_los_dias_de_la_semana(): void
    {
        $vendedor = User::factory()->create();
        $servicio = app(DiasDeTrabajo::class);

        // Octubre 2026: lunes 5 y miércoles 7.
        $servicio->guardar($vendedor, 2026, 10, ['2026-10-05', '2026-10-07']);

        $noviembre = $servicio->comoElMesAnterior($vendedor->id, 2026, 11);

        // Noviembre 2026: lunes 2, 9, 16, 23, 30 y miércoles 4, 11, 18, 25.
        $this->assertCount(9, $noviembre);
        $this->assertContains('2026-11-02', $noviembre);
        $this->assertContains('2026-11-04', $noviembre);
        $this->assertNotContains('2026-11-03', $noviembre);
    }

    public function test_el_historial_cuenta_las_faltas_de_los_dias_programados(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));

        $tienda = Tienda::query()->create(['nombre' => 'Centro', 'latitud' => -17.78, 'longitud' => -63.18]);
        $vino = User::factory()->create(['name' => 'ana'])->syncRoles('vendedor');
        $nunca = User::factory()->create(['name' => 'beto'])->syncRoles('vendedor');

        $servicio = app(DiasDeTrabajo::class);
        $servicio->guardar($vino, 2026, 11, ['2026-11-02', '2026-11-03', '2026-11-25']);
        $servicio->guardar($nunca, 2026, 11, ['2026-11-02']);

        Asistencia::query()->create([
            'user_id' => $vino->id,
            'tienda_id' => $tienda->id,
            'fecha' => '2026-11-02',
            'entrada_en' => '2026-11-02 08:00:00',
            'salida_en' => '2026-11-02 16:00:00',
            'entrada_latitud' => -17.78,
            'entrada_longitud' => -63.18,
            'entrada_distancia' => 5,
        ]);

        $filas = collect(app(RegistroDeAsistencia::class)->historial(2026, 11))->keyBy('trabajador');

        $this->assertSame(3, $filas['ana']['programados']);
        // El 3 faltó; el 25 todavía no llega.
        $this->assertSame(1, $filas['ana']['faltas']);
        $this->assertSame(['2026-11-03'], $filas['ana']['fechas_falta']);

        // Quien no vino nunca también sale, con su falta.
        $this->assertSame(0, $filas['beto']['dias']);
        $this->assertSame(1, $filas['beto']['faltas']);
    }
}
