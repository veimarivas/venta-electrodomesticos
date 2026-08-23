<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Los tres análisis que el panel ya tenía y a la app le faltaban: por
 * vendedor, por método de pago e inventario.
 *
 * Lo que más importa aquí es **qué NO viaja**: la app la puede tener un
 * vendedor, y el margen de la tienda no es dato suyo.
 */
class ReportesAppApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function conCostos(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('admin');
    }

    /** Ve reportes pero no costos: es el caso que hay que proteger. */
    private function sinCostos(): User
    {
        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->syncRoles('vendedor');
        $usuario->givePermissionTo('reportes.ver');

        return $usuario->fresh();
    }

    private function ventaDeHoy(User $vendedor): Venta
    {
        return Venta::factory()->create([
            'user_id' => $vendedor->id,
            'estado' => 'completada',
            'vendida_en' => now(),
            'total' => 1500,
            'ganancia' => 500,
            'metodo_pago' => 'efectivo',
        ]);
    }

    public function test_por_vendedor_agrupa_las_ventas_del_periodo(): void
    {
        $admin = $this->conCostos();
        $this->ventaDeHoy($admin);
        $this->ventaDeHoy($admin);

        Sanctum::actingAs($admin);

        $respuesta = $this->getJson('/api/v1/dashboard/por-vendedor?rango=hoy')->assertOk();

        $this->assertSame(2, $respuesta->json('data.0.ventas'));
        $this->assertEquals(3000, $respuesta->json('data.0.ingreso'));
        $this->assertEquals(1000, $respuesta->json('data.0.ganancia'));
    }

    public function test_sin_permiso_de_costos_la_ganancia_por_vendedor_no_viaja(): void
    {
        $this->ventaDeHoy($this->conCostos());

        Sanctum::actingAs($this->sinCostos());

        $respuesta = $this->getJson('/api/v1/dashboard/por-vendedor?rango=hoy')->assertOk();

        // No basta con ocultarla en la app: no debe salir del servidor.
        $this->assertArrayNotHasKey('ganancia', $respuesta->json('data.0'));
        $this->assertEquals(1500, $respuesta->json('data.0.ingreso'));
    }

    public function test_por_metodo_de_pago_traduce_la_etiqueta(): void
    {
        $admin = $this->conCostos();
        $this->ventaDeHoy($admin);

        Sanctum::actingAs($admin);

        $respuesta = $this->getJson('/api/v1/dashboard/por-metodo-pago?rango=hoy')->assertOk();

        // La etiqueta viaja resuelta: el histórico incluye métodos retirados
        // que la app no conoce, y traducirlos allá los duplicaría.
        $this->assertSame('efectivo', $respuesta->json('data.0.metodo'));
        $this->assertSame('Efectivo', $respuesta->json('data.0.etiqueta'));
        $this->assertEquals(1500, $respuesta->json('data.0.ingreso'));
    }

    public function test_el_inventario_suma_lo_que_hay_en_la_estanteria(): void
    {
        $producto = Producto::factory()->create();

        Unidad::factory()->count(3)->create([
            'producto_id' => $producto->id,
            'estado' => 'en_stock',
            'costo_unitario' => 100,
            'precio_venta' => 180,
        ]);

        // Vendida: ya no está en la estantería y no debe sumar.
        Unidad::factory()->create([
            'producto_id' => $producto->id,
            'estado' => 'vendido',
            'costo_unitario' => 100,
            'precio_venta' => 180,
        ]);

        Sanctum::actingAs($this->conCostos());

        $respuesta = $this->getJson('/api/v1/dashboard/inventario')->assertOk();

        $this->assertSame(3, $respuesta->json('unidades'));
        $this->assertEquals(300, $respuesta->json('costo'));
        $this->assertEquals(540, $respuesta->json('valor'));
        $this->assertEquals(240, $respuesta->json('potencial'));
    }

    public function test_sin_permiso_de_costos_el_inventario_no_expone_el_costo(): void
    {
        Unidad::factory()->create([
            'estado' => 'en_stock',
            'costo_unitario' => 100,
            'precio_venta' => 180,
        ]);

        Sanctum::actingAs($this->sinCostos());

        $respuesta = $this->getJson('/api/v1/dashboard/inventario')->assertOk();

        $this->assertArrayNotHasKey('costo', $respuesta->json());
        $this->assertArrayNotHasKey('potencial', $respuesta->json());
        // El valor de venta y las unidades sí: es lo que hay para vender.
        $this->assertSame(1, $respuesta->json('unidades'));
        $this->assertEquals(180, $respuesta->json('valor'));
    }

    public function test_sin_permiso_de_reportes_no_se_puede_ver_nada_de_esto(): void
    {
        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('vendedor'));

        $this->getJson('/api/v1/dashboard/por-vendedor')->assertForbidden();
        $this->getJson('/api/v1/dashboard/por-metodo-pago')->assertForbidden();
        $this->getJson('/api/v1/dashboard/inventario')->assertForbidden();
    }
}
