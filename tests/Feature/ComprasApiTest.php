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
                $verificacion[$linea->id] = ['verificada' => true];
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

    public function test_la_api_no_permite_crear_compras(): void
    {
        // Crear compras se hace desde el panel web con la factura delante.
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/compras')->assertStatus(405);
    }

    public function test_la_api_permite_recepcionar_compras_pendientes(): void
    {
        $compra = $this->compraPendiente();

        $linea = $compra->detalles()->first();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'verificada' => true],
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
                ['linea_id' => 999999, 'verificada' => true],
            ],
        ])->assertStatus(422);

        $compra->refresh();
        $this->assertEquals('pendiente', $compra->estado);
    }

    public function test_la_api_rechaza_seriales_de_menos(): void
    {
        $compra = $this->compraPendiente(cantidad: 3, tieneSerial: true);

        $linea = $compra->detalles()->first();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/compras/{$compra->id}/recepcionar", [
            'lineas' => [
                ['linea_id' => $linea->id, 'seriales' => ['SN-A1']],
            ],
        ])->assertStatus(422);

        $compra->refresh();
        $this->assertEquals('pendiente', $compra->estado);
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
    }
}
