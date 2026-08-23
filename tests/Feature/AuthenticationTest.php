<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\FortifyServiceProvider;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_la_pantalla_de_login_usa_la_plantilla_velzon(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertViewIs('backend.auth.login')
            ->assertSee('Iniciar sesión');
    }

    public function test_un_usuario_activo_puede_iniciar_sesion(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_no_se_puede_iniciar_sesion_con_password_incorrecta(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_se_puede_iniciar_sesion_con_el_nombre_de_usuario(): void
    {
        // Las cuentas de los trabajadores se entregan con un usuario tipo
        // "jperezlopez"; el campo del login acepta usuario o correo.
        $user = User::factory()->create(['name' => 'jperezlopez', 'password' => 'password']);

        $this->post('/login', [
            'email' => 'jperezlopez',
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_una_cuenta_bloqueada_no_puede_iniciar_sesion(): void
    {
        $user = User::factory()->create(['password' => 'password', 'is_active' => false]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors(['email' => FortifyServiceProvider::MENSAJE_CUENTA_BLOQUEADA]);

        $this->assertGuest();
    }

    public function test_una_cuenta_bloqueada_con_password_mala_no_revela_que_existe(): void
    {
        // El bloqueo se comprueba DESPUÉS de la contraseña: si no, cualquiera
        // podría averiguar qué cuentas existen y cuáles están dadas de baja.
        $user = User::factory()->create(['password' => 'password', 'is_active' => false]);

        $respuesta = $this->post('/login', [
            'email' => $user->email,
            'password' => 'incorrecta',
        ])->assertSessionHasErrors('email');

        $this->assertNotSame(
            FortifyServiceProvider::MENSAJE_CUENTA_BLOQUEADA,
            session('errors')->first('email')
        );
    }

    public function test_el_login_registra_el_ultimo_acceso(): void
    {
        $user = User::factory()->create(['password' => 'password', 'last_login_at' => null]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_un_usuario_desactivado_es_expulsado_del_panel(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_el_dashboard_exige_autenticacion(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_el_registro_publico_esta_deshabilitado(): void
    {
        // Las cuentas las crea el administrador, no hay alta libre.
        $this->get('/register')->assertNotFound();
    }
}
