<?php

namespace App\Support;

use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Venta;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Arma y mantiene el plan de cuotas de una venta a crédito.
 *
 * **Sin interés.** La suma de las cuotas es exactamente lo que se financió, ni
 * un centavo más. Si la tienda quiere cobrar más caro a plazos, sube el precio
 * pactado de la línea en el punto de venta —que ya se puede— y así el recargo
 * queda dentro de la venta, donde los reportes de ganancia ya lo cuentan. Un
 * interés aparte obligaría a decidir si es ingreso y a separarlo del costo en
 * cada consulta.
 */
class PlanDeCuotas
{
    /** Tope de cuotas. Más de cinco años a plazos no es una venta, es un pleito. */
    public const MAX_CUOTAS = 60;

    /**
     * Crea el plan de una venta ya registrada.
     *
     * @param  array{cuota_inicial?: float|string, numero_cuotas: int, primer_vencimiento: string, notas?: ?string}  $plan
     */
    public function crear(Venta $venta, array $plan, int $userId): Credito
    {
        if (! $venta->es_a_credito) {
            throw new RuntimeException('Esa venta no se cobró a crédito.');
        }

        if ($venta->esta_anulada) {
            throw new RuntimeException('Una venta anulada no puede financiarse.');
        }

        // Una deuda necesita un deudor. La venta al público sin datos sirve
        // para cobrar al contado, no para prestar.
        if ($venta->cliente_id === null) {
            throw new RuntimeException('Una venta a crédito necesita un cliente identificado.');
        }

        if ($venta->credito()->exists()) {
            throw new RuntimeException('Esa venta ya tiene un plan de cuotas.');
        }

        $total = ProrrateoDeGastos::aCentavos($venta->total);
        $inicial = ProrrateoDeGastos::aCentavos($plan['cuota_inicial'] ?? 0);
        $cuotas = (int) ($plan['numero_cuotas'] ?? 0);

        if ($inicial < 0) {
            throw new RuntimeException('La cuota inicial no puede ser negativa.');
        }

        if ($inicial >= $total) {
            throw new RuntimeException(
                'La cuota inicial cubre toda la venta: cóbrala al contado en vez de financiarla.'
            );
        }

        if ($cuotas < 1 || $cuotas > self::MAX_CUOTAS) {
            throw new RuntimeException('El número de cuotas debe estar entre 1 y '.self::MAX_CUOTAS.'.');
        }

        $primerVencimiento = $this->fecha($plan['primer_vencimiento'] ?? '');

        if ($primerVencimiento->lt(today())) {
            throw new RuntimeException('La primera cuota no puede vencer antes de hoy.');
        }

        $financiado = $total - $inicial;

        return DB::transaction(function () use ($venta, $plan, $userId, $inicial, $financiado, $cuotas, $primerVencimiento): Credito {
            $credito = Credito::create([
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'cuota_inicial' => ProrrateoDeGastos::aDecimal($inicial),
                'total_financiado' => ProrrateoDeGastos::aDecimal($financiado),
                'numero_cuotas' => $cuotas,
                'primer_vencimiento' => $primerVencimiento,
                'estado' => 'vigente',
                'creado_por' => $userId,
                'notas' => isset($plan['notas']) && trim((string) $plan['notas']) !== ''
                    ? trim((string) $plan['notas'])
                    : null,
            ]);

            // Reparto en partes iguales por el método del resto mayor: los
            // centavos que no dividen exactos se cargan en las primeras
            // cuotas y la suma da el financiado al céntimo. Dividir y
            // redondear dejaría una diferencia que nadie sabría a quién
            // cobrar.
            $montos = ProrrateoDeGastos::repartir($financiado, array_fill(0, $cuotas, 1));

            foreach ($montos as $indice => $monto) {
                Cuota::create([
                    'credito_id' => $credito->id,
                    'numero' => $indice + 1,
                    // `addMonthsNoOverflow` y no `addMonths`: con la primera
                    // cuota el 31 de enero, `addMonths` daría el 3 de marzo
                    // —febrero no tiene 31— y el cliente vería un vencimiento
                    // que no pactó. Sin overflow cae el 28.
                    'vence_en' => $primerVencimiento->copy()->addMonthsNoOverflow($indice),
                    'monto' => ProrrateoDeGastos::aDecimal($monto),
                    'monto_pagado' => ProrrateoDeGastos::aDecimal(0),
                ]);
            }

            return $credito->fresh();
        });
    }

