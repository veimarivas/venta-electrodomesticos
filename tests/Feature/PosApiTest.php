<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\QrCobro;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Punto de venta desde la app: búsqueda por escáner y cobro.
 */
class PosApiTest extends TestCase
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

    private function unidadEnStock(
        float $precio = 1500,
        float $descuentoMaximo = 0,
        ?string $serial = null,
    ): Unidad {
        return Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => $precio,
                'descuento_maximo' => $descuentoMaximo,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => $precio / 2,
            'precio_venta' => $precio,
            'serial' => $serial,
        ]);
    }

    // ---- Búsqueda / escáner ------------------------------------------------

    public function test_el_escaner_marca_la_coincidencia_exacta_del_serial(): void
    {
        $unidad = $this->unidadEnStock(serial: 'SN-ABC-12345');
        $this->unidadEnStock(serial: 'SN-ABC-99999');

        Sanctum::actingAs($this->vendedor());

        $respuesta = $this->getJson('/api/v1/pos/buscar?termino=SN-ABC-12345')->assertOk();

        // Con la coincidencia exacta marcada, la app lo agrega sola al carrito
        // en vez de pedir que se elija de una lista.
        $this->assertSame($unidad->id, $respuesta->json('meta.exacto'));
        $this->assertSame($unidad->id, $respuesta->json('data.0.unidad_id'));
    }

    public function test_el_escaner_marca_la_coincidencia_exacta_del_codigo_interno(): void
    {
        // En el mostrador se escanea lo que haya delante: la etiqueta que
        // imprime el panel (código interno) o el código del fabricante
        // (serial). Las dos tienen que agregar el aparato al carrito igual.
        $unidad = $this->unidadEnStock();

        Sanctum::actingAs($this->vendedor());

        $respuesta = $this->getJson(
            '/api/v1/pos/buscar?escaneado=1&termino='.urlencode($unidad->codigo_interno)
        )->assertOk();

        $this->assertSame($unidad->id, $respuesta->json('meta.exacto'));
        $this->assertSame($unidad->id, $respuesta->json('data.0.unidad_id'));
        $this->assertNull($respuesta->json('meta.diagnostico'));
    }

    public function test_la_coincidencia_exacta_no_depende_del_corte_de_la_lista(): void
    {
        // La lista se corta en 12 resultados ordenados por código interno. Si
        // la coincidencia exacta se buscara dentro de ese corte, escanear un
        // aparato cuyo código empieza por una letra alta lo dejaría fuera y la
        // venta no lo reconocería: el resultado del escáner dependería de
        // cuántos aparatos parecidos hubiese en stock ese día.
        for ($i = 1; $i <= 14; $i++) {
            $this->unidadEnStock(serial: 'SN-100'.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }

        $unidad = $this->unidadEnStock(serial: 'SN-100');
        // Con este código interno queda el último del orden alfabético.
        $unidad->update(['codigo_interno' => 'ZZZ-ULTIMO']);

        Sanctum::actingAs($this->vendedor());

        $respuesta = $this->getJson('/api/v1/pos/buscar?escaneado=1&termino=SN-100')->assertOk();

        $this->assertSame($unidad->id, $respuesta->json('meta.exacto'));
        // Y encabeza la lista: es el aparato que se está vendiendo.
        $this->assertSame($unidad->id, $respuesta->json('data.0.unidad_id'));
        $this->assertNull($respuesta->json('meta.diagnostico'));
    }

    public function test_el_aparato_escaneado_no_sale_repetido_en_la_lista(): void
    {
        $unidad = $this->unidadEnStock(serial: 'SN-ABC-12345');

        Sanctum::actingAs($this->vendedor());

        $respuesta = $this->getJson('/api/v1/pos/buscar?termino=SN-ABC-12345')->assertOk();

        $this->assertSame(1, $respuesta->json('meta.total'));
        $this->assertSame(
            [$unidad->id],
            array_column($respuesta->json('data'), 'unidad_id')
        );
    }

    public function test_un_codigo_escaneado_de_un_aparato_vendido_dice_en_que_venta_salio(): void
    {
        $unidad = $this->unidadEnStock(serial: 'SN-VENDIDO-1');
        $venta = Venta::factory()->create(['estado' => 'completada']);

        $unidad->update(['estado' => 'vendido']);
        $unidad->ventaDetalle()->create([
            'venta_id' => $venta->id,
            'producto_id' => $unidad->producto_id,
            'unidad_vendida_id' => $unidad->id,
            'precio_unitario' => $unidad->precio_venta,
            'costo_unitario' => $unidad->costo_unitario,
            'descuento' => 0,
            'ganancia' => 0,
        ]);

        Sanctum::actingAs($this->vendedor());

        $respuesta = $this
            ->getJson('/api/v1/pos/buscar?termino=SN-VENDIDO-1&escaneado=1')
            ->assertOk();

        // Sin esto, el mostrador ve una lista vacía y no puede distinguir «ya
        // se vendió» de «este aparato no existe».
        $this->assertSame('no_vendible', $respuesta->json('meta.diagnostico.tipo'));
        $this->assertSame($venta->id, $respuesta->json('meta.diagnostico.venta_id'));
        $this->assertStringContainsString(
            $venta->codigo,
            $respuesta->json('meta.diagnostico.detalle'),
        );
    }

    public function test_un_codigo_escaneado_que_no_esta_en_el_inventario_lo_dice(): void
    {
        $this->unidadEnStock(serial: 'SN-ABC-12345');

        Sanctum::actingAs($this->vendedor());

        $respuesta = $this
            ->getJson('/api/v1/pos/buscar?termino=CODIGO-DE-OTRA-TIENDA&escaneado=1')
            ->assertOk();

        $this->assertSame('desconocido', $respuesta->json('meta.diagnostico.tipo'));
        $this->assertSame([], $respuesta->json('data'));
    }

    public function test_escribiendo_a_mano_no_se_devuelve_diagnostico(): void
    {
        $this->unidadEnStock(serial: 'SN-ABC-12345');

        Sanctum::actingAs($this->vendedor());

        // Quien teclea media palabra no espera que le digan que «no existe en
        // el inventario»: el diagnóstico es solo para lo que leyó la cámara.
        $respuesta = $this->getJson('/api/v1/pos/buscar?termino=zzz')->assertOk();

        $this->assertNull($respuesta->json('meta.diagnostico'));
    }

    public function test_la_busqueda_parcial_no_inventa_una_coincidencia_exacta(): void
    {
        $this->unidadEnStock(serial: 'SN-ABC-12345');

        Sanctum::actingAs($this->vendedor());

        $respuesta = $this->getJson('/api/v1/pos/buscar?termino=SN-ABC')->assertOk();

        $this->assertNull($respuesta->json('meta.exacto'));
        $this->assertSame(1, $respuesta->json('meta.total'));
    }

    public function test_el_escaner_solo_ofrece_aparatos_vendibles(): void
    {
        $vendido = $this->unidadEnStock(serial: 'SN-VENDIDO');
        $vendido->update(['estado' => 'vendido']);

        Sanctum::actingAs($this->vendedor());

        $respuesta = $this->getJson('/api/v1/pos/buscar?termino=SN-VENDIDO')->assertOk();

        $this->assertSame(0, $respuesta->json('meta.total'));
    }

    public function test_la_busqueda_devuelve_el_precio_y_su_tope_de_rebaja(): void
    {
        $this->unidadEnStock(precio: 400, descuentoMaximo: 50, serial: 'SN-1');

        Sanctum::actingAs($this->vendedor());

        $aparato = $this->getJson('/api/v1/pos/buscar?termino=SN-1')->assertOk()->json('data.0');

        $this->assertEquals(400, $aparato['precio_venta']);
        $this->assertEquals(50, $aparato['descuento_maximo']);
        // Lo que el mostrador necesita saber sin hacer la resta a mano.
        $this->assertEquals(350, $aparato['precio_minimo']);
    }

    // ---- Cobro -------------------------------------------------------------

    public function test_cobra_en_efectivo_y_descuenta_el_stock(): void
    {
        $unidad = $this->unidadEnStock(1500);

        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 1500]],
            'metodo_pago' => 'efectivo',
        ])->assertCreated();

        $venta = Venta::firstOrFail();

        $this->assertEquals(1500, $venta->total);
        $this->assertSame('vendido', $unidad->fresh()->estado);
    }

    public function test_el_precio_pactado_se_registra_como_descuento(): void
    {
        // El caso del mostrador: lista 400, se pacta 350, quedan 50 de rebaja.
        $unidad = $this->unidadEnStock(precio: 400, descuentoMaximo: 100);

        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 350]],
            'metodo_pago' => 'efectivo',
        ])->assertCreated();

        $detalle = Venta::firstOrFail()->detalles()->firstOrFail();

        // El precio de lista lo pone el servidor desde la unidad, no la app.
        $this->assertSame('400.00', $detalle->precio_unitario);
        $this->assertSame('50.00', $detalle->descuento);
    }

    public function test_no_se_puede_cobrar_por_encima_del_precio_de_referencia(): void
    {
        $unidad = $this->unidadEnStock(precio: 400, descuentoMaximo: 100);

        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 500]],
            'metodo_pago' => 'efectivo',
        ])->assertStatus(422);

        $this->assertSame(0, Venta::count());
    }

    public function test_el_tope_de_descuento_del_producto_se_respeta(): void
    {
        // El teléfono no puede saltarse la regla: la comprueba RegistroDeVenta.
        $unidad = $this->unidadEnStock(precio: 400, descuentoMaximo: 50);

        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 300]],
            'metodo_pago' => 'efectivo',
        ])->assertStatus(422);

        $this->assertSame(0, Venta::count());
    }

    public function test_no_se_puede_vender_dos_veces_el_mismo_aparato(): void
    {
        $unidad = $this->unidadEnStock();

        Sanctum::actingAs($this->vendedor());

        $cuerpo = [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 1500]],
            'metodo_pago' => 'efectivo',
        ];

        $this->postJson('/api/v1/pos/cobrar', $cuerpo)->assertCreated();
        $this->postJson('/api/v1/pos/cobrar', $cuerpo)->assertStatus(422);

        $this->assertSame(1, Venta::count());
    }

    public function test_no_se_puede_cobrar_con_un_metodo_que_el_mostrador_retiro(): void
    {
        $unidad = $this->unidadEnStock();

        Sanctum::actingAs($this->vendedor());

        // `tarjeta` y `transferencia` siguen en METODOS_PAGO para que el
        // histórico muestre ventas viejas cobradas así, pero el POS ya no los
        // ofrece. La API validaba contra esa lista larga y dejaba colarlos
        // desde el teléfono.
        foreach (['tarjeta', 'transferencia'] as $metodo) {
            $this->postJson('/api/v1/pos/cobrar', [
                'lineas' => [['unidad_id' => $unidad->id, 'precio' => 1500]],
                'metodo_pago' => $metodo,
            ])->assertStatus(422)->assertJsonValidationErrors('metodo_pago');
        }

        $this->assertSame(0, Venta::count());
        $this->assertSame('en_stock', $unidad->fresh()->estado);
    }

    public function test_cobrar_por_qr_exige_el_respaldo_del_pago(): void
    {
        $unidad = $this->unidadEnStock();
        $qr = QrCobro::factory()->create();

        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 1500]],
            'metodo_pago' => 'qr',
            'qr_cobro_id' => $qr->id,
        ])->assertStatus(422)->assertJsonValidationErrors('comprobante');

        $this->assertSame(0, Venta::count());
    }

    public function test_cobra_por_qr_con_la_foto_del_comprobante(): void
    {
        Storage::fake('public');

        $unidad = $this->unidadEnStock();
        $qr = QrCobro::factory()->create();

        Sanctum::actingAs($this->vendedor());

        $this->post('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 1500]],
            'metodo_pago' => 'qr',
            'qr_cobro_id' => $qr->id,
            'comprobante' => UploadedFile::fake()->image('respaldo.jpg'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $venta = Venta::firstOrFail();

        $this->assertSame($qr->id, $venta->qr_cobro_id);
        $this->assertEquals(1500, $venta->monto_qr);
        $this->assertEquals(0, $venta->monto_efectivo);
        Storage::disk('public')->assertExists($venta->comprobante_qr);
    }

    public function test_el_pago_mixto_tiene_que_cuadrar_con_el_total(): void
    {
        Storage::fake('public');

        $unidad = $this->unidadEnStock(1000);
        $qr = QrCobro::factory()->create();

        Sanctum::actingAs($this->vendedor());

        $cuerpo = fn (float $efectivo, float $porQr): array => [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 1000]],
            'metodo_pago' => 'mixto',
            'qr_cobro_id' => $qr->id,
            'monto_efectivo' => $efectivo,
            'monto_qr' => $porQr,
            'comprobante' => UploadedFile::fake()->image('respaldo.jpg'),
        ];

        $this->post('/api/v1/pos/cobrar', $cuerpo(400, 400), ['Accept' => 'application/json'])
            ->assertStatus(422);

        $this->assertSame(0, Venta::count());

        $this->post('/api/v1/pos/cobrar', $cuerpo(400, 600), ['Accept' => 'application/json'])
            ->assertCreated();

        $venta = Venta::firstOrFail();

        $this->assertEquals(400, $venta->monto_efectivo);
        $this->assertEquals(600, $venta->monto_qr);
    }

    public function test_un_cobro_rechazado_no_deja_el_comprobante_en_el_disco(): void
    {
        Storage::fake('public');

        $unidad = $this->unidadEnStock(1000);
        $qr = QrCobro::factory()->create();

        Sanctum::actingAs($this->vendedor());

        $this->post('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 1000]],
            'metodo_pago' => 'mixto',
            'qr_cobro_id' => $qr->id,
            'monto_efectivo' => 100,
            'monto_qr' => 100,
            'comprobante' => UploadedFile::fake()->image('respaldo.jpg'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        // La imagen se sube antes de la transacción; si la venta se rechaza hay
        // que recogerla, o el disco se llena de respaldos de nada.
        $this->assertEmpty(Storage::disk('public')->files('comprobantes-qr'));
    }

    public function test_la_venta_se_registra_con_su_cliente(): void
    {
        $unidad = $this->unidadEnStock();
        $cliente = Cliente::factory()->create();

        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 1500]],
            'metodo_pago' => 'efectivo',
            'cliente_id' => $cliente->id,
        ])->assertCreated();

        $this->assertSame($cliente->id, Venta::firstOrFail()->cliente_id);
    }

    // ---- Alta rápida de cliente --------------------------------------------

    public function test_registra_un_cliente_desde_el_mostrador(): void
    {
        Sanctum::actingAs($this->vendedor());

        $respuesta = $this->postJson('/api/v1/clientes', [
            'carnet' => '9876543',
            'nombres' => 'Rosa',
            'apellido_paterno' => 'Quispe',
            'celular' => '71234567',
        ])->assertCreated();

        $cliente = Cliente::firstOrFail();

        $this->assertSame('9876543', $cliente->persona->carnet);
        $this->assertSame($cliente->codigo, $respuesta->json('data.codigo'));
    }

    public function test_el_alta_rechaza_un_carnet_repetido(): void
    {
        Persona::factory()->create(['carnet' => '9876543']);

        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/clientes', [
            'carnet' => '9876543',
            'nombres' => 'Rosa',
            'apellido_paterno' => 'Quispe',
        ])->assertStatus(422)->assertJsonValidationErrors('carnet');
    }

    public function test_el_alta_exige_al_menos_un_apellido(): void
    {
        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/clientes', [
            'carnet' => '9876543',
            'nombres' => 'Rosa',
        ])->assertStatus(422);
    }

    // ---- Permisos ----------------------------------------------------------

    public function test_el_pos_exige_el_permiso_de_crear_ventas(): void
    {
        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->syncPermissions(['ventas.ver']);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/v1/pos/buscar?termino=algo')->assertForbidden();
        $this->postJson('/api/v1/pos/cobrar', [])->assertForbidden();
    }

    public function test_el_alta_de_clientes_exige_su_permiso(): void
    {
        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->syncPermissions(['clientes.ver']);

        Sanctum::actingAs($usuario);

        $this->postJson('/api/v1/clientes', [])->assertForbidden();
    }
}
