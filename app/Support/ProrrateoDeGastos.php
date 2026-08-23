<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Reparte un importe entre varias partes sin perder ni inventar centavos.
 *
 * El problema: si a cada parte se le calcula su porción y se redondea por
 * separado, la suma casi nunca coincide con el importe original. Con un flete
 * de 100 repartido entre tres líneas iguales, 33.33 × 3 = 99.99: falta un
 * centavo. Y en este sistema ese centavo se convertiría en una ganancia
 * inflada, porque el costo de las unidades saldría por debajo de lo real.
 *
 * La solución es el método del resto mayor: se trabaja en centavos enteros
 * (sin coma flotante), se asigna a cada parte su porción truncada y los
 * centavos sobrantes se entregan uno a uno a las partes con mayor resto.
 * Así la suma del reparto es SIEMPRE exactamente el importe original.
 */
class ProrrateoDeGastos
{
    /**
     * Reparte un importe en centavos según los pesos indicados.
     *
     * @param  int  $importeEnCentavos  Lo que hay que repartir
     * @param  array<int, int>  $pesos  Peso de cada parte, en la misma unidad entre sí
     * @return array<int, int>  Centavos asignados a cada parte, en el mismo orden
     */
    public static function repartir(int $importeEnCentavos, array $pesos): array
    {
        if ($pesos === []) {
            return [];
        }

        if ($importeEnCentavos < 0) {
            throw new InvalidArgumentException('El importe a repartir no puede ser negativo.');
        }

        foreach ($pesos as $peso) {
            if ($peso < 0) {
                throw new InvalidArgumentException('Los pesos del prorrateo no pueden ser negativos.');
            }
        }

        $totalPesos = array_sum($pesos);

        // Sin pesos (todas las líneas en cero) el reparto proporcional no está
        // definido: se hace en partes iguales, que es lo esperable.
        if ($totalPesos === 0) {
            return self::repartir($importeEnCentavos, array_fill(0, count($pesos), 1));
        }

        $asignado = [];
        $restos = [];

        foreach ($pesos as $indice => $peso) {
            $exacto = $importeEnCentavos * $peso;

            $asignado[$indice] = intdiv($exacto, $totalPesos);
            $restos[$indice] = $exacto % $totalPesos;
        }

        // Centavos que quedaron sin asignar por el truncamiento.
        $sobrantes = $importeEnCentavos - array_sum($asignado);

        if ($sobrantes > 0) {
            // Mayor resto primero; a igualdad de resto, la parte de mayor peso,
            // y como último desempate el orden original. Sin este último
            // criterio el reparto no sería reproducible entre ejecuciones.
            $orden = array_keys($restos);

            usort($orden, function (int $a, int $b) use ($restos, $pesos): int {
                return [$restos[$b], $pesos[$b], $a] <=> [$restos[$a], $pesos[$a], $b];
            });

            foreach (array_slice($orden, 0, $sobrantes) as $indice) {
                $asignado[$indice]++;
            }
        }

        ksort($asignado);

        return $asignado;
    }

    /**
     * Convierte un importe decimal ("1234.56") a centavos enteros.
     *
     * Se hace con bcmath y no con (int) round($x * 100) porque multiplicar
     * por 100 en coma flotante ya introduce el error que queremos evitar.
     */
    public static function aCentavos(int|float|string $importe): int
    {
        return (int) bcmul(number_format((float) $importe, 2, '.', ''), '100', 0);
    }

    /**
     * Convierte centavos enteros a un importe decimal con dos decimales.
     */
    public static function aDecimal(int $centavos): string
    {
        return bcdiv((string) $centavos, '100', 2);
    }
}
