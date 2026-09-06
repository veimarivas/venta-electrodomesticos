<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Persona;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Perfil del usuario autenticado y edición de clientes desde la app.
 */
class PerfilYClientesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function vendedor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    private function supervisor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('supervisor');
    }

    // ---- Ver perfil --------------------------------------------------------

    public function test_puede_ver_su_propio_perfil(): void
    {
        $usuario = $this->vendedor();

        Sanctum::actingAs($usuario);

        $this->getJson('/api/v1/auth/perfil')
            ->assertOk()
            ->assertJsonPath('data.id', $usuario->id)
            ->assertJsonPath('data.nombre', $usuario->name)
            ->assertJsonPath('data.correo', $usuario->email);
    }

    // ---- Actualizar perfil -------------------------------------------------

    public function test_puede_actualizar_su_nombre(): void
    {
        $usuario = $this->vendedor();

        Sanctum::actingAs($usuario);

        $this->putJson('/api/v1/auth/perfil', [
            'name' => 'nuevo_nombre',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $usuario->id,
            'name' => 'nuevo_nombre',
        ]);
    }

    public function test_puede_actualizar_su_correo(): void
    {
        $usuario = $this->vendedor();

        Sanctum::actingAs($usuario);

        $this->putJson('/api/v1/auth/perfil', [
            'email' => 'nuevo@correo.com',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $usuario->id,
            'email' => 'nuevo@correo.com',
        ]);
    }

    public function test_puede_actualizar_datos_de_su_persona(): void
    {
        $usuario = $this->vendedor();
        $persona = $usuario->persona;

        Sanctum::actingAs($usuario);

        $this->putJson('/api/v1/auth/perfil', [
            'nombres' => 'Juan Carlos',
            'celular' => '70123456',
        ])->assertOk();

        $this->assertDatabaseHas('personas', [
            'id' => $persona->id,
            'nombres' => 'Juan Carlos',
            'celular' => '70123456',
        ]);
    }

    public function test_rechaza_nombre_muy_corto(): void
    {
        $usuario = $this->vendedor();

        Sanctum::actingAs($usuario);

        $this->putJson('/api/v1/auth/perfil', [
            'name' => 'ab',
        ])->assertUnprocessable();
    }

    public function test_rechaza_correo_invalido(): void
    {
        $usuario = $this->vendedor();

        Sanctum::actingAs($usuario);

        $this->putJson('/api/v1/auth/perfil', [
            'email' => 'no-es-correo',
        ])->assertUnprocessable();
    }

    public function test_rechaza_celular_con_menos_de_8_digitos(): void
    {
        $usuario = $this->vendedor();

        Sanctum::actingAs($usuario);

        $this->putJson('/api/v1/auth/perfil', [
            'celular' => '123',
        ])->assertUnprocessable();
    }

    // ---- Cambiar contraseña ------------------------------------------------

    public function test_puede_cambiar_su_contrasena(): void
    {
        $usuario = $this->vendedor();
        $contrasenaActual = 'password123';
        $usuario->update(['password' => Hash::make($contrasenaActual)]);

        Sanctum::actingAs($usuario);

        $this->putJson('/api/v1/auth/password', [
            'password_actual' => $contrasenaActual,
            'password_nuevo' => 'nuevaPassword123',
            'password_nuevo_confirmation' => 'nuevaPassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('nuevaPassword123', $usuario->fresh()->password));
    }

    public function test_rechaza_contrasena_actual_incorrecta(): void
    {
        $usuario = $this->vendedor();
        $usuario->update(['password' => Hash::make('password123')]);

        Sanctum::actingAs($usuario);

        $this->putJson('/api/v1/auth/password', [
            'password_actual' => 'incorrecta',
            'password_nuevo' => 'nuevaPassword123',
            'password_nuevo_confirmation' => 'nuevaPassword123',
        ])->assertUnprocessable();
    }

    public function test_rechaza_contrasena_nueva_muy_corta(): void
    {
        $usuario = $this->vendedor();
        $usuario->update(['password' => Hash::make('password123')]);

        Sanctum::actingAs($usuario);

        $this->putJson('/api/v1/auth/password', [
            'password_actual' => 'password123',
            'password_nuevo' => 'corta',
            'password_nuevo_confirmation' => 'corta',
        ])->assertUnprocessable();
    }

    public function test_rechaza_contrasenas_que_no_coinciden(): void
    {
        $usuario = $this->vendedor();
        $usuario->update(['password' => Hash::make('password123')]);

        Sanctum::actingAs($usuario);

        $this->putJson('/api/v1/auth/password', [
            'password_actual' => 'password123',
            'password_nuevo' => 'nuevaPassword123',
            'password_nuevo_confirmation' => 'otraPassword123',
        ])->assertUnprocessable();
    }

    // ---- Editar cliente ----------------------------------------------------

    public function test_puede_editar_cliente_con_permiso(): void
    {
        $supervisor = $this->supervisor();
        $cliente = Cliente::factory()->create();

        Sanctum::actingAs($supervisor);

        $this->postJson("/api/v1/clientes/{$cliente->id}", [
            'nombres' => 'María Elena',
            'celular' => '70987654',
        ])->assertOk();

        $this->assertDatabaseHas('personas', [
            'id' => $cliente->persona_id,
            'nombres' => 'María Elena',
            'celular' => '70987654',
        ]);
    }

    public function test_vendedor_no_puede_editar_cliente(): void
    {
        $vendedor = $this->vendedor();
        $cliente = Cliente::factory()->create();

        Sanctum::actingAs($vendedor);

        $this->postJson("/api/v1/clientes/{$cliente->id}", [
            'nombres' => 'María Elena',
        ])->assertForbidden();
    }

    public function test_rechaza_carnet_duplicado_al_editar_cliente(): void
    {
        $supervisor = $this->supervisor();
        $otraPersona = Persona::factory()->create(['carnet' => '12345678']);
        $cliente = Cliente::factory()->create();

        Sanctum::actingAs($supervisor);

        $this->postJson("/api/v1/clientes/{$cliente->id}", [
            'carnet' => '12345678',
        ])->assertUnprocessable();
    }

    public function test_puede_editar_solo_algunos_campos_del_cliente(): void
    {
        $supervisor = $this->supervisor();
        $cliente = Cliente::factory()->create();
        $correoOriginal = $cliente->persona->correo;

        Sanctum::actingAs($supervisor);

        // Solo actualizar celular, sin tocar otros campos
        $this->postJson("/api/v1/clientes/{$cliente->id}", [
            'celular' => '70111111',
        ])->assertOk();

        $this->assertDatabaseHas('personas', [
            'id' => $cliente->persona_id,
            'celular' => '70111111',
        ]);
    }
}
