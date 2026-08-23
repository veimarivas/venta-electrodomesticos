<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Alta, edición y baja de proveedores desde la app.
 *
 * Mismas reglas que el panel: si aquí fueran más laxas se colarían datos que el
 * otro formulario rechaza.
 */
class ProveedorEscrituraApiTest extends TestCase
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

    public function test_crea_un_proveedor(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/proveedores', [
            'nombre' => 'Importadora del Sur',
            'nit' => '1234567890',
            'telefono' => '+591 70000000',
        ])->assertCreated()->assertJsonPath('data.nombre', 'Importadora del Sur');

        $this->assertDatabaseHas('proveedores', ['nit' => '1234567890']);
    }

    public function test_edita_un_proveedor_sin_chocar_con_su_propio_nit(): void
    {
        $proveedor = Proveedor::factory()->create(['nit' => '999888777']);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/proveedores/{$proveedor->id}", [
            'nombre' => 'Nombre corregido',
            'nit' => '999888777',
        ])->assertOk();

        $this->assertSame('Nombre corregido', $proveedor->fresh()->nombre);
    }

    public function test_no_se_repite_el_nit(): void
    {
        Proveedor::factory()->create(['nit' => '111222333']);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/proveedores', [
            'nombre' => 'Otro proveedor',
            'nit' => '111222333',
        ])->assertStatus(422)->assertJsonValidationErrors('nit');
    }

    public function test_un_nit_vacio_se_guarda_como_nulo(): void
    {
        Proveedor::factory()->create(['nit' => null]);

        Sanctum::actingAs($this->admin());

        // Cadena vacía en una columna única bloquearía al segundo proveedor
        // sin NIT.
        $this->postJson('/api/v1/proveedores', [
            'nombre' => 'Proveedor sin NIT',
            'nit' => '',
        ])->assertCreated();

        $this->assertNull(Proveedor::where('nombre', 'Proveedor sin NIT')->value('nit'));
    }

    public function test_el_telefono_rechaza_caracteres_raros(): void
    {
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/proveedores', [
            'nombre' => 'Proveedor',
            'telefono' => 'llamar al fijo',
        ])->assertStatus(422)->assertJsonValidationErrors('telefono');
    }

    public function test_no_se_elimina_un_proveedor_con_compras(): void
    {
        $proveedor = Proveedor::factory()->create();
        Compra::factory()->create(['proveedor_id' => $proveedor->id]);

        Sanctum::actingAs($this->admin());

        // Dejaría el histórico de costos sin origen: de dónde salió la
        // mercadería es parte del kardex.
        $this->deleteJson("/api/v1/proveedores/{$proveedor->id}")->assertStatus(422);

        $this->assertNull($proveedor->fresh()->deleted_at);
    }

    public function test_elimina_un_proveedor_al_que_nunca_se_le_compro(): void
    {
        $proveedor = Proveedor::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/proveedores/{$proveedor->id}")->assertOk();

        $this->assertSoftDeleted('proveedores', ['id' => $proveedor->id]);
    }

    public function test_un_supervisor_puede_crear_y_editar_pero_no_eliminar(): void
    {
        $proveedor = Proveedor::factory()->create();

        // El supervisor mantiene el catálogo de proveedores pero no los borra:
        // es el único permiso de proveedores que solo tiene el administrador.
        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('supervisor'));

        $this->postJson('/api/v1/proveedores', ['nombre' => 'Nuevo proveedor'])
            ->assertCreated();
        $this->postJson("/api/v1/proveedores/{$proveedor->id}", ['nombre' => 'Editado'])
            ->assertOk();
        $this->deleteJson("/api/v1/proveedores/{$proveedor->id}")->assertForbidden();
    }

    public function test_un_vendedor_no_puede_tocar_proveedores(): void
    {
        $proveedor = Proveedor::factory()->create();

        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('vendedor'));

        $this->postJson('/api/v1/proveedores', ['nombre' => 'Nuevo'])->assertForbidden();
        $this->postJson("/api/v1/proveedores/{$proveedor->id}", ['nombre' => 'X'])->assertForbidden();
        $this->deleteJson("/api/v1/proveedores/{$proveedor->id}")->assertForbidden();
    }
}
