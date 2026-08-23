<?php

namespace Tests\Feature;

use App\Livewire\Usuarios\Index;
use App\Models\Persona;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UsuarioCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->syncRoles('admin');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(array $sobrescribir = []): array
    {
        return array_merge([
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@tienda.test',
            'phone' => '71234567',
            'password' => 'Secreta123',
            'password_confirmation' => 'Secreta123',
            'roles' => ['vendedor'],
            'persona_id' => (string) Persona::factory()->create()->id,
            'is_active' => true,
        ], $sobrescribir);
    }

    // ---- Listado ----------------------------------------------------------

    public function test_el_listado_muestra_diez_usuarios_por_pagina(): void
    {
        User::factory()->count(14)->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('usuarios', fn ($usuarios) => $usuarios->count() === 10);
    }

    public function test_el_listado_filtra_por_rol_y_por_estado(): void
    {
        User::factory()->create()->syncRoles('vendedor');
        User::factory()->create(['is_active' => false])->syncRoles('supervisor');

        $componente = Livewire::actingAs($this->admin())->test(Index::class);

        $componente->set('filtroRol', 'vendedor')
            ->assertViewHas('usuarios', fn ($u) => $u->total() === 1);

        $componente->set('filtroRol', '')
            ->set('filtroEstado', 'inactivos')
            ->assertViewHas('usuarios', fn ($u) => $u->total() === 1);
    }

    // ---- Alta -------------------------------------------------------------

    public function test_crea_un_usuario_con_su_rol(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->datosValidos())
            ->assertSet('formularioValido', true)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-usuario')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Usuario creado correctamente.');

        $usuario = User::where('email', 'nuevo@tienda.test')->first();

        $this->assertNotNull($usuario);
        $this->assertTrue($usuario->hasRole('vendedor'));
        $this->assertTrue(Hash::check('Secreta123', $usuario->password));
    }

    public function test_el_rol_es_obligatorio(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos(['roles' => []]))
            ->assertSet('formularioValido', false)
            ->call('guardar')
            ->assertHasErrors(['roles']);
    }

    public function test_la_contrasena_exige_ocho_caracteres_con_letras_y_numeros(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos(['password' => 'corta1', 'password_confirmation' => 'corta1']))
            ->assertHasErrors(['password'])
            ->set('password', 'solamenteletras')
            ->set('password_confirmation', 'solamenteletras')
            ->assertHasErrors(['password']);
    }

    public function test_las_contrasenas_deben_coincidir(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos(['password_confirmation' => 'Distinta123']))
            ->call('guardar')
            ->assertHasErrors(['password' => 'confirmed']);
    }

    public function test_no_permite_dos_cuentas_con_el_mismo_correo(): void
    {
        User::factory()->create(['email' => 'repetido@tienda.test']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos(['email' => 'repetido@tienda.test']))
            ->call('guardar')
            ->assertHasErrors(['email' => 'unique']);
    }

    // ---- Edición ----------------------------------------------------------

    public function test_al_editar_sin_tocar_la_contrasena_esta_se_conserva(): void
    {
        $usuario = User::factory()->create(['password' => 'OriginalPass1']);
        $usuario->syncRoles('vendedor');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $usuario->id)
            // El formulario nunca precarga la contraseña.
            ->assertSet('password', '')
            ->set('name', 'Nombre Cambiado')
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario->refresh();

        $this->assertSame('Nombre Cambiado', $usuario->name);
        $this->assertTrue(Hash::check('OriginalPass1', $usuario->password));
    }

    public function test_al_editar_se_puede_cambiar_el_rol(): void
    {
        $usuario = User::factory()->create();
        $usuario->syncRoles('vendedor');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $usuario->id)
            ->assertSet('roles', ['vendedor'])
            ->set('roles', ['supervisor'])
            ->call('guardar')
            ->assertHasNoErrors();

        $usuario->refresh();

        $this->assertTrue($usuario->hasRole('supervisor'));
        $this->assertFalse($usuario->hasRole('vendedor'));
    }

    public function test_vincula_y_cambia_la_persona_manteniendo_el_uno_a_uno(): void
    {
        $usuario = User::factory()->create();
        $usuario->syncRoles('vendedor');
        $persona = Persona::factory()->create();

        $componente = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $usuario->id)
            ->set('persona_id', (string) $persona->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame($persona->id, $usuario->fresh()->persona_id);

        // Cambiar a otra persona también funciona: la única la garantiza la BD.
        $otraPersona = Persona::factory()->create();

        $componente->call('abrirEditar', $usuario->id)
            ->set('persona_id', (string) $otraPersona->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame($otraPersona->id, $usuario->fresh()->persona_id);
    }

    public function test_un_usuario_no_queda_sin_persona_al_guardar(): void
    {
        $usuario = User::factory()->create();
        $usuario->syncRoles('vendedor');

        // Dejarla en blanco se rechaza: la relación 1 a 1 es obligatoria.
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $usuario->id)
            ->set('persona_id', '')
            ->call('guardar')
            ->assertHasErrors(['persona_id' => 'required']);

        $this->assertNotNull($usuario->fresh()->persona_id);
    }

    public function test_una_persona_ya_vinculada_no_se_puede_asignar_a_otra_cuenta(): void
    {
        $persona = Persona::factory()->create();
        $otroUsuario = User::factory()->create();
        $otroUsuario->update(['persona_id' => $persona->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos(['persona_id' => (string) $persona->id]))
            ->call('guardar')
            ->assertHasErrors(['persona_id']);
    }

    // ---- Salvaguardas -----------------------------------------------------

    public function test_no_puedes_quitarte_a_ti_mismo_el_rol_de_administrador(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirEditar', $admin->id)
            ->set('roles', ['vendedor'])
            ->call('guardar')
            ->assertHasErrors(['roles']);

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_no_puedes_desactivar_tu_propia_cuenta(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirEditar', $admin->id)
            ->set('is_active', false)
            ->call('guardar')
            ->assertHasErrors(['is_active']);

        $this->assertTrue($admin->fresh()->is_active);

        // Tampoco desde el interruptor del listado.
        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('alternarEstado', $admin->id)
            ->assertDispatched('toast', tipo: 'error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_no_puedes_eliminar_tu_propia_cuenta(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmarEliminar', $admin->id)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_no_se_puede_eliminar_al_unico_administrador(): void
    {
        // Quien elimina es otro admin, así que la salvaguarda que actúa no es
        // la de "tu propia cuenta" sino la del último administrador.
        $admin = $this->admin();
        $otroAdmin = User::factory()->create()->syncRoles('admin');

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmarEliminar', $otroAdmin->id)
            ->call('eliminar')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $otroAdmin->id]);

        // Ahora $admin es el último: otro usuario con permiso no puede borrarlo.
        $gestor = User::factory()->create()->syncRoles('supervisor');
        $gestor->givePermissionTo('usuarios.eliminar');

        Livewire::actingAs($gestor)
            ->test(Index::class)
            ->call('confirmarEliminar', $admin->id)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'error');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_alternar_estado_activa_y_desactiva_a_otro_usuario(): void
    {
        $usuario = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('alternarEstado', $usuario->id)
            ->assertDispatched('toast', tipo: 'success');

        $this->assertFalse($usuario->fresh()->is_active);
    }

    // ---- Permisos ---------------------------------------------------------

    public function test_un_vendedor_no_entra_al_modulo_de_usuarios(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        $this->actingAs($vendedor)->get('/usuarios')->assertForbidden();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();
    }
}
