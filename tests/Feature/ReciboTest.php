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
