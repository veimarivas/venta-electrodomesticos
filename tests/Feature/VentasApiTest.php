<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * API de anulación de ventas y recibo PDF.
 */
class VentasApiTest extends TestCase
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

    private function vender(float $precio = 1500): Venta
    {
        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => $precio,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => $precio / 2,
            'precio_venta' => $precio,
        ]);

        return app(RegistroDeVenta::class)->registrar(
            [[
                'unidad_id' => $unidad->id,
                'precio_unitario' => (string) $precio,
                'descuento' => '0',
            ]],
            [],
            $this->admin()->id,
        );
    }

    // ---- Anular venta -------------------------------------------------------

    public function test_anula_una_venta(): void
    {
        $venta = $this->vender();
        $admin = $this->admin();

        $respuesta = $this->actingAs($admin)
            ->postJson("/api/v1/ventas/{$venta->id}/anular", [
                'motivo' => 'El cliente se arrepintió.',
            ]);

        $respuesta->assertOk()
            ->assertJsonPath('data.estado', 'anulada')
            ->assertJsonStructure(['message', 'data']);

        // La unidad vuelve al stock.
        $venta->fresh(['detalles.unidad']);
        foreach ($venta->detalles as $detalle) {
            $this->assertSame('en_stock', $detalle->unidad->estado);
        }
    }

    public function test_anula_y_devuelve_unidades_al_stock(): void
    {
        $venta = $this->vender();
        $unidadId = $venta->detalles->first()->unidad_vendida_id;

        $this->actingAs($this->admin())
            ->postJson("/api/v1/ventas/{$venta->id}/anular", [
                'motivo' => 'Error de cobro.',
            ]);

        $unidad = \App\Models\Unidad::find($unidadId);
        $this->assertSame('en_stock', $unidad->estado);
        $this->assertNull($unidad->vendido_en);
    }

    public function test_no_permite_anular_una_venta_ya_anulada(): void
    {
        $venta = $this->vender();

        $admin = $this->admin();
        app(RegistroDeVenta::class)->anular($venta, 'Primera anulación.');

        $respuesta = $this->actingAs($admin)
            ->postJson("/api/v1/ventas/{$venta->id}/anular", [
                'motivo' => 'Segunda anulación.',
            ]);

        $respuesta->assertStatus(422)
            ->assertJsonPath('message', 'Esta venta ya estaba anulada.');
    }

    public function test_anular_exige_un_motivo(): void
    {
        $venta = $this->vender();

        $respuesta = $this->actingAs($this->admin())
            ->postJson("/api/v1/ventas/{$venta->id}/anular", []);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    public function test_el_motivo_debe_tener_al_menos_4_caracteres(): void
    {
        $venta = $this->vender();

        $respuesta = $this->actingAs($this->admin())
            ->postJson("/api/v1/ventas/{$venta->id}/anular", [
                'motivo' => 'Mal',
            ]);

        $respuesta->assertStatus(422)
            ->assertJsonValidationErrors('motivo');
    }

    public function test_el_vendedor_no_puede_anular(): void
    {
        $venta = $this->vender();

        $respuesta = $this->actingAs($this->vendedor())
            ->postJson("/api/v1/ventas/{$venta->id}/anular", [
                'motivo' => 'El vendedor no debería poder anular.',
            ]);

        $respuesta->assertForbidden();
    }

    public function test_se_necesita_sesion_para_anular(): void
    {
        $venta = $this->vender();

        $this->postJson("/api/v1/ventas/{$venta->id}/anular", [
            'motivo' => 'Sin sesión.',
        ])->assertUnauthorized();
    }

    // ---- Recibo PDF ---------------------------------------------------------

    public function test_obtiene_el_recibo_en_pdf(): void
    {
        $venta = $this->vender();

        $respuesta = $this->actingAs($this->admin())
            ->getJson("/api/v1/ventas/{$venta->id}/recibo");

        $respuesta->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_el_recibo_de_una_venta_anulada_muestra_el_estado(): void
    {
        $venta = $this->vender();
        app(RegistroDeVenta::class)->anular($venta, 'Cliente devolvió.');
        $venta = $venta->fresh();

        $this->actingAs($this->admin())
            ->getJson("/api/v1/ventas/{$venta->id}/recibo")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        // En los bytes del PDF no se puede buscar el texto: dompdf comprime
        // los flujos y escribe los caracteres como índices de glifo. Se
        // afirma sobre la plantilla que usa el controlador.
        $html = view('backend.ventas.recibo', [
            'venta' => $venta,
            'metodosPago' => Venta::METODOS_PAGO,
            'tienda' => config('app.name'),
        ])->render();

        $this->assertStringContainsString('ANULADA', $html);
    }

    public function test_el_recibo_exige_permiso_de_ver_ventas(): void
    {
        $venta = $this->vender();

        $usuario = User::factory()->create(['is_active' => true]);

        $this->actingAs($usuario)
            ->getJson("/api/v1/ventas/{$venta->id}/recibo")
            ->assertForbidden();
    }

    public function test_se_necesita_sesion_para_ver_recibo(): void
    {
        $venta = $this->vender();

        $this->getJson("/api/v1/ventas/{$venta->id}/recibo")
            ->assertUnauthorized();
    }
}
