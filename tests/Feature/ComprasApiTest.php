<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Support\GeneradorCodigoCompra;
use App\Support\RecepcionDeCompra;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API de proveedores y órdenes de compra que consume la app.
 */
class ComprasApiTest extends TestCase
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

    /**
     * Compra en borrador con una línea, lista para recepcionar.
     */
    private function compraBorrador(
        ?Proveedor $proveedor = null,
        int $cantidad = 3,
        float $costo = 1000,
        float $flete = 300,
        float $precioVenta = 1800,
    ): Compra {
        $compra = app(GeneradorCodigoCompra::class)->crearCon([
            'proveedor_id' => ($proveedor ?? Proveedor::factory()->create())->id,
            'user_id' => $this->admin()->id,
            'fecha_compra' => now()->toDateString(),
            'estado' => 'borrador',
            'moneda' => 'BOB',
            'tipo_cambio' => 1,
            'subtotal' => $cantidad * $costo,
            'flete' => $flete,
            'total' => $cantidad * $costo + $flete,
        ]);

        CompraDetalle::create([
            'compra_id' => $compra->id,
            'producto_id' => Producto::factory()->create()->id,
            'cantidad' => $cantidad,
            'costo_unitario' => $costo,
            'subtotal' => $cantidad * $costo,
            'precio_venta' => $precioVenta,
        ]);

        return $compra->refresh();
    }

    // ---- Proveedores -------------------------------------------------------

    public function test_el_listado_resume_lo_invertido_en_cada_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Importadora Andina']);

        app(RecepcionDeCompra::class)->recepcionar($this->compraBorrador($proveedor));

        Sanctum::actingAs($this->admin());

        $fila = $this->getJson('/api/v1/proveedores')->assertOk()->json('data.0');

        $this->assertSame('Importadora Andina', $fila['nombre']);
        $this->assertSame(1, $fila['compras']['total']);
        // 3 × 1000 + 300 de flete.
        $this->assertEquals(3300, $fila['compras']['invertido']);
        $this->assertSame(3, $fila['compras']['unidades']);
        $this->assertNotNull($fila['compras']['ultima']);
    }

    public function test_un_borrador_no_cuenta_como_dinero_invertido(): void
    {
        // Hasta recepcionar no ha entrado mercadería ni ha salido dinero.
        $proveedor = Proveedor::factory()->create();
        $this->compraBorrador($proveedor);

        Sanctum::actingAs($this->admin());

        $fila = $this->getJson('/api/v1/proveedores')->assertOk()->json('data.0');

        $this->assertSame(0, $fila['compras']['total']);
        $this->assertEquals(0, $fila['compras']['invertido']);
    }

    public function test_la_ficha_del_proveedor_trae_sus_ultimas_compras(): void
    {
        $proveedor = Proveedor::factory()->create();
        app(RecepcionDeCompra::class)->recepcionar($this->compraBorrador($proveedor));

        Sanctum::actingAs($this->admin());

        $ficha = $this->getJson("/api/v1/proveedores/{$proveedor->id}")->assertOk()->json('data');

        $this->assertCount(1, $ficha['ultimas_compras']);
        $this->assertSame('recepcionada', $ficha['ultimas_compras'][0]['estado']);
    }

    public function test_sin_permiso_de_compras_no_viaja_lo_invertido(): void
    {
        $proveedor = Proveedor::factory()->create();
        app(RecepcionDeCompra::class)->recepcionar($this->compraBorrador($proveedor));

        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->syncPermissions(['proveedores.ver']);

        Sanctum::actingAs($usuario);

        $fila = $this->getJson('/api/v1/proveedores')->assertOk()->json('data.0');

        // Queda la ficha de contacto, que es para lo que se abre en el almacén.
        $this->assertArrayNotHasKey('compras', $fila);
        $this->assertNotNull($fila['nombre']);
    }

    // ---- Compras -----------------------------------------------------------

    public function test_el_listado_distingue_borradores_de_recepcionadas(): void
    {
        $this->compraBorrador();
        app(RecepcionDeCompra::class)->recepcionar($this->compraBorrador());

        Sanctum::actingAs($this->admin());

        $porEstado = collect(
            $this->getJson('/api/v1/compras')->assertOk()->json('data')
        )->keyBy('estado');

        $this->assertTrue($porEstado['borrador']['es_borrador']);
        // En un borrador todavía no existen unidades: se crean al recepcionar.
        $this->assertSame(0, $porEstado['borrador']['unidades']);

        $this->assertTrue($porEstado['recepcionada']['esta_recepcionada']);
        $this->assertSame(3, $porEstado['recepcionada']['unidades']);
        $this->assertNotNull($porEstado['recepcionada']['recepcionada_en']);
    }

    public function test_el_listado_filtra_por_proveedor_y_por_estado(): void
    {
        $uno = Proveedor::factory()->create();
        $otro = Proveedor::factory()->create();

        $this->compraBorrador($uno);
        app(RecepcionDeCompra::class)->recepcionar($this->compraBorrador($otro));

        Sanctum::actingAs($this->admin());

        $delUno = $this->getJson("/api/v1/compras?proveedor_id={$uno->id}")->assertOk()->json('data');
        $this->assertCount(1, $delUno);
        $this->assertSame('borrador', $delUno[0]['estado']);

        $recepcionadas = $this->getJson('/api/v1/compras?estado=recepcionada')->assertOk()->json('data');
        $this->assertCount(1, $recepcionadas);
        $this->assertSame($otro->id, $recepcionadas[0]['proveedor_id']);
    }

    public function test_la_ficha_desglosa_los_gastos_y_el_costo_real(): void
    {
        $compra = $this->compraBorrador(cantidad: 3, costo: 1000, flete: 300, precioVenta: 1800);
        app(RecepcionDeCompra::class)->recepcionar($compra);

        Sanctum::actingAs($this->admin());

        $ficha = $this->getJson("/api/v1/compras/{$compra->id}")->assertOk()->json('data');

        // El flete se reparte entre las unidades; el impuesto no, porque en
        // Bolivia suele ser recuperable.
        $this->assertEquals(300, $ficha['gastos_prorrateables']);
        $this->assertEquals(3300, $ficha['total']);

        $linea = $ficha['detalles'][0];

        $this->assertSame(3, $linea['cantidad']);
        $this->assertEquals(1000, $linea['costo_unitario']);
        // 1000 + 300/3 de flete: lo que de verdad cuesta cada aparato.
        $this->assertEquals(1100, $linea['costo_real_unitario']);
        $this->assertEquals(700, $linea['margen_unitario']);
        $this->assertSame(3, $linea['unidades']);
    }

    public function test_en_un_borrador_la_linea_no_finge_tener_costo_real(): void
    {
        $compra = $this->compraBorrador();

        Sanctum::actingAs($this->admin());

        $linea = $this->getJson("/api/v1/compras/{$compra->id}")
            ->assertOk()
            ->json('data.detalles.0');

        $this->assertEquals(0, $linea['costo_real_unitario']);
        // Sin costo real no hay margen que calcular; inventarlo con el costo
        // sin prorratear lo dejaría inflado.
        $this->assertNull($linea['margen_unitario']);
    }

    public function test_las_unidades_de_la_compra_van_en_su_propia_ruta(): void
    {
        $compra = $this->compraBorrador();
        app(RecepcionDeCompra::class)->recepcionar($compra);

        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson("/api/v1/compras/{$compra->id}/unidades")->assertOk();

        $this->assertCount(3, $respuesta->json('data'));
        $this->assertSame(3, $respuesta->json('meta.total'));
        $this->assertSame(3, $respuesta->json('meta.en_stock'));
        $this->assertNotNull($respuesta->json('data.0.codigo_interno'));
        $this->assertNotNull($respuesta->json('data.0.producto'));
    }

    public function test_las_unidades_exigen_el_permiso_de_verlas(): void
    {
        $compra = $this->compraBorrador();
        app(RecepcionDeCompra::class)->recepcionar($compra);

        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->syncPermissions(['compras.ver']);

        Sanctum::actingAs($usuario);

        $this->getJson("/api/v1/compras/{$compra->id}")->assertOk();
        $this->getJson("/api/v1/compras/{$compra->id}/unidades")->assertForbidden();
    }

    public function test_las_compras_exigen_su_permiso(): void
    {
        $usuario = User::factory()->create(['is_active' => true]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/v1/compras')->assertForbidden();
        $this->getJson('/api/v1/proveedores')->assertForbidden();
    }

    public function test_la_api_no_permite_recepcionar_ni_crear_compras(): void
    {
        // Recepcionar genera las unidades físicas del almacén: se hace con la
        // mercadería delante, no desde el teléfono.
        $compra = $this->compraBorrador();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/compras')->assertStatus(405);
        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar")->assertNotFound();
    }
}
