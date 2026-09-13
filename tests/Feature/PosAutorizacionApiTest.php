<?php

namespace Tests\Feature;

use App\Models\Entrega;
use App\Models\Producto;
use App\Models\SolicitudDescuento;
use App\Models\Unidad;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * POS desde la app: costo con permiso, reserva del carrito, autorización de
 * descuentos y entrega a domicilio.
 */
class PosAutorizacionApiTest extends TestCase
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

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('admin');
    }

    private function unidadEnStock(float $costo = 200, float $precio = 400, float $tope = 50): Unidad
    {
        return Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => $precio,
                'descuento_maximo' => $tope,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => $costo,
            'precio_venta' => $precio,
        ]);
    }

    public function test_el_costo_solo_viaja_con_permiso_de_ver_costos(): void
    {
        $unidad = $this->unidadEnStock();

        Sanctum::actingAs($this->vendedor());
        $sinPermiso = $this->getJson('/api/v1/pos/buscar?termino='.$unidad->codigo_interno)->assertOk();
        $this->assertNull($sinPermiso->json('data.0.costo_unitario'));

        Sanctum::actingAs($this->admin());
        $conPermiso = $this->getJson('/api/v1/pos/buscar?termino='.$unidad->codigo_interno)->assertOk();
        $this->assertEquals(200.0, $conPermiso->json('data.0.costo_unitario'));
    }

    public function test_reservar_deja_el_aparato_en_proceso_de_venta(): void
    {
        $unidad = $this->unidadEnStock();
        $vendedor = $this->vendedor();

        Sanctum::actingAs($vendedor);
        $this->postJson('/api/v1/pos/reservar', ['unidad_id' => $unidad->id])->assertOk();

        $unidad->refresh();

        $this->assertSame('reservado', $unidad->estado);
        $this->assertSame($vendedor->id, $unidad->reservado_por);
    }

    public function test_otra_caja_no_puede_reservar_el_mismo_aparato(): void
    {
        $unidad = $this->unidadEnStock();

        Sanctum::actingAs($this->vendedor());
        $this->postJson('/api/v1/pos/reservar', ['unidad_id' => $unidad->id])->assertOk();

        Sanctum::actingAs($this->vendedor());
        $this->postJson('/api/v1/pos/reservar', ['unidad_id' => $unidad->id])->assertStatus(409);
    }

    public function test_liberar_devuelve_el_aparato_al_stock(): void
    {
        $unidad = $this->unidadEnStock();

        Sanctum::actingAs($this->vendedor());
        $this->postJson('/api/v1/pos/reservar', ['unidad_id' => $unidad->id])->assertOk();
        $this->postJson('/api/v1/pos/liberar', ['unidad_ids' => [$unidad->id]])->assertOk();

        $this->assertSame('en_stock', $unidad->fresh()->estado);
    }

    public function test_el_vendedor_solicita_autorizacion_y_el_admin_la_resuelve(): void
    {
        $unidad = $this->unidadEnStock(200, 400, 50);

        Sanctum::actingAs($this->vendedor());
        $respuesta = $this->postJson('/api/v1/pos/solicitudes-descuento', [
            'unidad_id' => $unidad->id,
            'precio' => 300,
        ])->assertStatus(201);

        $id = $respuesta->json('data.id');
        $this->assertSame('pendiente', $respuesta->json('data.estado'));

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/autorizaciones')->assertOk()->assertJsonFragment(['id' => $id]);

        $this->postJson("/api/v1/autorizaciones/{$id}/resolver", [
            'aprobar' => true,
            'precio' => 320,
        ])->assertOk()->assertJsonPath('data.estado', 'aprobada');

        $this->assertSame('320.00', SolicitudDescuento::find($id)->precio_aprobado);
    }

    public function test_cobrar_acepta_la_rebaja_autorizada(): void
    {
        $unidad = $this->unidadEnStock(200, 400, 50);
        $vendedor = $this->vendedor();

        Sanctum::actingAs($vendedor);
        $id = $this->postJson('/api/v1/pos/solicitudes-descuento', [
            'unidad_id' => $unidad->id,
            'precio' => 300,
        ])->json('data.id');

        Sanctum::actingAs($this->admin());
        $this->postJson("/api/v1/autorizaciones/{$id}/resolver", ['aprobar' => true])->assertOk();

        Sanctum::actingAs($vendedor);
        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 300]],
            'metodo_pago' => 'efectivo',
        ])->assertStatus(201);

        $this->assertSame('consumida', SolicitudDescuento::find($id)->estado);
    }

    public function test_cobrar_rechaza_la_rebaja_sin_autorizacion(): void
    {
        $unidad = $this->unidadEnStock(200, 400, 50);

        Sanctum::actingAs($this->vendedor());
        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 300]],
            'metodo_pago' => 'efectivo',
        ])->assertStatus(422);
    }

    public function test_cobrar_con_entrega_a_domicilio_crea_la_entrega(): void
    {
        $seLleva = $this->unidadEnStock(200, 400, 50);
        $aDomicilio = $this->unidadEnStock(200, 500, 50);

        Sanctum::actingAs($this->vendedor());
        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => [
                ['unidad_id' => $seLleva->id, 'precio' => 400, 'entrega' => 'directa'],
                ['unidad_id' => $aDomicilio->id, 'precio' => 500, 'entrega' => 'domicilio'],
            ],
            'metodo_pago' => 'efectivo',
            'entrega' => ['direccion' => 'Av. Siempre Viva 742'],
        ])->assertStatus(201);

        $this->assertSame(1, Entrega::count());
    }

    public function test_una_entrega_a_domicilio_sin_direccion_se_rechaza(): void
    {
        $unidad = $this->unidadEnStock();

        Sanctum::actingAs($this->vendedor());
        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 400, 'entrega' => 'domicilio']],
            'metodo_pago' => 'efectivo',
        ])->assertStatus(422);
    }
}
