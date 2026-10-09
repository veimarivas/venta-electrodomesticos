<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\QrCobro;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Recibo de venta en PDF.
 */
class ReciboTest extends TestCase
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

    private function vender(
        float $precio = 1500,
        float $descuento = 0,
        array $cabecera = [],
    ): Venta {
        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => $precio,
                'descuento_maximo' => $descuento,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => $precio / 2,
            'precio_venta' => $precio,
        ]);

        return app(RegistroDeVenta::class)->registrar(
            [[
                'unidad_id' => $unidad->id,
                'precio_unitario' => (string) $precio,
                'descuento' => (string) $descuento,
            ]],
            $cabecera,
            $this->admin()->id,
        );
    }

    /** El HTML del recibo antes de pasar a PDF, para leer qué imprime. */
    private function htmlDelRecibo(Venta $venta): string
    {
        $venta->load(['detalles.unidad', 'detalles.producto', 'cliente.persona', 'user', 'qrCobro']);

        return view('backend.ventas.recibo', [
            'venta' => $venta,
            'metodosPago' => Venta::METODOS_PAGO,
            'tienda' => config('app.nombre_comercial'),
        ])->render();
    }

    public function test_el_recibo_lleva_el_nombre_comercial_y_solo_el_total(): void
    {
        // Lista 1500, se cobró 1400: el cliente no debe ver la lista ni la rebaja.
        $venta = $this->vender(1500, 100);
        $html = $this->htmlDelRecibo($venta);

        $this->assertStringContainsString('Electrogar', $html);
        $this->assertStringNotContainsString('Electrónica del Hogar', $html);
        $this->assertStringNotContainsString('Subtotal', $html);
        $this->assertStringNotContainsString('Descuento', $html);
        $this->assertStringNotContainsString('1.500,00', $html);
        // El precio final de la línea y el total.
        $this->assertStringContainsString('1.400,00', $html);
        $this->assertStringContainsString('TOTAL', $html);
    }

    public function test_el_recibo_junta_las_unidades_sin_serial(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Cable HDMI', 'precio_venta' => 50, 'tiene_serial' => false]);

        $unidades = Unidad::factory()->count(3)->create([
            'producto_id' => $producto->id,
            'estado' => 'en_stock',
            'serial' => null,
            'costo_unitario' => 20,
            'precio_venta' => 50,
        ]);

        $venta = app(RegistroDeVenta::class)->registrar(
            $unidades->map(fn (Unidad $u) => ['unidad_id' => $u->id, 'precio_unitario' => '50', 'descuento' => '0'])->all(),
            [],
            $this->admin()->id,
        );

        $html = $this->htmlDelRecibo($venta);

        // Una línea «3 × Cable HDMI» de 150, con el precio por unidad debajo.
        $this->assertSame(1, substr_count($html, 'Cable HDMI'));
        $this->assertMatchesRegularExpression('/3\s*×\s*Cable HDMI/u', $html);
        $this->assertStringContainsString('150,00', $html);
        $this->assertStringContainsString('P/U 50,00', $html);
    }

    public function test_descarga_el_recibo_en_pdf(): void
    {
        $venta = $this->vender();

        $respuesta = $this->actingAs($this->admin())
            ->get(route('ventas.recibo', $venta))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $respuesta->assertDownload("Recibo-{$venta->codigo}.pdf");

        // Un PDF empieza siempre por su firma; si la vista reventara, la
        // respuesta sería HTML de error con el mismo código 200.
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_el_recibo_de_un_pago_mixto_desglosa_el_cobro(): void
    {
        $qr = QrCobro::factory()->create();

        $venta = $this->vender(1000, 0, [
            'metodo_pago' => 'mixto',
            'qr_cobro_id' => $qr->id,
            'comprobante_qr' => 'comprobantes-qr/x.jpg',
            'monto_efectivo' => '400',
            'monto_qr' => '600',
        ]);

        // El PDF se genera sin reventar con el desglose; que los importes
        // salgan bien lo fija el test de RegistroDeVenta.
        $this->actingAs($this->admin())
            ->get(route('ventas.recibo', $venta))
            ->assertOk();

        $this->assertEquals(400, $venta->monto_efectivo);
        $this->assertEquals(600, $venta->monto_qr);
    }

    public function test_una_venta_anulada_tambien_tiene_recibo(): void
    {
        // Se puede reimprimir, pero el papel dice ANULADA: un recibo anulado
        // que parezca válido es un problema de caja.
        $venta = $this->vender();

        app(RegistroDeVenta::class)->anular($venta, 'El cliente devolvió el aparato.');

        $this->actingAs($this->admin())
            ->get(route('ventas.recibo', $venta->fresh()))
            ->assertOk();
    }

    public function test_el_recibo_exige_el_permiso_de_ver_ventas(): void
    {
        $venta = $this->vender();

        $usuario = User::factory()->create(['is_active' => true]);

        $this->actingAs($usuario)
            ->get(route('ventas.recibo', $venta))
            ->assertForbidden();
    }

    public function test_el_recibo_no_se_entrega_sin_sesion(): void
    {
        $venta = $this->vender();

        $this->get(route('ventas.recibo', $venta))->assertRedirect(route('login'));
    }
}
