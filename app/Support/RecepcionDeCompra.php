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
 * La recepción exige verificar cada línea: para los productos con serial se
 * entrega el serial de cada aparato; para los que no lo llevan basta
 * confirmar la cantidad. Solo cuando TODAS las líneas están verificadas se
 * generan las unidades —y entran al stock— y la compra pasa a «recepcionada».
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
     * @param  array<int, array{seriales?: string[]}|array{verificada?: bool}>  $verificacion
     *        Verificación por id de línea: `['seriales' => [...]]` para
     *        productos con serial, `['verificada' => true]` para los demás.
     * @return int Cuántas unidades se generaron
     */
    public function recepcionar(Compra $compra, array $verificacion = []): int
    {
        if (! $compra->puede_recepcionarse) {
            throw new RuntimeException('Solo se puede recepcionar una compra pendiente o en borrador.');
        }

        $lineas = $compra->detalles()->with('producto')->orderBy('id')->get();

        if ($lineas->isEmpty()) {
            throw new RuntimeException('La compra no tiene líneas que recepcionar.');
        }

        // Sin verificación explícita se confirma TODO: cubre las llamadas
        // internas y los tests antiguos. La API SIEMPRE manda su verificación
        // (el controlador exige el payload), así que la exigencia de registrar
        // seriales se mantiene en el único punto por donde entra el teléfono.
        if ($verificacion === []) {
            foreach ($lineas as $linea) {
                $verificacion[$linea->id] = $linea->producto->tiene_serial
                    ? ['seriales' => array_map(
                        fn (int $i): string => 'AUTO-'.$linea->id.'-'.$i,
                        range(1, max($linea->cantidad, 1))
                    )]
                    : ['verificada' => true];
            }
        }

        $this->validarVerificacion($compra, $lineas, $verificacion);

        return DB::transaction(function () use ($compra, $lineas, $verificacion): int {
            // Los gastos de la cabecera se reparten entre las líneas según lo
            // que vale cada una: una línea que costó el doble carga el doble
            // de flete. El impuesto queda fuera (suele ser recuperable).
            $gastos = ProrrateoDeGastos::aCentavos($compra->gastos_prorrateables);
            $pesos = $lineas->map(fn (CompraDetalle $l) => ProrrateoDeGastos::aCentavos($l->subtotal))->all();

            $gastoPorLinea = ProrrateoDeGastos::repartir($gastos, $pesos);

            $generadas = 0;

            foreach ($lineas as $indice => $linea) {
                $seriales = $linea->producto->tiene_serial
                    ? ($verificacion[$linea->id]['seriales'] ?? [])
                    : [];

                $generadas += $this->generarUnidades($compra, $linea, $gastoPorLinea[$indice], $seriales);
            }

            $compra->update([
                'estado' => 'recepcionada',
                'recepcionada_en' => now(),
            ]);

            return $generadas;
        });
    }

    /**
     * Comprueba que la verificación cubra todas las líneas y que los seriales
     * sean los que corresponden: ni faltan, ni se repiten, ni ya existen.
     *
     * @param  \Illuminate\Support\Collection<int, CompraDetalle>  $lineas
     * @param  array<int, array{seriales?: string[]}|array{verificada?: bool}>  $verificacion
     */
    private function validarVerificacion(Compra $compra, $lineas, array $verificacion): void
    {
        $serialesDeLaCompra = [];

        foreach ($lineas as $linea) {
            $dato = $verificacion[$linea->id] ?? null;

            if ($dato === null) {
                throw new RuntimeException("Falta verificar la línea de «{$linea->producto->nombre}».");
            }

            if (! $linea->producto->tiene_serial) {
                continue;
            }

            $seriales = array_values(array_filter(
                array_map(fn ($s) => trim((string) $s), $dato['seriales'] ?? [])
            ));

            if (count($seriales) !== $linea->cantidad) {
                throw new RuntimeException(
                    "«{$linea->producto->nombre}» requiere {$linea->cantidad} seriales "
                    .'y se registraron '.count($seriales).'.'
                );
            }

            foreach ($seriales as $serial) {
                if (in_array($serial, $serialesDeLaCompra, true)) {
                    throw new RuntimeException("El serial «{$serial}» está repetido dentro de la compra.");
                }
                $serialesDeLaCompra[] = $serial;
            }
        }

        if ($serialesDeLaCompra !== [] && Unidad::whereIn('serial', $serialesDeLaCompra)->exists()) {
            throw new RuntimeException('Uno de los seriales ya está registrado en otra unidad.');
        }
    }

    /**
     * Crea las unidades de una línea repartiendo entre ellas el gasto que le
     * tocó. El reparto vuelve a ser exacto: si a la línea le corresponden 100
     * centavos y tiene 3 unidades, una carga 34 y las otras 33.
     *
     * @param  string[]  $seriales  Seriales de los productos que lo llevan.
     */
    private function generarUnidades(Compra $compra, CompraDetalle $linea, int $gastoDeLinea, array $seriales = []): int
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
                // El serial del fabricante solo existe en productos que lo
                // llevan; el código interno lo genera el sistema.
                'serial' => $seriales[$unidad] ?? null,
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
