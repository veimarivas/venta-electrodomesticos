<?php

namespace Tests\Feature;

use App\Livewire\Creditos\Show;
use App\Models\Cliente;
use App\Models\Credito;
use App\Models\PagoCredito;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Support\ArqueoDeCaja;
use App\Support\CobroDeCuota;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Cobrar cuotas.
 *
 * La imputación va siempre de la cuota más antigua a la más nueva. Dejar
 * elegir cuál se paga permitiría saldar la de diciembre dejando viva la de
 * agosto, y la mora dejaría de significar nada.
 */
class CobranzaDeCuotasTest extends TestCase
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

    /** Crédito de 1200 en 4 cuotas de 300, sin inicial. */
    private function credito(float $precio = 1200, int $cuotas = 4, float $inicial = 0): Credito
    {
        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => $precio,
                'stock_minimo' => 0,
                'descuento_maximo' => 0,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => $precio / 2,
            'precio_venta' => $precio,
        ]);

        $venta = app(RegistroDeVenta::class)->registrar(
            lineas: [['unidad_id' => $unidad->id, 'precio_unitario' => $precio, 'descuento' => 0]],
            cabecera: [
                'cliente_id' => Cliente::factory()->create()->id,
                'metodo_pago' => 'credito',
                'credito' => [
                    'cuota_inicial' => $inicial,
                    'numero_cuotas' => $cuotas,
                    'primer_vencimiento' => today()->addMonth()->format('Y-m-d'),
                ],
            ],
            userId: $this->supervisor()->id,
        );

        return $venta->credito;
    }

    private function cobrar(Credito $credito, float|string $monto, array $datos = []): \Illuminate\Support\Collection
    {
        return app(CobroDeCuota::class)->cobrar(
            $credito,
            $monto,
            $datos === [] ? ['metodo_pago' => 'efectivo'] : $datos,
            $this->supervisor()->id,
        );
    }

    // ---- Imputación ---------------------------------------------------------

    public function test_el_pago_va_a_la_cuota_mas_antigua_pendiente(): void
    {
        $credito = $this->credito();

        $this->cobrar($credito, 300);

        $cuotas = $credito->refresh()->cuotas;

        $this->assertSame('300.00', $cuotas->first()->monto_pagado);
        $this->assertTrue($cuotas->first()->esta_pagada);
        $this->assertSame('0.00', $cuotas->get(1)->monto_pagado);
        $this->assertSame(90000, $credito->saldoEnCentavos());
    }

    public function test_un_pago_que_cubre_cuota_y_media_toca_dos_cuotas_con_un_solo_recibo(): void
    {
        $credito = $this->credito();

        // 450 = una cuota entera (300) y media de la siguiente.
        $pagos = $this->cobrar($credito, 450);

        $this->assertCount(2, $pagos);
        // Una sola entrega de dinero: mismo recibo, dos imputaciones. Una fila
        // única con el total dejaría sin respuesta qué cuota quedó saldada.
        $this->assertSame($pagos->first()->recibo, $pagos->last()->recibo);
        $this->assertSame('300.00', $pagos->first()->monto);
        $this->assertSame('150.00', $pagos->last()->monto);

        $cuotas = $credito->refresh()->cuotas;

        $this->assertTrue($cuotas->first()->esta_pagada);
        $this->assertSame('150.00', $cuotas->get(1)->monto_pagado);
        $this->assertFalse($cuotas->get(1)->esta_pagada);
        $this->assertSame('Pago parcial', $cuotas->get(1)->etiqueta_estado);
    }

    public function test_no_se_acepta_un_pago_mayor_que_el_saldo(): void
    {
        $credito = $this->credito();

        // Un cobro de más dejaría un saldo a favor que este sistema no lleva, y
        // un sobrante sin explicación en el cajón al cerrar.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('supera el saldo');

        $this->cobrar($credito, 1500);
    }

    public function test_pagar_todo_deja_el_credito_saldado(): void
    {
        $credito = $this->credito();

        $this->cobrar($credito, 1200);

        $this->assertSame('pagado', $credito->refresh()->estado);
        $this->assertSame(0, $credito->saldoEnCentavos());
    }

    public function test_a_un_credito_saldado_ya_no_se_le_cobra(): void
    {
        $credito = $this->credito();
        $this->cobrar($credito, 1200);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya está pagado');

        $this->cobrar($credito->refresh(), 100);
    }

    public function test_un_credito_anulado_no_admite_cobros(): void
    {
        $credito = $this->credito();
        $credito->update(['estado' => 'anulado']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('anulado');

        $this->cobrar($credito, 300);
    }

    public function test_el_pago_por_qr_exige_respaldo(): void
    {
        $credito = $this->credito();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('respaldo');

        $this->cobrar($credito, 300, ['metodo_pago' => 'qr']);
    }

    // ---- Caja ---------------------------------------------------------------

    public function test_las_cuotas_cobradas_en_efectivo_cuentan_en_el_arqueo(): void
    {
        $cajero = $this->supervisor();
        $credito = $this->credito();

        $caja = app(ArqueoDeCaja::class)->abrir($cajero->id, 100);

        $this->cobrar($credito, 300);

        // Fondo 100 + cuota 300. Sin contar la cobranza, el cierre saldría con
        // un sobrante de 300 que nadie sabría explicar.
        $this->assertSame(40000, app(ArqueoDeCaja::class)->esperadoEnCentavos($caja->refresh()));
    }

    public function test_la_cuota_pagada_por_qr_no_entra_al_cajon(): void
    {
        $cajero = $this->supervisor();
        $credito = $this->credito();

        $caja = app(ArqueoDeCaja::class)->abrir($cajero->id, 100);

        $this->cobrar($credito, 300, ['metodo_pago' => 'qr', 'comprobante_qr' => 'BNB-99231']);

        $this->assertSame(10000, app(ArqueoDeCaja::class)->esperadoEnCentavos($caja->refresh()));
    }

    public function test_el_pago_queda_atado_al_turno_abierto(): void
    {
        $cajero = $this->supervisor();
        $credito = $this->credito();

        $caja = app(ArqueoDeCaja::class)->abrir($cajero->id, 0);
        $this->cobrar($credito, 300);

        $this->assertSame($caja->id, PagoCredito::first()->caja_id);
    }

    // ---- La pantalla --------------------------------------------------------

    public function test_desde_la_ficha_se_registra_el_pago(): void
    {
        $credito = $this->credito();

        Livewire::actingAs($this->supervisor())
            ->test(Show::class, ['credito' => $credito])
            ->call('abrirCobro')
            // Se propone la cuota que toca: es lo que se cobra casi siempre.
            ->assertSet('monto', '300.00')
            ->call('cobrar')
            ->assertDispatched('cerrar-modal-cobrar-cuota');

        $this->assertSame(90000, $credito->refresh()->saldoEnCentavos());
    }

    public function test_sin_permiso_de_cobrar_la_ficha_no_deja_registrar_pagos(): void
    {
        $credito = $this->credito();

        // El componente es un endpoint invocable: esconder el botón no basta.
        $sinPermiso = User::factory()->create(['is_active' => true]);
        $sinPermiso->givePermissionTo('creditos.ver');

        Livewire::actingAs($sinPermiso)
            ->test(Show::class, ['credito' => $credito])
            ->set('monto', '300')
            ->call('cobrar')
            ->assertForbidden();
    }

    public function test_la_cartera_se_abre_y_lista_el_credito(): void
    {
        $credito = $this->credito();

        $this->actingAs($this->supervisor())
            ->get('/creditos')
            ->assertOk()
            ->assertSee($credito->cliente->codigo)
            ->assertSee($credito->venta->codigo);
    }

    public function test_la_ficha_muestra_el_plan_y_los_pagos(): void
    {
        $credito = $this->credito();
        $this->cobrar($credito, 300);

        $this->actingAs($this->supervisor())
            ->get(route('creditos.show', $credito))
            ->assertOk()
            ->assertSee('Plan de cuotas')
            ->assertSee('Pagos recibidos')
            ->assertSee(PagoCredito::first()->recibo);
    }

    public function test_sin_permiso_no_se_ve_la_cartera(): void
    {
        $this->credito();

        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get('/creditos')
            ->assertForbidden();
    }

    public function test_el_vendedor_no_puede_autorizar_una_venta_a_credito(): void
    {
        // Fiar es una decisión del dueño: el rol `vendedor` cobra cuotas y
        // consulta la cartera, pero no abre créditos nuevos.
        $vendedor = User::factory()->create(['is_active' => true])->syncRoles('vendedor');

        $this->assertFalse($vendedor->can('creditos.crear'));
        $this->assertTrue($vendedor->can('creditos.cobrar'));
        $this->assertTrue($vendedor->can('creditos.ver'));
    }

    public function test_la_cartera_separa_lo_vencido_de_lo_que_esta_al_dia(): void
    {
        $credito = $this->credito();

        // Se adelanta el reloj más allá del primer vencimiento.
        Carbon::setTestNow(today()->addMonths(2));

        $this->assertTrue($credito->refresh()->esta_en_mora);
        $this->assertSame(1, Credito::query()->enMora()->count());

        Carbon::setTestNow();

        $this->assertFalse($credito->refresh()->esta_en_mora);
        $this->assertSame(0, Credito::query()->enMora()->count());
    }
}
