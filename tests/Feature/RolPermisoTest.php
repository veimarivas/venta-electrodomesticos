<?php

namespace Tests\Feature;

use App\Livewire\Roles\Index;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolPermisoTest extends TestCase
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

    // ---- Listado ----------------------------------------------------------

    public function test_muestra_los_roles_con_sus_conteos(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('roles', fn ($roles) => $roles->count() === 3
                && $roles->firstWhere('name', 'vendedor')->permissions_count > 0);
    }

    public function test_agrupa_los_permisos_por_modulo(): void
    {
        $componente = Livewire::actingAs($this->admin())->test(Index::class);

        $grupos = $componente->instance()->permisosPorModulo();

        // 'ventas.crear' debe caer bajo el grupo 'ventas'.
        $this->assertTrue($grupos->has('ventas'));
        $this->assertTrue(
            $grupos['ventas']->pluck('name')->contains('ventas.crear')
        );
    }

    // ---- Alta y edición ---------------------------------------------------

    public function test_crea_un_rol(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('nombre', 'cajero')
            ->assertSet('formularioValido', true)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Rol creado correctamente.');

        $this->assertDatabaseHas('roles', ['name' => 'cajero', 'guard_name' => 'web']);
    }

    public function test_no_permite_dos_roles_con_el_mismo_nombre(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombre', 'vendedor')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'unique']);
    }

    public function test_renombra_un_rol_conservando_sus_permisos(): void
    {
        $rol = Role::findByName('vendedor');
        $permisosAntes = $rol->permissions->count();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $rol->id)
            ->assertSet('nombre', 'vendedor')
            ->set('nombre', 'vendedor mostrador')
            ->call('guardar')
            ->assertHasNoErrors();

        $rol->refresh();

        $this->assertSame('vendedor mostrador', $rol->name);
        $this->assertCount($permisosAntes, $rol->permissions);
    }

    // ---- Matriz de permisos ----------------------------------------------

    public function test_asigna_permisos_a_un_rol(): void
    {
        $rol = Role::findByName('vendedor');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirPermisos', $rol->id)
            ->assertSet('permisosRolNombre', 'vendedor')
            ->set('permisosSeleccionados', ['ventas.ver', 'ventas.crear', 'ventas.anular'])
            ->call('guardarPermisos')
            ->assertDispatched('cerrar-modal-permisos-rol')
            ->assertDispatched('toast', tipo: 'success');

        $rol->refresh();

        $this->assertTrue($rol->hasPermissionTo('ventas.anular'));
        // syncPermissions reemplaza: lo que no se marcó, se quita.
        $this->assertFalse($rol->hasPermissionTo('personas.ver'));
    }

    public function test_marcar_modulo_alterna_todos_sus_permisos(): void
    {
        $rol = Role::findByName('vendedor');

        $componente = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirPermisos', $rol->id)
            ->set('permisosSeleccionados', [])
            ->call('alternarModulo', 'cargos');

        $seleccionados = $componente->get('permisosSeleccionados');

        foreach (['cargos.ver', 'cargos.crear', 'cargos.editar', 'cargos.eliminar'] as $permiso) {
            $this->assertContains($permiso, $seleccionados);
        }

        // Volver a llamarlo los quita todos.
        $componente->call('alternarModulo', 'cargos');

        $this->assertEmpty($componente->get('permisosSeleccionados'));
    }

    public function test_marcar_y_desmarcar_todos(): void
    {
        $rol = Role::findByName('vendedor');
        $total = Permission::count();

        $componente = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirPermisos', $rol->id)
            ->call('marcarTodos');

        $this->assertCount($total, $componente->get('permisosSeleccionados'));

        $componente->call('desmarcarTodos');

        $this->assertEmpty($componente->get('permisosSeleccionados'));
    }

    public function test_ignora_permisos_inventados_que_lleguen_del_navegador(): void
    {
        $rol = Role::findByName('vendedor');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirPermisos', $rol->id)
            ->set('permisosSeleccionados', ['ventas.ver', 'permiso.inventado'])
            ->call('guardarPermisos');

        $rol->refresh();

        $this->assertTrue($rol->hasPermissionTo('ventas.ver'));
        $this->assertCount(1, $rol->permissions);
    }

    public function test_los_cambios_de_permisos_surten_efecto_de_inmediato(): void
    {
        // spatie cachea los permisos: si no se limpia la caché, el usuario
        // seguiría sin poder entrar aunque el rol ya lo tenga.
        $usuario = User::factory()->create()->syncRoles('vendedor');
        $rol = Role::findByName('vendedor');

        $this->assertFalse($usuario->can('cargos.crear'));

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirPermisos', $rol->id)
            ->set('permisosSeleccionados', ['cargos.crear'])
            ->call('guardarPermisos');

        $this->assertTrue($usuario->fresh()->can('cargos.crear'));
    }

    // ---- Rol protegido ----------------------------------------------------

    public function test_el_rol_admin_no_se_puede_renombrar(): void
    {
        $admin = Role::findByName('admin');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $admin->id)
            ->assertDispatched('toast', tipo: 'error')
            ->assertSet('rolId', null);

        $this->assertSame('admin', $admin->fresh()->name);
    }

    public function test_el_rol_admin_no_se_puede_eliminar(): void
    {
        $admin = Role::findByName('admin');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $admin->id)
            ->assertDispatched('toast', tipo: 'error')
            ->assertSet('eliminarId', null);

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
    }

    // ---- Eliminación ------------------------------------------------------

    public function test_elimina_un_rol_sin_usuarios(): void
    {
        $rol = Role::create(['name' => 'temporal', 'guard_name' => 'web']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $rol->id)
            ->assertSet('eliminarUsuarios', 0)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'success');

        $this->assertDatabaseMissing('roles', ['id' => $rol->id]);
    }

    public function test_no_elimina_un_rol_que_tiene_usuarios(): void
    {
        // Borrarlo dejaría a esos usuarios sin ningún permiso sin avisar.
        $rol = Role::findByName('vendedor');
        User::factory()->count(2)->create()->each(fn ($u) => $u->syncRoles('vendedor'));

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $rol->id)
            ->assertSet('eliminarUsuarios', 2)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'error');

        $this->assertDatabaseHas('roles', ['id' => $rol->id]);
    }

    // ---- Permisos del propio módulo --------------------------------------

    public function test_un_vendedor_no_entra_al_modulo_de_roles(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        $this->actingAs($vendedor)->get('/roles')->assertForbidden();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();
    }
}