    /**
     * La venta se anuló: el crédito deja de cobrarse.
     *
     * No se borra ni se ponen las cuotas a cero. Los pagos que ya entraron
     * existieron y tienen que seguir contando en el arqueo del turno en que
     * se recibieron; lo que hay que devolver al cliente se resuelve en el
     * mostrador, no reescribiendo la historia.
     */
    public function anular(Credito $credito): void
    {
        if ($credito->estado === 'anulado') {
            return;
        }

        $credito->update(['estado' => 'anulado']);
    }

    /**
     * Se devolvió un aparato: la deuda baja.
     *
     * Se descuenta **desde la última cuota hacia atrás**. Es lo que espera el
     * cliente: sigue pagando lo mismo cada mes y termina antes. Rebajar todas
     * las cuotas por igual obligaría a reimprimir el plan entero y a explicar
     * un importe nuevo cada vez.
     *
     * @param  int  $rebajaEnCentavos  Lo que se le devuelve al cliente.
     * @return int  Lo que no cupo en la deuda: dinero a devolver en efectivo.
     */
    public function reducir(Credito $credito, int $rebajaEnCentavos): int
    {
        if ($rebajaEnCentavos <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($credito, $rebajaEnCentavos): int {
            $porRebajar = $rebajaEnCentavos;

            // De la última a la primera: se acorta el plan por el final.
            //
            // `reorder` y no `orderByDesc` a secas: la relación ya ordena por
            // número ascendente, y encadenar un segundo criterio deja mandando
            // al primero — el recorte habría empezado por la cuota que vence
            // antes, justo al revés de lo que se busca.
            $cuotas = $credito->cuotas()->reorder('numero', 'desc')->get();

            foreach ($cuotas as $cuota) {
                if ($porRebajar <= 0) {
                    break;
                }

                // Solo se puede rebajar lo que todavía se debe. Lo ya cobrado
                // no se toca: ese dinero está en un cajón que ya se cuadró.
                $rebajable = min($porRebajar, $cuota->faltaEnCentavos());

                if ($rebajable === 0) {
                    continue;
                }

                $nuevoMonto = $cuota->montoEnCentavos() - $rebajable;

                $cuota->update([
                    'monto' => ProrrateoDeGastos::aDecimal($nuevoMonto),
                    'pagada_en' => $cuota->pagadoEnCentavos() >= $nuevoMonto
                        ? ($cuota->pagada_en ?? now())
                        : null,
                ]);

                $porRebajar -= $rebajable;
            }

            $credito->update([
                'total_financiado' => ProrrateoDeGastos::aDecimal(
                    max(0, ProrrateoDeGastos::aCentavos($credito->total_financiado) - ($rebajaEnCentavos - $porRebajar))
                ),
            ]);

            $this->cerrarSiNoQuedaSaldo($credito->refresh());

            // Lo que sobró es dinero que el cliente ya pagó y hay que
            // devolverle: no se puede descontar de una deuda que ya no existe.
            return $porRebajar;
        });
    }

    /** Un crédito sin saldo deja de ser cartera. */
    public function cerrarSiNoQuedaSaldo(Credito $credito): void
    {
        if ($credito->estado !== 'vigente') {
            return;
        }

        if ($credito->load('cuotas')->saldoEnCentavos() === 0) {
            $credito->update(['estado' => 'pagado']);
        }
    }

    /**
     * `Carbon::parse('')` devuelve «ahora» en vez de fallar, así que la cadena
     * vacía se descarta antes: un plan sin fecha se habría guardado con el
     * primer vencimiento puesto hoy sin que nadie lo pidiera.
     */
    private function fecha(string $valor): CarbonInterface
    {
        if (trim($valor) === '') {
            throw new RuntimeException('Falta la fecha de la primera cuota.');
        }

        try {
            return Carbon::parse($valor)->startOfDay();
        } catch (\Throwable) {
            throw new RuntimeException('La fecha de la primera cuota no es válida.');
        }
    }
}
