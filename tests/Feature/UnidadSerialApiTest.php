<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Registro del serial del fabricante desde la app, leyéndolo con la cámara.
 *
 * El código interno lo pone el sistema al recepcionar la compra; el serial va
 * impreso en la caja y se registra después, con el aparato delante.
 */
class UnidadSerialApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function unidad(?string $serial = null): Unidad
    {
        return Unidad::factory()->create([
            'producto_id' => Producto::factory()->create()->id,
            'estado' => 'en_stock',
            'serial' => $serial,
        ]);
    }

    public function test_registra_el_serial_leido_con_la_camara(): void
    {
        $unidad = $this->unidad();

        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('admin'));

        $this->postJson("/api/v1/unidades/{$unidad->id}/serial", [
            'serial' => 'SN-NUEVO-001',
        ])->assertOk()->assertJsonPath('data.serial', 'SN-NUEVO-001');

        $this->assertSame('SN-NUEVO-001', $unidad->fresh()->serial);
    }

    public function test_los_espacios_alrededor_del_serial_no_se_guardan(): void
    {
        $unidad = $this->unidad();

        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('admin'));

        $this->postJson("/api/v1/unidades/{$unidad->id}/serial", [
            'serial' => '  SN-CON-ESPACIOS  ',
        ])->assertOk();

        $this->assertSame('SN-CON-ESPACIOS', $unidad->fresh()->serial);
    }

    public function test_un_serial_repetido_dice_en_que_unidad_esta(): void
    {
        $ocupada = $this->unidad('SN-REPETIDO');
        $unidad = $this->unidad();

        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('admin'));

        // Escanear dos veces el mismo aparato es el error más fácil de cometer
        // en el almacén: el mensaje tiene que decir dónde está ya ese serial.
        $respuesta = $this->postJson("/api/v1/unidades/{$unidad->id}/serial", [
            'serial' => 'SN-REPETIDO',
        ])->assertStatus(422);

        $this->assertStringContainsString(
            $ocupada->codigo_interno,
            implode(' ', $respuesta->json('errors.serial')),
        );

        $this->assertNull($unidad->fresh()->serial);
    }

    public function test_un_serial_que_solo_cambia_en_mayusculas_se_considera_repetido(): void
    {
        $this->unidad('SN-REPETIDO');
        $unidad = $this->unidad();

        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('admin'));

        $this->postJson("/api/v1/unidades/{$unidad->id}/serial", [
            'serial' => 'sn-repetido',
        ])->assertStatus(422);

        $this->assertNull($unidad->fresh()->serial);
    }

    public function test_un_serial_en_blanco_no_se_guarda(): void
    {
        $unidad = $this->unidad();

        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('admin'));

        // Una cadena vacía en una columna única bloquearía a la SEGUNDA unidad
        // sin serial: los vacíos tienen que quedarse en NULL.
        $this->postJson("/api/v1/unidades/{$unidad->id}/serial", [
            'serial' => '   ',
        ])->assertStatus(422);

        $this->assertNull($unidad->fresh()->serial);
    }

    public function test_reescribir_el_mismo_serial_de_la_unidad_no_choca_consigo_misma(): void
    {
        $unidad = $this->unidad('SN-IGUAL');

        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('admin'));

        $this->postJson("/api/v1/unidades/{$unidad->id}/serial", [
            'serial' => 'SN-IGUAL',
        ])->assertOk();
    }

    public function test_sin_permiso_de_editar_unidades_no_se_puede_registrar(): void
    {
        $unidad = $this->unidad();

        // El vendedor consulta el catálogo, pero no cambia el inventario.
        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('vendedor'));

        $this->postJson("/api/v1/unidades/{$unidad->id}/serial", [
            'serial' => 'SN-NO-DEBE-ENTRAR',
        ])->assertForbidden();

        $this->assertNull($unidad->fresh()->serial);
    }

    public function test_sin_sesion_no_se_puede_registrar(): void
    {
        $unidad = $this->unidad();

        $this->postJson("/api/v1/unidades/{$unidad->id}/serial", [
            'serial' => 'SN-SIN-SESION',
        ])->assertUnauthorized();
    }
}
