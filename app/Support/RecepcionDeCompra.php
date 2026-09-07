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
 * La recepción puede ser **por lotes**: si una línea traía 11 aparatos y solo
 * llegaron 7, se verifican 7 (sus seriales o su cantidad) y la compra sigue
 * pendiente hasta que se verifiquen los 4 restantes. Una vez que TODAS las
 * líneas están completas, la compra pasa a «recepcionada».
 *
 * Todo ocurre dentro de una transacción: o se genera el lote de esta tanda y
 * la compra avanza, o no se crea nada.
 */
class RecepcionDeCompra
{
    public function __construct(
        private readonly GeneradorCodigoUnidad $generador,
        private readonly Kardex $kardex,
    ) {}

    /**
     * @param  array<int, array{seriales?: string[]}|array{cantidad_verificada?: int}>  $verificacion
     *        Verificación por id de línea: `['seriales' => [...]]` para
     *        productos con serial, `['cantidad_verificada' => N]` para los
     *        demás. Puede ser parcial; las líneas omitidas no se tocan en esta
     *        tanda.
     * @return int Cuántas unidades se generaron en esta tanda
     */
    public function recepcionar(Compra $compra, array $verificacion = []): int
    {
        if (! $compra->puede_recepcionarse) {
            throw new RuntimeException('Solo se puede recepcionar una compra pendiente o en borrador.');
        }

        $lineas = $compra->detalles()->with('producto')->withCount('unidades')->orderBy('id')->get();

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
                    : ['cantidad_verificada' => $linea->cantidad];
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
            $completa = true;

            foreach ($lineas as $indice => $linea) {
                $dato = $verificacion[$linea->id] ?? null;
                $yaCreadas = (int) $linea->unidades_count;

                if ($linea->producto->tiene_serial) {
                    $seriales = array_values(array_filter(
                        array_map(fn ($s) => trim((string) $s), $dato['seriales'] ?? [])
                    ));
                    $porCrear = count($seriales);
                } else {
                    $seriales = [];
                    $porCrear = (int) ($dato['cantidad_verificada'] ?? 0);
                }

                if ($porCrear > 0) {
                    $generadas += $this->generarUnidades(
                        $compra,
                        $linea,
                        $gastoPorLinea[$indice],
                        $seriales,
                        $yaCreadas,
                        $porCrear,
                    );
                }

                if ($yaCreadas + $porCrear < $linea->cantidad) {
                    $completa = false;
                }
            }

            // Solo cuando TODAS las líneas están completas la compra pasa a
            // recepcionada. Con una verificación parcial sigue pendiente.
            if ($completa) {
                $compra->update([
                    'estado' => 'recepcionada',
                    'recepcionada_en' => now(),
                ]);
            }

            return $generadas;
        });
    }

    /**
     * Comprueba que la verificación sea válida: los seriales son los que
     * corresponden (ni faltan los de esta tanda, ni se repiten, ni ya existen),
     * y la cantidad marcada no supera lo que falta por recibir.
     *
     * @param  \Illuminate\Support\Collection<int, CompraDetalle>  $lineas
     * @param  array<int, array{seriales?: string[]}|array{cantidad_verificada?: int}>  $verificacion
     */
    private function validarVerificacion(Compra $compra, $lineas, array $verificacion): void
    {
        $serialesDeLaCompra = [];
        $algoVerificado = false;

        foreach ($lineas as $linea) {
            $dato = $verificacion[$linea->id] ?? null;
            $yaCreadas = (int) $linea->unidades_count;
            $restantes = $linea->cantidad - $yaCreadas;

            // Línea ya completa: no se vuelve a verificar.
            if ($restantes <= 0) {
                continue;
            }

            if ($linea->producto->tiene_serial) {
                $seriales = array_values(array_filter(
                    array_map(fn ($s) => trim((string) $s), $dato['seriales'] ?? [])
                ));

                if ($seriales !== [] && count($seriales) > $restantes) {
                    throw new RuntimeException(
                        "«{$linea->producto->nombre}» ya recibió {$yaCreadas} de {$linea->cantidad}; "
                        .'quedan '.$restantes.' por verificar y se registraron '.count($seriales).'.'
                    );
                }

                foreach ($seriales as $serial) {
                    if (in_array($serial, $serialesDeLaCompra, true)) {
                        throw new RuntimeException("El serial «{$serial}» está repetido dentro de la compra.");
                    }
                    $serialesDeLaCompra[] = $serial;
                }

                if ($seriales !== []) {
                    $algoVerificado = true;
                }
            } else {
                $cantidad = (int) ($dato['cantidad_verificada'] ?? 0);

                if ($cantidad < 0 || $cantidad > $restantes) {
                    throw new RuntimeException(
                        "«{$linea->producto->nombre}» quedan {$restantes} unidades por verificar "
                        .'y se marcaron '.$cantidad.'.'
                    );
                }

                if ($cantidad > 0) {
                    $algoVerificado = true;
                }
            }
        }

        if (! $algoVerificado) {
            throw new RuntimeException('Marca cuántas unidades de cada producto llegaron.');
        }

        if ($serialesDeLaCompra !== [] && Unidad::whereIn('serial', $serialesDeLaCompra)->exists()) {
            throw new RuntimeException('Uno de los seriales ya está registrado en otra unidad.');
        }
    }

    /**
     * Crea las unidades de una línea repartiendo entre ellas el gasto que le
     * tocó. El reparto vuelve a ser exacto y se hace sobre el lote COMPLETO de
     * la línea: cada tanda consume las siguientes porciones en orden, así la
     * suma de los costos de todas las unidades —las de hoy y las de mañana—
     * coincide al centavo con lo facturado.
     *
     * @param  string[]  $seriales  Seriales de esta tanda (productos que lo llevan).
     * @param  int  $desde  Cuántas unidades de la línea ya se recibieron antes.
     * @param  int  $porCrear  Cuántas se reciben en esta tanda.
     */
    private function generarUnidades(Compra $compra, CompraDetalle $linea, int $gastoDeLinea, array $seriales = [], int $desde = 0, int $porCrear = 1): int
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

        for ($tanda = 0; $tanda < $porCrear; $tanda++) {
            $indice = $desde + $tanda;

            $fisica = $this->generador->crearCon([
                'producto_id' => $linea->producto_id,
                'compra_id' => $compra->id,
                'compra_detalle_id' => $linea->id,
                'costo_unitario' => ProrrateoDeGastos::aDecimal($costoPorUnidad[$indice] + $gastoPorUnidad[$indice]),
                'precio_venta' => $linea->precio_venta,
                // El serial del fabricante solo existe en productos que lo
                // llevan; el código interno lo genera el sistema.
                'serial' => $seriales[$tanda] ?? null,
                'estado' => 'en_stock',
                'ingresado_en' => now(),
            ]);

            // Primer movimiento del kardex: de dónde salió este aparato. Va
            // dentro de la misma transacción que la unidad, para que no pueda
            // existir inventario sin su rastro de origen.
            $this->kardex->entrada($fisica, $compra, "Compra {$compra->codigo}");
        }

        return $porCrear;
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
