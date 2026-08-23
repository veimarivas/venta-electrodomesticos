<?php

namespace Tests\Feature;

use App\Models\Unidad;
use App\Models\Producto;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Support\GeneradorCodigoUnidad;
use App\Support\ProrrateoDeGastos;
use App\Support\RecepcionDeCompra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProrrateoYRecepcionTest extends TestCase
{
    use RefreshDatabase;

    // =======================================================================
    // Reparto sin centavos perdidos
    // =======================================================================

    public function test_el_reparto_suma_siempre_el_importe_original(): void
    {
        // El caso clásico: 100 entre tres partes iguales. 33.33 x 3 = 99.99,
        // falta un centavo. Aquí no puede faltar.
        $reparto = ProrrateoDeGastos::repartir(10000, [1, 1, 1]);

        $this->assertSame(10000, array_sum($reparto));
        $this->assertSame([3334, 3333, 3333], array_values($reparto));
    }

    public function test_el_reparto_es_proporcional_al_peso(): void
    {
        // Una línea que vale el doble carga el doble de flete.
        $reparto = ProrrateoDeGastos::repartir(30000, [20000, 10000]);

        $this->assertSame([20000, 10000], array_values($reparto));
        $this->assertSame(30000, array_sum($reparto));
    }

    public function test_el_reparto_cuadra_con_pesos_irregulares(): void
    {
        // Números elegidos para forzar restos en casi todas las partes.
        $pesos = [12345, 67891, 2, 999, 45678];
        $reparto = ProrrateoDeGastos::repartir(77777, $pesos);

        $this->assertSame(77777, array_sum($reparto));
        $this->assertCount(5, $reparto);
    }

    public function test_el_reparto_cuadra_en_muchos_escenarios_aleatorios(): void
    {
        // Barrido: cualquier combinación debe cuadrar al centavo.
        for ($caso = 0; $caso < 300; $caso++) {
            $partes = random_int(1, 9);
            $pesos = [];

            for ($i = 0; $i < $partes; $i++) {
                $pesos[] = random_int(0, 500000);
            }

            $importe = random_int(0, 1000000);

            $this->assertSame(
                $importe,
                array_sum(ProrrateoDeGastos::repartir($importe, $pesos)),
                'El reparto no cuadró con pesos '.implode(',', $pesos)." e importe {$importe}"
            );
        }
    }

    public function test_sin_pesos_reparte_en_partes_iguales(): void
    {
        // Todas las líneas en cero: el reparto proporcional no está definido.
        $reparto = ProrrateoDeGastos::repartir(1000, [0, 0, 0, 0]);

        $this->assertSame(1000, array_sum($reparto));
        $this->assertSame([250, 250, 250, 250], array_values($reparto));
    }

    public function test_la_conversion_a_centavos_no_usa_coma_flotante(): void
    {
        // 8.07 * 100 en float da 806.9999...; truncar daría 806.
        $this->assertSame(807, ProrrateoDeGastos::aCentavos('8.07'));
        $this->assertSame(1010, ProrrateoDeGastos::aCentavos(10.10));
        $this->assertSame(0, ProrrateoDeGastos::aCentavos(0));
        $this->assertSame('8.07', ProrrateoDeGastos::aDecimal(807));
    }

    // =======================================================================
    // Recepción
    // =======================================================================

    private function compraConLineas(array $lineas, array $cabecera = []): Compra
    {
        $compra = Compra::factory()->create(array_merge([
            'flete' => 0,
            'otros_gastos' => 0,
        ], $cabecera));

        $subtotal = 0;

        foreach ($lineas as $linea) {
            $importe = round($linea['costo_unitario'] * $linea['cantidad'], 2);
            $subtotal += $importe;

            CompraDetalle::factory()->create([
                'compra_id' => $compra->id,
                'producto_id' => $linea['producto_id'] ?? Producto::factory()->create()->id,
                'cantidad' => $linea['cantidad'],
                'costo_unitario' => $linea['costo_unitario'],
                'subtotal' => $importe,
                'precio_venta' => $linea['precio_venta'] ?? 0,
            ]);
        }

        $compra->update([
            'subtotal' => $subtotal,
            'total' => $subtotal + $compra->flete + $compra->otros_gastos,
        ]);

        return $compra->fresh();
    }

    public function test_recepcionar_genera_una_unidad_por_cada_pieza_comprada(): void
    {
        $compra = $this->compraConLineas([
            ['cantidad' => 3, 'costo_unitario' => 1000],
            ['cantidad' => 2, 'costo_unitario' => 500],
        ]);

        $generadas = app(RecepcionDeCompra::class)->recepcionar($compra);

        $this->assertSame(5, $generadas);
        $this->assertSame(5, Unidad::where('compra_id', $compra->id)->count());
        $this->assertSame(5, Unidad::where('estado', 'en_stock')->count());
    }

    public function test_el_costo_de_las_unidades_suma_exactamente_la_inversion(): void
    {
        // Este es el test que pide el plan: el prorrateo del landed cost debe
        // sumar exactamente subtotal + gastos, sin centavos perdidos.
        $compra = $this->compraConLineas(
            [
                ['cantidad' => 3, 'costo_unitario' => 1000],
                ['cantidad' => 2, 'costo_unitario' => 500],
                ['cantidad' => 1, 'costo_unitario' => 333.33],
            ],
            ['flete' => 100, 'otros_gastos' => 50.55]
        );

        $servicio = app(RecepcionDeCompra::class);
        $servicio->recepcionar($compra);

        $esperado = ProrrateoDeGastos::aDecimal(
            ProrrateoDeGastos::aCentavos($compra->subtotal)
            + ProrrateoDeGastos::aCentavos($compra->gastos_prorrateables)
        );

        $this->assertSame($esperado, $servicio->costoTotalDeUnidades($compra->fresh()));
    }

    public function test_el_gasto_se_reparte_proporcionalmente_entre_lineas(): void
    {
        // Línea A vale 2000, línea B vale 1000: del flete de 300, A carga 200.
        $compra = $this->compraConLineas(
            [
                ['cantidad' => 2, 'costo_unitario' => 1000],
                ['cantidad' => 1, 'costo_unitario' => 1000],
            ],
            ['flete' => 300]
        );

        app(RecepcionDeCompra::class)->recepcionar($compra);

        $lineas = $compra->detalles()->orderBy('id')->get();

        // A: 200 entre 2 unidades = 100 cada una → 1100
        $costosA = $lineas[0]->unidades()->pluck('costo_unitario')->all();
        $this->assertSame(['1100.00', '1100.00'], $costosA);

        // B: 100 en una unidad → 1100
        $this->assertSame(['1100.00'], $lineas[1]->unidades()->pluck('costo_unitario')->all());
    }

    public function test_el_centavo_sobrante_se_asigna_no_se_pierde(): void
    {
        // Un flete de 0.01 sobre 3 unidades no se puede repartir en partes
        // iguales: una unidad debe cargarlo entero.
        $compra = $this->compraConLineas(
            [['cantidad' => 3, 'costo_unitario' => 100]],
            ['flete' => 0.01]
        );

        $servicio = app(RecepcionDeCompra::class);
        $servicio->recepcionar($compra);

        $costos = Unidad::where('compra_id', $compra->id)->pluck('costo_unitario')->sort()->values()->all();

        $this->assertSame(['100.00', '100.00', '100.01'], $costos);
        $this->assertSame('300.01', $servicio->costoTotalDeUnidades($compra->fresh()));
    }

    public function test_las_unidades_heredan_el_precio_de_venta_de_la_linea(): void
    {
        $compra = $this->compraConLineas([
            ['cantidad' => 2, 'costo_unitario' => 1000, 'precio_venta' => 1499.90],
        ]);

        app(RecepcionDeCompra::class)->recepcionar($compra);

        $this->assertSame(['1499.90', '1499.90'], Unidad::pluck('precio_venta')->all());
    }

    public function test_las_unidades_reciben_la_garantia_del_producto(): void
    {
        $producto = Producto::factory()->create(['meses_garantia' => 24]);

        $compra = $this->compraConLineas([
            ['cantidad' => 1, 'costo_unitario' => 1000, 'producto_id' => $producto->id],
        ]);

        app(RecepcionDeCompra::class)->recepcionar($compra);

        $this->assertSame(
            now()->addMonths(24)->toDateString(),
            Unidad::first()->garantia_hasta->toDateString()
        );
    }

    public function test_la_compra_queda_recepcionada_con_su_fecha(): void
    {
        $compra = $this->compraConLineas([['cantidad' => 1, 'costo_unitario' => 100]]);

        app(RecepcionDeCompra::class)->recepcionar($compra);
        $compra->refresh();

        $this->assertTrue($compra->esta_recepcionada);
        $this->assertNotNull($compra->recepcionada_en);
    }

    public function test_no_se_puede_recepcionar_dos_veces(): void
    {
        $compra = $this->compraConLineas([['cantidad' => 2, 'costo_unitario' => 100]]);

        $servicio = app(RecepcionDeCompra::class);
        $servicio->recepcionar($compra);

        $this->expectException(RuntimeException::class);

        $servicio->recepcionar($compra->fresh());
    }

    public function test_no_se_puede_recepcionar_una_compra_sin_lineas(): void
    {
        $compra = Compra::factory()->create();

        $this->expectException(RuntimeException::class);

        app(RecepcionDeCompra::class)->recepcionar($compra);
    }

    public function test_si_falla_la_generacion_no_queda_nada_a_medias(): void
    {
        // La recepción es atómica: o se genera el lote completo o no se crea
        // nada. Se simula un fallo a mitad del lote (la tercera unidad).
        $compra = $this->compraConLineas([
            ['cantidad' => 2, 'costo_unitario' => 100],
            ['cantidad' => 2, 'costo_unitario' => 100],
        ]);

        $this->app->bind(GeneradorCodigoUnidad::class, fn () => new class extends GeneradorCodigoUnidad
        {
            private int $llamadas = 0;

            public function crearCon(array $datos): Unidad
            {
                if (++$this->llamadas === 3) {
                    throw new RuntimeException('Fallo simulado a mitad del lote.');
                }

                return parent::crearCon($datos);
            }
        });

        try {
            app(RecepcionDeCompra::class)->recepcionar($compra);
            $this->fail('Se esperaba que la excepción del generador se propagara.');
        } catch (RuntimeException) {
            // Esperado.
        }

        // Las dos unidades que sí se alcanzaron a crear deben haberse revertido.
        $this->assertSame(0, Unidad::count());
        $this->assertTrue($compra->fresh()->es_borrador);
    }
}
