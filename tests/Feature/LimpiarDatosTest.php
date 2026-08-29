<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Unidad;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * `datos:limpiar` deja la base como recién instalada sin perder los accesos.
 *
 * Es un comando destructivo y sin vuelta atrás, así que lo que tiene que
 * conservar está fijado aquí: si alguien añade una tabla y la mete por error
 * en la lista de intocables —o al revés, se lleva por delante los permisos—,
 * estas pruebas lo cazan.
 */
class LimpiarDatosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'email' => 'admin@tienda.test',
            'is_active' => true,
        ])->syncRoles('admin');
    }

    private function sembrarDatos(): void
    {
        $producto = Producto::factory()->create();
        Unidad::factory()->count(3)->create(['producto_id' => $producto->id]);
        Proveedor::factory()->create();
        Cliente::factory()->create();
    }

    // ---- Lo que conserva ---------------------------------------------------

    public function test_conserva_roles_permisos_y_la_cuenta_de_administrador(): void
    {
        $admin = $this->admin();
        $this->sembrarDatos();

        $roles = Role::count();
        $permisos = Permission::count();
        $matriz = DB::table('role_has_permissions')->count();

        $this->artisan('datos:limpiar', ['--force' => true])->assertSuccessful();

        $this->assertSame($roles, Role::count(), 'Se perdieron roles.');
        $this->assertSame($permisos, Permission::count(), 'Se perdieron permisos.');
        $this->assertSame($matriz, DB::table('role_has_permissions')->count(), 'Se perdió la matriz de permisos.');

        // Y la cuenta sigue entera: existe, activa y con su rol.
        $admin->refresh();
        $this->assertTrue($admin->exists);
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue($admin->can('ventas.crear'));
    }

    // ---- Lo que borra ------------------------------------------------------

    public function test_borra_los_datos_de_operacion(): void
    {
        $this->admin();
        $this->sembrarDatos();

        $this->artisan('datos:limpiar', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Producto::count());
        $this->assertSame(0, Unidad::count());
        $this->assertSame(0, Proveedor::count());
        $this->assertSame(0, Cliente::count());
    }

    public function test_borra_las_demas_cuentas_y_solo_deja_la_del_administrador(): void
    {
        $admin = $this->admin();
        User::factory()->count(3)->create()->each->syncRoles('vendedor');

        $this->artisan('datos:limpiar', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame($admin->id, User::first()->id);
    }

    public function test_las_cuentas_borradas_se_llevan_sus_roles_asignados(): void
    {
        $this->admin();
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        $this->artisan('datos:limpiar', ['--force' => true])->assertSuccessful();

        // Si quedara la fila en model_has_roles, un usuario nuevo que reusara
        // ese id heredaría permisos ajenos.
        $this->assertDatabaseMissing('model_has_roles', ['model_id' => $vendedor->id]);
    }

    // ---- A quién conserva --------------------------------------------------

    public function test_se_puede_indicar_que_cuenta_conservar(): void
    {
        $this->admin();
        $otro = User::factory()->create(['email' => 'supervisor@tienda.test'])
            ->syncRoles('supervisor');

        $this->artisan('datos:limpiar', [
            '--admin' => 'supervisor@tienda.test',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertSame($otro->id, User::first()->id);
    }

    public function test_sin_cuenta_que_conservar_no_borra_nada(): void
    {
        // Sin ningún admin y sin --admin, el comando no sabe qué salvar. Antes
        // de vaciar la base a ciegas, se planta.
        $this->sembrarDatos();

        $this->artisan('datos:limpiar', ['--force' => true])->assertFailed();

        $this->assertSame(1, Producto::count(), 'Borró datos sin saber qué cuenta conservar.');
    }

    public function test_sin_datos_que_borrar_no_hace_nada(): void
    {
        $this->admin();

        $this->artisan('datos:limpiar', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, User::count());
    }
}
