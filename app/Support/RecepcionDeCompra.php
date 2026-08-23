<?php

namespace App\Support;

use App\Models\Unidad;
use App\Models\Compra;
use App\Models\CompraDetalle;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recepciona una compra: convierte sus líneas en unidades físicas.
 *
 * Es el punto donde el dinero se vuelve inventario. Por cada línea de N
 * unidades se crean N registros en `unidades`, cada uno con su código interno y
 * con el costo REAL que le corresponde (landed cost), no con el precio de
 * lista del proveedor.
 *
 * Todo ocurre dentro de una transacción: o se genera el lote completo y la
 * compra queda recepcionada, o no se crea nada.
 */
class RecepcionDeCompra
{
    public function __construct(
        private readonly GeneradorCodigoUnidad $generador,
        private readonly Kardex $kardex,
    ) {}

    /**
     * @return int Cuántas unidades se generaron
     */
    public function recepcionar(Compra $compra): int
    {
        if (! $compra->es_borrador) {
            throw new RuntimeException('Solo se puede recepcionar una compra en borrador.');
        }

        $lineas = $compra->detalles()->with('producto')->orderBy('id')->get();

        if ($lineas->isEmpty()) {
            throw new RuntimeException('La compra no tiene líneas que recepcionar.');
        }

        return DB::transaction(function () use ($compra, $lineas): int {
            // Los gastos de la cabecera se reparten entre las líneas según lo
            // que vale cada una: una línea que costó el doble carga el doble
            // de flete. El impuesto queda fuera (suele ser recuperable).
            $gastos = ProrrateoDeGastos::aCentavos($compra->gastos_prorrateables);
            $pesos = $lineas->map(fn (CompraDetalle $l) => ProrrateoDeGastos::aCentavos($l->subtotal))->all();

            $gastoPorLinea = ProrrateoDeGastos::repartir($gastos, $pesos);

            $generadas = 0;

            foreach ($lineas as $indice => $linea) {
                $generadas += $this->generarUnidades($compra, $linea, $gastoPorLinea[$indice]);
            }

            $compra->update([
                'estado' => 'recepcionada',
                'recepcionada_en' => now(),
            ]);

            return $generadas;
        });
    }

    /**
     * Crea las unidades de una línea repartiendo entre ellas el gasto que le
     * tocó. El reparto vuelve a ser exacto: si a la línea le corresponden 100
     * centavos y tiene 3 unidades, una carga 34 y las otras 33.
     */
    private function generarUnidades(Compra $compra, CompraDetalle $linea, int $gastoDeLinea): int
    {
        $piezas = max($linea->cantidad, 1);

        // El subtotal (lo facturado por este producto) se reparte entre las
        // piezas, NO se multiplica costo_unitario × cantidad: si la división
        // no es exacta —1000 Bs entre 3— multiplicar el promedio redondeado
        // perdería o inventaría centavos frente a la factura. Repartir asigna
        // el resto pieza a pieza: 333,34 / 333,33 / 333,33.
        $costoPorUnidad = ProrrateoDeGastos::repartir(
            ProrrateoDeGastos::aCentavos($linea->subtotal),
            array_fill(0, $piezas, 1)
        );

        $gastoPorUnidad = ProrrateoDeGastos::repartir($gastoDeLinea, array_fill(0, $piezas, 1));

        // El costo_real_unitario de la línea es informativo (el promedio); el
        // costo que manda es el de cada unidad, que puede diferir un centavo.
        $linea->update([
            'costo_real_unitario' => ProrrateoDeGastos::aDecimal(
                intdiv(ProrrateoDeGastos::aCentavos($linea->subtotal) + $gastoDeLinea, $piezas)
            ),
        ]);

        for ($unidad = 0; $unidad < $linea->cantidad; $unidad++) {
            $fisica = $this->generador->crearCon([
                'producto_id' => $linea->producto_id,
                'compra_id' => $compra->id,
                'compra_detalle_id' => $linea->id,
                'costo_unitario' => ProrrateoDeGastos::aDecimal($costoPorUnidad[$unidad] + $gastoPorUnidad[$unidad]),
                'precio_venta' => $linea->precio_venta,
                'estado' => 'en_stock',
                'ingresado_en' => now(),
            ]);

            // Primer movimiento del kardex: de dónde salió este aparato. Va
            // dentro de la misma transacción que la unidad, para que no pueda
            // existir inventario sin su rastro de origen.
            $this->kardex->entrada($fisica, $compra, "Compra {$compra->codigo}");
        }

        return $linea->cantidad;
    }

    /**
     * Suma de los costos de todas las unidades generadas por la compra.
     *
     * Debe coincidir al centavo con subtotal + gastos prorrateables; es la
     * comprobación de que el prorrateo no perdió ni inventó dinero.
     */
    public function costoTotalDeUnidades(Compra $compra): string
    {
        $centavos = Unidad::where('compra_id', $compra->id)
            ->get()
            ->sum(fn (Unidad $unidad) => ProrrateoDeGastos::aCentavos($unidad->costo_unitario));

        return ProrrateoDeGastos::aDecimal((int) $centavos);
    }
}
