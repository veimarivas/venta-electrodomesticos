<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Producto;
use App\Models\Reparacion;
use App\Models\Unidad;
use App\Models\User;
use App\Support\RegistroDeVenta;
use App\Support\ServicioTecnico;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Comprobantes que se le entregan al cliente: estado de cuenta del crédito y
 * orden de servicio técnico. Se generan al vuelo, desde el panel y desde la app.
 */
class ComprobantesTest extends TestCase
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

    /** Crédito de 1200 en 4 cuotas. */
    private function credito(): Credito
    {
        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => 1200,
                'stock_minimo' => 0,
                'descuento_maximo' => 0,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => 600,
            'precio_venta' => 1200,
        ]);

        $venta = app(RegistroDeVenta::class)->registrar(
            lineas: [['unidad_id' => $unidad->id, 'precio_unitario' => 1200, 'descuento' => 0]],
            cabecera: [
                'cliente_id' => Cliente::factory()->create()->id,
                'metodo_pago' => 'credito',
                'credito' => [
                    'cuota_inicial' => 0,
                    'numero_cuotas' => 4,
                    'primer_vencimiento' => Carbon::now()->addMonth()->format('Y-m-d'),
                ],
            ],
            userId: $this->admin()->id,
        );

        return $venta->credito;
    }

    private function reparacion(): Reparacion
    {
        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => 1000,
                'stock_minimo' => 0,
                'descuento_maximo' => 0,
                'meses_garantia' => 12,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => 500,
            'precio_venta' => 1000,
            'ingresado_en' => now()->subMonths(2),
        ]);

        return app(ServicioTecnico::class)->recibir(
            $unidad,
            ['falla_reportada' => 'No enciende'],
            $this->admin()->id,
        );
    }

    // ---- Panel --------------------------------------------------------------

    public function test_el_panel_emite_el_estado_de_cuenta(): void
    {
        $credito = $this->credito();

        $respuesta = $this->actingAs($this->admin())
            ->get(route('creditos.estado-cuenta', $credito));

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_el_panel_emite_la_orden_de_taller(): void
    {
        $reparacion = $this->reparacion();

        $respuesta = $this->actingAs($this->admin())
            ->get(route('reparaciones.orden', $reparacion));

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    // ---- API ----------------------------------------------------------------

    public function test_la_app_descarga_el_estado_de_cuenta(): void
    {
        $credito = $this->credito();

        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson("/api/v1/creditos/{$credito->id}/estado-cuenta");

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_la_app_descarga_la_orden_de_taller(): void
    {
        $reparacion = $this->reparacion();

        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson("/api/v1/reparaciones/{$reparacion->id}/comprobante");

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    // ---- Permisos -----------------------------------------------------------

    public function test_el_panel_exige_permiso_para_los_comprobantes(): void
    {
        $credito = $this->credito();
        $reparacion = $this->reparacion();
        $sinPermiso = User::factory()->create(['is_active' => true]);

        $this->actingAs($sinPermiso)
            ->get(route('creditos.estado-cuenta', $credito))
            ->assertForbidden();

        $this->actingAs($sinPermiso)
            ->get(route('reparaciones.orden', $reparacion))
            ->assertForbidden();
    }

    public function test_la_api_exige_permiso_para_los_comprobantes(): void
    {
        $credito = $this->credito();
        $reparacion = $this->reparacion();

        Sanctum::actingAs(User::factory()->create(['is_active' => true]));

        $this->getJson("/api/v1/creditos/{$credito->id}/estado-cuenta")->assertForbidden();
        $this->getJson("/api/v1/reparaciones/{$reparacion->id}/comprobante")->assertForbidden();
    }
}
