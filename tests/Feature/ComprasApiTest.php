<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\PagoCompra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Support\GeneradorCodigoCompra;
use App\Support\RecepcionDeCompra;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    private function vendedor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    /**
     * Compra en pendiente con una línea (sin serial), lista para recepcionar.
     */
    private function compraPendiente(
        ?Proveedor $proveedor = null,
        int $cantidad = 3,
        float $costo = 1000,
        float $flete = 300,
        float $precioVenta = 1800,
        bool $tieneSerial = false,
    ): Compra {
        $compra = app(GeneradorCodigoCompra::class)->crearCon([
            'proveedor_id' => ($proveedor ?? Proveedor::factory()->create())->id,
            'user_id' => $this->admin()->id,
            'fecha_compra' => now()->toDateString(),
            'estado' => 'pendiente',
            'moneda' => 'BOB',
            'tipo_cambio' => 1,
            'subtotal' => $cantidad * $costo,
            'flete' => $flete,
            'total' => $cantidad * $costo + $flete,
        ]);

        CompraDetalle::create([
            'compra_id' => $compra->id,
            'producto_id' => Producto::factory()->create(['tiene_serial' => $tieneSerial])->id,
            'cantidad' => $cantidad,
            'costo_unitario' => $costo,
            'subtotal' => $cantidad * $costo,
            'precio_venta' => $precioVenta,
        ]);

        return $compra->refresh();
    }

    /**
     * Recepciona la compra verificando cada línea: seriales si el producto los
     * lleva, confirmación de la cantidad si no.
     */
    private function recepcionar(Compra $compra): int
    {
        $verificacion = [];

        foreach ($compra->detalles()->with('producto')->get() as $linea) {
            if ($linea->producto->tiene_serial) {
                $verificacion[$linea->id] = [
                    'seriales' => array_map(
                        fn (int $i): string => 'SN-'.$linea->id.'-'.$i,
                        range(1, $linea->cantidad)
                    ),
                ];
            } else {
                $verificacion[$linea->id] = ['cantidad_verificada' => $linea->cantidad];
            }
        }

        return app(RecepcionDeCompra::class)->recepcionar($compra->fresh(), $verificacion);
    }

    // ---- Proveedores -------------------------------------------------------

    public function test_el_listado_resume_lo_invertido_en_cada_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Importadora Andina']);

        $this->recepcionar($this->compraPendiente($proveedor));

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
        $this->compraPendiente($proveedor);

        Sanctum::actingAs($this->admin());

        $fila = $this->getJson('/api/v1/proveedores')->assertOk()->json('data.0');

        $this->assertSame(0, $fila['compras']['total']);
        $this->assertEquals(0, $fila['compras']['invertido']);
    }

    public function test_la_ficha_del_proveedor_trae_sus_ultimas_compras(): void
    {
        $proveedor = Proveedor::factory()->create();
        $this->recepcionar($this->compraPendiente($proveedor));

        Sanctum::actingAs($this->admin());

        $ficha = $this->getJson("/api/v1/proveedores/{$proveedor->id}")->assertOk()->json('data');

        $this->assertCount(1, $ficha['ultimas_compras']);
        $this->assertSame('recepcionada', $ficha['ultimas_compras'][0]['estado']);
    }

    public function test_sin_permiso_de_compras_no_viaja_lo_invertido(): void
    {
        $proveedor = Proveedor::factory()->create();
        $this->recepcionar($this->compraPendiente($proveedor));

        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->syncPermissions(['proveedores.ver']);

        Sanctum::actingAs($usuario);

        $fila = $this->getJson('/api/v1/proveedores')->assertOk()->json('data.0');

        // Queda la ficha de contacto, que es para lo que se abre en el almacén.
        $this->assertArrayNotHasKey('compras', $fila);
        $this->assertNotNull($fila['nombre']);
    }

    // ---- Compras -----------------------------------------------------------

    public function test_el_listado_distingue_pendientes_de_recepcionadas(): void
    {
        $this->compraPendiente();
        $this->recepcionar($this->compraPendiente());

        Sanctum::actingAs($this->admin());

        $porEstado = collect(
            $this->getJson('/api/v1/compras')->assertOk()->json('data')
        )->keyBy('estado');

        $this->assertTrue($porEstado['pendiente']['es_pendiente']);
        // En un pendiente todavía no existen unidades: se crean al recepcionar.
        $this->assertSame(0, $porEstado['pendiente']['unidades']);

        $this->assertTrue($porEstado['recepcionada']['esta_recepcionada']);
        $this->assertSame(3, $porEstado['recepcionada']['unidades']);
        $this->assertNotNull($porEstado['recepcionada']['recepcionada_en']);
    }

    public function test_el_listado_filtra_por_proveedor_y_por_estado(): void
    {
        $uno = Proveedor::factory()->create();
        $otro = Proveedor::factory()->create();

        $this->compraPendiente($uno);
        $this->recepcionar($this->compraPendiente($otro));

        Sanctum::actingAs($this->admin());

        $delUno = $this->getJson("/api/v1/compras?proveedor_id={$uno->id}")->assertOk()->json('data');
        $this->assertCount(1, $delUno);
        $this->assertSame('pendiente', $delUno[0]['estado']);

        $recepcionadas = $this->getJson('/api/v1/compras?estado=recepcionada')->assertOk()->json('data');
        $this->assertCount(1, $recepcionadas);
        $this->assertSame($otro->id, $recepcionadas[0]['proveedor_id']);
    }

    public function test_la_ficha_desglosa_los_gastos_y_el_costo_real(): void
    {
        $compra = $this->compraPendiente(cantidad: 3, costo: 1000, flete: 300, precioVenta: 1800);
        $this->recepcionar($compra);

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

    public function test_en_un_pendiente_la_linea_no_finge_tener_costo_real(): void
    {
        $compra = $this->compraPendiente();

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
        $compra = $this->compraPendiente();
        $this->recepcionar($compra);

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
        $compra = $this->compraPendiente();
        $this->recepcionar($compra);

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

    public function test_la_api_registra_una_compra_pendiente(): void
    {
        $proveedor = Proveedor::factory()->create();
        $producto = Producto::factory()->create(['activo' => true, 'precio_venta' => 1800]);

        Sanctum::actingAs($this->admin());

        $respuesta = $this->postJson('/api/v1/compras', [
            'proveedor_id' => $proveedor->id,
            'numero_factura' => 'F-0042',
            'fecha_compra' => now()->toDateString(),
            'total' => 3300,
            'lineas' => [
                ['producto_id' => $producto->id, 'cantidad' => 3, 'costo_total' => 3300],
            ],
        ])->assertCreated();

        $this->assertSame('pendiente', $respuesta->json('data.estado'));
        $this->assertEquals(3300, $respuesta->json('data.total'));
        // Nace sin unidades: se generan al recepcionar.
        $this->assertSame(0, $respuesta->json('data.unidades'));

        $compra = Compra::first();
        $this->assertSame('pendiente', $compra->estado);
        $this->assertSame('1100.00', $compra->detalles()->first()->costo_unitario);
    }

    public function test_la_api_rechaza_una_compra_que_no_cuadra(): void
    {
        $proveedor = Proveedor::factory()->create();
        $producto = Producto::factory()->create(['activo' => true]);

        Sanctum::actingAs($this->admin());

        // Total 3300 pero líneas que suman 3000: queda un costo sin cargar.
        $this->postJson('/api/v1/compras', [
            'proveedor_id' => $proveedor->id,
            'fecha_compra' => now()->toDateString(),
            'total' => 3300,
            'lineas' => [
                ['producto_id' => $producto->id, 'cantidad' => 3, 'costo_total' => 3000],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, Compra::count());
    }

    public function test_la_api_rechaza_un_producto_repetido_en_dos_lineas(): void
    {
        $proveedor = Proveedor::factory()->create();
        $producto = Producto::factory()->create(['activo' => true]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/compras', [
            'proveedor_id' => $proveedor->id,
            'fecha_compra' => now()->toDateString(),
            'total' => 2000,
            'lineas' => [
                ['producto_id' => $producto->id, 'cantidad' => 1, 'costo_total' => 1000],
                ['producto_id' => $producto->id, 'cantidad' => 1, 'costo_total' => 1000],
            ],
        ])->assertStatus(422);

        $this->assertSame(0, Compra::count());
    }

    public function test_la_api_permite_eliminar_una_compra_pendiente(): void
    {
        $compra = $this->compraPendiente();

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/compras/{$compra->id}")->assertOk();

        $this->assertSoftDeleted('compras', ['id' => $compra->id]);
    }

    public function test_la_api_no_elimina_una_compra_recepcionada(): void
    {
        $compra = $this->compraPendiente();
        $this->recepcionar($compra);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/compras/{$compra->id}")->assertStatus(422);

        $this->assertDatabaseHas('compras', ['id' => $compra->id, 'deleted_at' => null]);
    }

    public function test_la_api_no_elimina_una_compra_parcialmente_recepcionada(): void
    {
        // La compra sigue pendiente, pero sus unidades ya están en el almacén:
        // borrarla las dejaría huérfanas.
        $compra = $this->compraPendiente(cantidad: 5);

        $linea = $compra->detalles()->first();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'cantidad_verificada' => 3],
            ],
        ])->assertOk();

        $this->assertSame('pendiente', $compra->refresh()->estado);

        $this->deleteJson("/api/v1/compras/{$compra->id}")->assertStatus(422);

        $this->assertDatabaseHas('compras', ['id' => $compra->id, 'deleted_at' => null]);
        $this->assertSame(3, $compra->unidades()->count());
    }

    public function test_la_api_permite_recepcionar_compras_pendientes(): void
    {
        $compra = $this->compraPendiente();

        $linea = $compra->detalles()->first();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'cantidad_verificada' => $linea->cantidad],
            ],
        ])
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'estado'],
            ]);

        $compra->refresh();
        $this->assertEquals('recepcionada', $compra->estado);
    }

    public function test_la_api_recepciona_los_seriales_de_productos_con_serial(): void
    {
        $compra = $this->compraPendiente(cantidad: 2, tieneSerial: true);

        $linea = $compra->detalles()->first();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'seriales' => ['SN-A1', 'SN-A2']],
            ],
        ])->assertOk();

        $compra->refresh();
        $this->assertEquals('recepcionada', $compra->estado);

        $seriales = $compra->unidades()->orderBy('id')->pluck('serial')->all();

        $this->assertSame(['SN-A1', 'SN-A2'], $seriales);
    }

    public function test_la_api_rechaza_una_linea_sin_verificar(): void
    {
        $compra = $this->compraPendiente();

        Sanctum::actingAs($this->admin());

        // Se manda una línea que no existe: nada debe recepcionarse.
        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => 999999, 'cantidad_verificada' => 1],
            ],
        ])->assertStatus(422);

        $compra->refresh();
        $this->assertEquals('pendiente', $compra->estado);
    }

    public function test_la_recepcion_puede_ser_parcial(): void
    {
        // La mercadería puede llegar por tandas: 3 aparatos pedidos, hoy solo
        // entra 1. Se registran sus seriales y la compra sigue pendiente hasta
        // completar el lote.
        $compra = $this->compraPendiente(cantidad: 3, tieneSerial: true);

        $linea = $compra->detalles()->first();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'seriales' => ['SN-A1']],
            ],
        ])->assertOk();

        $compra->refresh();
        $this->assertEquals('pendiente', $compra->estado);
        $this->assertSame(1, $compra->unidades()->count());
        $this->assertSame('SN-A1', $compra->unidades()->first()->serial);

        // La segunda tanda completa el lote y recién ahí la compra se
        // recepciona.
        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'seriales' => ['SN-A2', 'SN-A3']],
            ],
        ])->assertOk();

        $compra->refresh();
        $this->assertEquals('recepcionada', $compra->estado);
        $this->assertSame(3, $compra->unidades()->count());
    }

    public function test_la_recepcion_parcial_rechaza_mas_seriales_de_los_que_faltan(): void
    {
        $compra = $this->compraPendiente(cantidad: 3, tieneSerial: true);

        $linea = $compra->detalles()->first();

        Sanctum::actingAs($this->admin());

        // Hoy entra 1 de 3.
        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'seriales' => ['SN-A1']],
            ],
        ])->assertOk();

        // Quedan 2 por verificar: mandar 3 de una vez es imposible.
        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'seriales' => ['SN-B1', 'SN-B2', 'SN-B3']],
            ],
        ])->assertStatus(422);

        $compra->refresh();
        $this->assertSame(1, $compra->unidades()->count());
    }

    public function test_la_recepcion_parcial_de_cantidad(): void
    {
        // Producto sin serial: 11 pedidos, hoy entran 7. La compra queda
        // pendiente con 4 por llegar, y los 7 ya están en el stock.
        $compra = $this->compraPendiente(cantidad: 11);

        $linea = $compra->detalles()->first();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'cantidad_verificada' => 7],
            ],
        ])->assertOk();

        $compra->refresh();
        $this->assertEquals('pendiente', $compra->estado);
        $this->assertSame(7, $compra->unidades()->count());

        // Llegan los 4 restantes: se recepciona.
        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'cantidad_verificada' => 4],
            ],
        ])->assertOk();

        $compra->refresh();
        $this->assertEquals('recepcionada', $compra->estado);
        $this->assertSame(11, $compra->unidades()->count());
    }

    public function test_la_recepcion_parcial_no_pasa_de_lo_pedido(): void
    {
        $compra = $this->compraPendiente(cantidad: 5);

        $linea = $compra->detalles()->first();

        Sanctum::actingAs($this->admin());

        // Marcar más unidades de las pedidas es imposible.
        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'cantidad_verificada' => 9],
            ],
        ])->assertStatus(422);

        $compra->refresh();
        $this->assertSame(0, $compra->unidades()->count());
    }

    public function test_la_api_rechaza_recepcionar_compra_no_pendiente(): void
    {
        $compra = $this->compraPendiente();
        $compra->update(['estado' => 'recepcionada']);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar")
            ->assertStatus(422);
    }

    public function test_recepcionar_compras_requiere_permiso(): void
    {
        $compra = $this->compraPendiente();
        $vendedor = $this->vendedor();

        Sanctum::actingAs($vendedor);

        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar")
            ->assertForbidden();
    }

    // ---- Edición de una compra pendiente -----------------------------------

    public function test_la_api_edita_una_compra_pendiente(): void
    {
        $compra = $this->compraPendiente(cantidad: 3, costo: 1000, flete: 300);
        $otroProveedor = Proveedor::factory()->create();
        $otroProducto = Producto::factory()->create(['activo' => true, 'precio_venta' => 900]);

        Sanctum::actingAs($this->admin());

        // Cambia proveedor, factura y líneas: 5 × 600 = 3000 de detalle.
        $respuesta = $this->postJson("/api/v1/compras/{$compra->id}", [
            'proveedor_id' => $otroProveedor->id,
            'numero_factura' => 'F-NUEVA',
            'fecha_compra' => now()->toDateString(),
            'total' => 3000,
            'lineas' => [
                ['producto_id' => $otroProducto->id, 'cantidad' => 5, 'costo_total' => 3000],
            ],
        ])->assertOk();

        $compra->refresh();

        $this->assertSame('pendiente', $compra->estado);
        $this->assertSame($otroProveedor->id, $compra->proveedor_id);
        $this->assertSame('F-NUEVA', $compra->numero_factura);
        $this->assertEquals(3000, $compra->total);
        $this->assertEquals(3000, $respuesta->json('data.total'));
        $this->assertCount(1, $compra->detalles);
        $this->assertSame($otroProducto->id, $compra->detalles()->first()->producto_id);
        // El código no cambia: es la misma orden corregida.
        $this->assertNotNull($respuesta->json('data.codigo'));
    }

    public function test_la_api_no_edita_una_compra_recepcionada(): void
    {
        $compra = $this->compraPendiente();
        $this->recepcionar($compra);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/compras/{$compra->id}", [
            'proveedor_id' => Proveedor::factory()->create()->id,
            'fecha_compra' => now()->toDateString(),
            'total' => 1000,
            'lineas' => [['producto_id' => Producto::factory()->create()->id, 'cantidad' => 1, 'costo_total' => 1000]],
        ])->assertStatus(422);
    }

    public function test_la_api_no_edita_una_compra_con_pagos(): void
    {
        $compra = $this->compraPendiente();
        $compra->pagos()->create([
            'user_id' => $this->admin()->id,
            'monto' => 500,
            'fecha' => now()->toDateString(),
        ]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/compras/{$compra->id}", [
            'proveedor_id' => $compra->proveedor_id,
            'fecha_compra' => now()->toDateString(),
            'total' => $compra->total,
            'lineas' => [[
                'producto_id' => $compra->detalles()->first()->producto_id,
                'cantidad' => 1,
                'costo_total' => (float) $compra->total,
            ]],
        ])->assertStatus(422);

        // El total y las líneas no se tocaron.
        $compra->refresh();
        $this->assertEquals(3300, $compra->total);
        $this->assertCount(1, $compra->detalles);
    }

    public function test_la_api_rechaza_editar_una_compra_que_no_cuadra(): void
    {
        $compra = $this->compraPendiente();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/compras/{$compra->id}", [
            'proveedor_id' => $compra->proveedor_id,
            'fecha_compra' => now()->toDateString(),
            'total' => 3000,
            'lineas' => [[
                'producto_id' => $compra->detalles()->first()->producto_id,
                'cantidad' => 1,
                'costo_total' => 2000,
            ]],
        ])->assertStatus(422);

        $compra->refresh();
        $this->assertEquals(3300, $compra->total);
    }

    public function test_editar_compras_requiere_permiso(): void
    {
        $compra = $this->compraPendiente();

        Sanctum::actingAs($this->vendedor());

        $this->postJson("/api/v1/compras/{$compra->id}", [
            'proveedor_id' => $compra->proveedor_id,
            'fecha_compra' => now()->toDateString(),
            'total' => 1000,
            'lineas' => [[
                'producto_id' => $compra->detalles()->first()->producto_id,
                'cantidad' => 1,
                'costo_total' => 1000,
            ]],
        ])->assertForbidden();
    }

    // ---- Pagos al proveedor -----------------------------------------------

    public function test_el_pago_suma_al_total_pagado_de_la_compra(): void
    {
        $compra = $this->compraPendiente();

        $linea = $compra->detalles()->first();

        Sanctum::actingAs($this->admin());

        // Primer pago parcial.
        $this->postJson("/api/v1/compras/{$compra->id}/pagos", [
            'monto' => 1500,
            'fecha' => now()->toDateString(),
        ])->assertCreated();

        $ficha = $this->getJson("/api/v1/compras/{$compra->id}")->assertOk()->json('data');

        $this->assertEquals(1500, $ficha['total_pagado']);
        $this->assertEquals(1800, $ficha['saldo_pendiente']);
        $this->assertFalse($ficha['esta_pagada']);
    }

    public function test_varios_pagos_completan_el_total(): void
    {
        $compra = $this->compraPendiente();

        Sanctum::actingAs($this->admin());

        foreach ([1500, 1800] as $monto) {
            $this->postJson("/api/v1/compras/{$compra->id}/pagos", [
                'monto' => $monto,
                'fecha' => now()->toDateString(),
            ])->assertCreated();
        }

        $ficha = $this->getJson("/api/v1/compras/{$compra->id}")->assertOk()->json('data');

        $this->assertEquals(3300, $ficha['total_pagado']);
        $this->assertEquals(0, $ficha['saldo_pendiente']);
        $this->assertTrue($ficha['esta_pagada']);
        $this->assertCount(2, $ficha['pagos']);
    }

    public function test_el_pago_guarda_el_boucher(): void
    {
        $compra = $this->compraPendiente();

        Sanctum::actingAs($this->admin());

        Storage::fake('public');

        $this->postJson("/api/v1/compras/{$compra->id}/pagos", [
            'monto' => 1000,
            'fecha' => now()->toDateString(),
            'imagen' => UploadedFile::fake()->image('boucher.jpg'),
        ])->assertCreated();

        $pago = PagoCompra::firstOrFail();

        $this->assertNotNull($pago->imagen);
        $this->assertStringStartsWith('comprobantes-compra/', $pago->imagen);
        Storage::disk('public')->assertExists($pago->imagen);
    }

    public function test_borrar_pago_quita_su_boucher(): void
    {
        $compra = $this->compraPendiente();

        $pago = $compra->pagos()->create([
            'user_id' => $this->admin()->id,
            'monto' => 1000,
            'fecha' => now()->toDateString(),
            'imagen' => 'comprobantes-compra/viejo.jpg',
        ]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/compras/{$compra->id}/pagos/{$pago->id}")->assertOk();

        $this->assertDatabaseMissing('compra_pagos', ['id' => $pago->id]);
    }

    public function test_los_pagos_requieren_permiso(): void
    {
        $compra = $this->compraPendiente();

        $usuario = User::factory()->create(['is_active' => true]);

        Sanctum::actingAs($usuario);

        $this->getJson("/api/v1/compras/{$compra->id}/pagos")->assertForbidden();
        $this->getJson('/api/v1/compras/pagos')->assertForbidden();
    }

    // ---- Historial de pagos -----------------------------------------------

    public function test_el_historial_de_pagos_filtra_por_rango(): void
    {
        $hoy = $this->compraPendiente();
        $hoy->pagos()->create([
            'user_id' => $this->admin()->id,
            'monto' => 1500,
            'fecha' => now()->toDateString(),
        ]);

        $mesPasado = $this->compraPendiente();
        $mesPasado->pagos()->create([
            'user_id' => $this->admin()->id,
            'monto' => 2000,
            'fecha' => now()->subMonths(2)->toDateString(),
        ]);

        Sanctum::actingAs($this->admin());

        $deHoy = $this->getJson('/api/v1/compras/pagos?rango=hoy')->assertOk();
        $this->assertCount(1, $deHoy->json('data'));
        $this->assertEquals(1500, $deHoy->json('data.0.monto'));
        $this->assertEquals(1500, $deHoy->json('meta.total'));

        // El rango «mes» también trae el de hoy.
        $delMes = $this->getJson('/api/v1/compras/pagos?rango=mes')->assertOk();
        $this->assertCount(1, $delMes->json('data'));

        // Sin filtro, los dos.
        $todos = $this->getJson('/api/v1/compras/pagos')->assertOk();
        $this->assertCount(2, $todos->json('data'));
        $this->assertEquals(3500, $todos->json('meta.total'));
    }

    public function test_el_historial_puede_buscar_por_compra_o_proveedor(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Importadora Alfa']);
        $compra = $this->compraPendiente($proveedor);
        $compra->pagos()->create([
            'user_id' => $this->admin()->id,
            'monto' => 500,
            'fecha' => now()->toDateString(),
        ]);

        Sanctum::actingAs($this->admin());

        $porProveedor = $this->getJson('/api/v1/compras/pagos?buscar=alfa')->assertOk();
        $this->assertCount(1, $porProveedor->json('data'));
        $this->assertSame('Importadora Alfa', $porProveedor->json('data.0.proveedor'));
        $this->assertSame($compra->codigo, $porProveedor->json('data.0.compra_codigo'));

        $porCodigo = $this->getJson('/api/v1/compras/pagos?buscar='.$compra->codigo)->assertOk();
        $this->assertCount(1, $porCodigo->json('data'));
    }
}
