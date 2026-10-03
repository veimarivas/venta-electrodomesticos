<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Buscador global de la app: la versión API del buscador del topbar del panel.
 */
class BusquedaApiTest extends TestCase
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

    private function productoConUnidad(string $nombre, string $serial): Unidad
    {
        return Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'nombre' => $nombre,
                'precio_venta' => 1000,
                'stock_minimo' => 0,
                'descuento_maximo' => 0,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => 500,
            'precio_venta' => 1000,
            'serial' => $serial,
        ]);
    }

    public function test_el_buscador_devuelve_productos_y_aparatos(): void
    {
        $this->productoConUnidad('Televisor Smart 55', 'SN-TV-100');

        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson('/api/v1/buscar?termino=Televisor')->assertOk();

        $claves = array_column($respuesta->json('data'), 'clave');

        $this->assertContains('productos', $claves);
        // El aparato sale por la cascada al nombre del producto.
        $this->assertContains('unidades', $claves);

        $productos = collect($respuesta->json('data'))->firstWhere('clave', 'productos');
        $this->assertSame('producto', $productos['items'][0]['tipo']);
        $this->assertStringContainsString('en stock', $productos['items'][0]['nota']);
    }

    public function test_un_aparato_vendido_lleva_a_su_venta(): void
    {
        $admin = $this->admin();
        $unidad = $this->productoConUnidad('Refrigerador', 'SN-REF-1');

        $venta = app(RegistroDeVenta::class)->registrar(
            lineas: [['unidad_id' => $unidad->id, 'precio_unitario' => 1000, 'descuento' => 0]],
            cabecera: ['metodo_pago' => 'efectivo'],
            userId: $admin->id,
        );

        Sanctum::actingAs($admin);

        $respuesta = $this->getJson('/api/v1/buscar?termino=SN-REF-1')->assertOk();

        $unidades = collect($respuesta->json('data'))->firstWhere('clave', 'unidades');

        $this->assertNotNull($unidades);
        $this->assertSame($venta->id, $unidades['items'][0]['venta_id']);

        // Y la venta por su código.
        $porVenta = $this->getJson("/api/v1/buscar?termino={$venta->codigo}")->assertOk();
        $ventas = collect($porVenta->json('data'))->firstWhere('clave', 'ventas');
        $this->assertSame($venta->id, $ventas['items'][0]['id']);
    }

    public function test_aparecen_clientes_y_compras(): void
    {
        $admin = $this->admin();

        Compra::factory()->create([
            'codigo' => 'COM-TEST-777',
            'proveedor_id' => \App\Models\Proveedor::factory()->create(['nombre' => 'Mayorista Central'])->id,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/buscar?termino=COM-TEST-777')
            ->assertOk()
            ->assertJsonPath('data.0.clave', 'compras')
            ->assertJsonPath('data.0.items.0.tipo', 'compra');
    }

    public function test_sin_permisos_el_buscador_viene_vacio(): void
    {
        $this->productoConUnidad('Televisor Smart 55', 'SN-TV-200');

        // Sin rol no tiene ningún permiso de módulo.
        Sanctum::actingAs(User::factory()->create(['is_active' => true]));

        $this->getJson('/api/v1/buscar?termino=Televisor')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_un_termino_muy_corto_no_busca(): void
    {
        $this->productoConUnidad('Televisor Smart 55', 'SN-TV-300');

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/buscar?termino=T')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
