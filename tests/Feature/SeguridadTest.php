<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lo que protege al panel cuando ya está en la tienda.
 *
 * Son comprobaciones aburridas a propósito: una cabecera que desaparece en un
 * refactor no rompe ninguna pantalla, así que nadie se entera hasta que hace
 * falta.
 */
class SeguridadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_las_respuestas_llevan_las_cabeceras_de_seguridad(): void
    {
        $respuesta = $this->get('/login');

        $respuesta->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $respuesta->assertHeader('X-Content-Type-Options', 'nosniff');
        $respuesta->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $respuesta->assertHeaderMissing('X-Powered-By');
    }

    /** También en la API, que es la que consume la app del teléfono. */
    public function test_la_api_tambien_las_lleva(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * HSTS solo se envía sobre HTTPS y en producción. Mandarlo en desarrollo
     * dejaría el navegador convencido de que http://localhost debe ser seguro,
     * y el proyecto no volvería a abrir hasta limpiar la caché HSTS.
     */
    public function test_no_se_manda_hsts_en_desarrollo(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    /**
     * El panel entero está detrás del login: una ruta que se olvide del
     * middleware quedaría abierta a cualquiera que adivine su dirección.
     */
    public function test_el_panel_no_se_abre_sin_iniciar_sesion(): void
    {
        foreach (['/dashboard', '/personas', '/productos', '/compras', '/ventas', '/reportes', '/usuarios'] as $ruta) {
            $this->get($ruta)->assertRedirect('/login');
        }
    }

    /**
     * La cuenta desactivada no sigue navegando con la sesión que ya tenía: el
     * middleware `active` la corta en la siguiente petición. Es lo que hace
     * que dar de baja a un trabajador surta efecto en el acto.
     */
    public function test_la_cuenta_desactivada_pierde_la_sesion_abierta(): void
    {
        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->syncRoles('admin');

        $this->actingAs($usuario)->get('/dashboard')->assertOk();

        $usuario->update(['is_active' => false]);

        $this->actingAs($usuario)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    /**
     * El .env.example no puede llevar valores reales: es el archivo que sí se
     * versiona, y una APP_KEY o una contraseña ahí es una filtración.
     */
    public function test_el_env_de_ejemplo_no_lleva_secretos(): void
    {
        $ejemplo = file_get_contents(base_path('.env.example'));

        foreach (['APP_KEY', 'DB_PASSWORD', 'REVERB_APP_SECRET', 'BACKUP_ARCHIVE_PASSWORD'] as $clave) {
            $this->assertMatchesRegularExpression(
                '/^'.$clave.'=\s*$/m',
                $ejemplo,
                "{$clave} tiene un valor en .env.example."
            );
        }
    }
}
