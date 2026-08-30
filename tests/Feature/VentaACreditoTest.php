<?php

namespace Tests\Feature;

use App\Livewire\Ventas\Pos;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\ArqueoDeCaja;
use App\Support\ProrrateoDeGastos;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Venta a plazos: el plan de cuotas y lo que arrastra.
 *
 * Lo delicado no es partir un importe en trozos, sino que el crédito convive
 * con tres cosas que ya existían: el arqueo —a plazos solo entra la inicial al
 * cajón—, la anulación y la devolución de un aparato.
 */
class VentaACreditoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function supervisor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('supervisor');
    }

    private function cliente(): Cliente
    {
        return Cliente::factory()->create();
    }

    private function unidad(float $precio): Unidad
    {
        return Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => $precio,
                // Fijo: al azar dispararía el aviso de stock bajo, que aquí no
                // pinta nada.
                'stock_minimo' => 0,
                'descuento_maximo' => 0,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => $precio / 2,
            'precio_venta' => $precio,
        ]);
    }

    /**
     * @param  array<int, float>  $precios
     * @param  array<string, mixed>  $plan
     */
    private function venderACredito(array $precios, array $plan, ?Cliente $cliente = null, ?User $user = null): Venta
    {
        $cliente ??= $this->cliente();
        $user ??= $this->supervisor();

        return app(RegistroDeVenta::class)->registrar(
            lineas: array_map(fn (float $precio): array => [
                'unidad_id' => $this->unidad($precio)->id,
                'precio_unitario' => $precio,
                'descuento' => 0,
            ], $precios),
            cabecera: [
                'cliente_id' => $cliente->id,
                'metodo_pago' => 'credito',
                'credito' => $plan,
            ],
            userId: $user->id,
        );
    }

    // ---- El plan ------------------------------------------------------------

    public function test_una_venta_a_credito_arma_su_plan_de_cuotas(): void
    {
        $venta = $this->venderACredito([6000], [
            'cuota_inicial' => 1200,
            'numero_cuotas' => 6,
            'primer_vencimiento' => '2026-09-15',
        ]);

        $credito = $venta->credito;

        $this->assertNotNull($credito);
        $this->assertSame('1200.00', $credito->cuota_inicial);
        $this->assertSame('4800.00', $credito->total_financiado);
        $this->assertSame(6, $credito->numero_cuotas);
        $this->assertSame('vigente', $credito->estado);
        $this->assertCount(6, $credito->cuotas);
        $this->assertSame('800.00', $credito->cuotas->first()->monto);
    }

    public function test_las_cuotas_suman_exactamente_lo_financiado_aunque_no_divida(): void
    {
        // 1000 entre 3 da 333,33 y sobra un centavo. Repartir y redondear
        // dejaría 999,99: un centavo que nadie sabría a quién cobrar.
        $venta = $this->venderACredito([1000], [
            'cuota_inicial' => 0,
            'numero_cuotas' => 3,
            'primer_vencimiento' => '2026-09-15',
        ]);

        $cuotas = $venta->credito->cuotas;

        $suma = $cuotas->sum(fn ($cuota) => ProrrateoDeGastos::aCentavos($cuota->monto));

        $this->assertSame(100000, $suma);
        // El sobrante se carga en la primera, que es la más cara del plan.
        $this->assertSame('333.34', $cuotas->first()->monto);
        $this->assertSame('333.33', $cuotas->last()->monto);
    }

    public function test_los_vencimientos_caen_el_mismo_dia_de_cada_mes_sin_desbordar(): void
    {
        // Empezando el 31 de enero, `addMonths` daría el 3 de marzo —febrero no
        // tiene 31— y el cliente vería un vencimiento que no pactó.
        $venta = $this->venderACredito([300], [
            'cuota_inicial' => 0,
            'numero_cuotas' => 3,
            'primer_vencimiento' => '2027-01-31',
        ]);

        $fechas = $venta->credito->cuotas->map(fn ($c) => $c->vence_en->format('Y-m-d'))->all();

        $this->assertSame(['2027-01-31', '2027-02-28', '2027-03-31'], $fechas);
    }

    public function test_a_credito_hace_falta_un_cliente(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cliente identificado');

        app(RegistroDeVenta::class)->registrar(
            lineas: [['unidad_id' => $this->unidad(1000)->id, 'precio_unitario' => 1000, 'descuento' => 0]],
            cabecera: [
                'cliente_id' => null,
                'metodo_pago' => 'credito',
                'credito' => ['numero_cuotas' => 3, 'primer_vencimiento' => '2026-09-15'],
            ],
            userId: $this->supervisor()->id,
        );
    }

    public function test_una_inicial_que_cubre_toda_la_venta_no_es_un_credito(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cóbrala al contado');

        $this->venderACredito([1000], [
            'cuota_inicial' => 1000,
            'numero_cuotas' => 3,
            'primer_vencimiento' => '2026-09-15',
        ]);
    }

    public function test_la_primera_cuota_no_puede_vencer_antes_de_hoy(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('antes de hoy');

        $this->venderACredito([1000], [
            'cuota_inicial' => 0,
            'numero_cuotas' => 3,
            'primer_vencimiento' => today()->subDay()->format('Y-m-d'),
        ]);
    }

    public function test_si_el_plan_no_es_valido_no_queda_ni_la_venta(): void
    {
        $unidad = $this->unidad(1000);

        try {
            app(RegistroDeVenta::class)->registrar(
                lineas: [['unidad_id' => $unidad->id, 'precio_unitario' => 1000, 'descuento' => 0]],
                cabecera: [
                    'cliente_id' => $this->cliente()->id,
                    'metodo_pago' => 'credito',
                    // Sin cuotas: el plan no se puede armar.
                    'credito' => ['numero_cuotas' => 0, 'primer_vencimiento' => '2026-09-15'],
                ],
                userId: $this->supervisor()->id,
            );
        } catch (RuntimeException) {
            // Esperado.
        }

        // Todo o nada: una venta a crédito sin cuotas sería una deuda que nadie
        // sabe cobrar, y el aparato habría salido del stock por nada.
        $this->assertSame(0, Venta::count());
        $this->assertSame(0, Credito::count());
        $this->assertSame('en_stock', $unidad->refresh()->estado);
    }

    // ---- El punto de venta --------------------------------------------------

    public function test_desde_el_pos_se_cobra_a_plazos(): void
    {
        $unidad = $this->unidad(1200);
        $cliente = $this->cliente();

        Livewire::actingAs($this->supervisor())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->call('elegirCliente', $cliente->id)
            ->set('metodoPago', 'credito')
            ->set('cuotaInicial', '200')
            ->set('numeroCuotas', '5')
            ->set('primerVencimiento', today()->addMonth()->format('Y-m-d'))
            ->assertSet('metodoPago', 'credito')
            ->call('cobrar')
            ->assertDispatched('abrir-modal-venta-registrada');

        $credito = Credito::firstOrFail();

        $this->assertSame('200.00', $credito->cuota_inicial);
        $this->assertSame('1000.00', $credito->total_financiado);
        $this->assertCount(5, $credito->cuotas);
        $this->assertSame('200.00', $credito->cuotas->first()->monto);
    }

    public function test_el_pos_no_deja_fiar_sin_el_permiso_de_creditos(): void
    {
        $unidad = $this->unidad(1200);
        $cliente = $this->cliente();

        // El componente es un endpoint invocable: quitar la opción de la vista
        // no impide mandarla desde el navegador.
        $vendedor = User::factory()->create(['is_active' => true])->syncRoles('vendedor');

        Livewire::actingAs($vendedor)
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->call('elegirCliente', $cliente->id)
            ->set('metodoPago', 'credito')
            ->set('numeroCuotas', '5')
            ->set('primerVencimiento', today()->addMonth()->format('Y-m-d'))
            ->call('cobrar')
            ->assertForbidden();

        $this->assertSame(0, Credito::count());
    }

    // ---- Caja ---------------------------------------------------------------

    public function test_al_cajon_solo_entra_la_cuota_inicial(): void
    {
        $cajero = $this->supervisor();
        $caja = app(ArqueoDeCaja::class)->abrir($cajero->id, 100);

        $this->venderACredito([6000], [
            'cuota_inicial' => 1200,
            'numero_cuotas' => 6,
            'primer_vencimiento' => '2026-09-15',
        ], user: $cajero);

        // Fondo 100 + inicial 1200. Los 4800 financiados no son dinero cobrado:
        // contarlos inventaría un faltante de 4800 en cada cierre.
        $this->assertSame(130000, app(ArqueoDeCaja::class)->esperadoEnCentavos($caja->refresh()));
    }

    public function test_la_ficha_de_la_venta_enseña_el_plan_y_lleva_a_el(): void
    {
        $venta = $this->venderACredito([6000], [
            'cuota_inicial' => 1200,
            'numero_cuotas' => 6,
            'primer_vencimiento' => '2026-09-15',
        ]);

        $this->actingAs($this->supervisor())
            ->get(route('ventas.show', $venta))
            ->assertOk()
            ->assertSee('1.200,00')
            ->assertSee('Ver el plan de cuotas y sus pagos')
            ->assertSee(route('creditos.show', $venta->credito), false);
    }

    // ---- Anulación y devolución --------------------------------------------

    public function test_anular_la_venta_anula_el_credito(): void
    {
        $venta = $this->venderACredito([1000], [
            'cuota_inicial' => 0,
            'numero_cuotas' => 4,
            'primer_vencimiento' => '2026-09-15',
        ]);

        app(RegistroDeVenta::class)->anular($venta, 'El cliente se arrepintió');

        $this->assertSame('anulado', $venta->credito()->first()->estado);
    }

    public function test_devolver_un_aparato_baja_la_deuda_desde_la_ultima_cuota(): void
    {
        Carbon::setTestNow('2026-08-29 10:00:00');

        // Dos aparatos de 1000, sin inicial, en 4 cuotas de 500.
        $venta = $this->venderACredito([1000, 1000], [
            'cuota_inicial' => 0,
            'numero_cuotas' => 4,
            'primer_vencimiento' => '2026-09-15',
        ]);

        app(RegistroDeVenta::class)->devolver(
            $venta->detalles()->with(['venta', 'unidad'])->first(),
            'Salió defectuoso'
        );

        $credito = $venta->credito()->first();
        $montos = $credito->cuotas->map(fn ($c) => $c->monto)->all();

        // Se recortan las últimas: el cliente sigue pagando 500 al mes y
        // termina en la mitad del tiempo, en vez de recibir un plan nuevo con
        // otro importe cada mes.
        $this->assertSame(['500.00', '500.00', '0.00', '0.00'], $montos);
        $this->assertSame(100000, $credito->saldoEnCentavos());

        Carbon::setTestNow();
    }

    public function test_devolver_todos_los_aparatos_anula_el_credito(): void
    {
        $venta = $this->venderACredito([1000], [
            'cuota_inicial' => 0,
            'numero_cuotas' => 4,
            'primer_vencimiento' => '2026-09-15',
        ]);

        app(RegistroDeVenta::class)->devolver(
            $venta->detalles()->with(['venta', 'unidad'])->first(),
            'Salió defectuoso'
        );

        $this->assertSame('anulada', $venta->refresh()->estado);
        $this->assertSame('anulado', $venta->credito()->first()->estado);
    }
}
