<?php

namespace App\Support;

use App\Models\Credito;
use App\Models\Cuota;
use App\Models\PagoCredito;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recibe dinero contra un crédito y lo imputa a sus cuotas.
 *
 * La imputación va **de la cuota más antigua a la más nueva**, sin preguntar.
 * Dejar elegir cuál se paga permitiría saldar la de diciembre dejando viva la
 * de agosto, y la mora dejaría de significar nada.
 */
class CobroDeCuota
{
    public function __construct(private readonly ArqueoDeCaja $arqueo) {}

    /**
     * @param  array{metodo_pago?: string, comprobante_qr?: ?string, notas?: ?string}  $datos
     * @return Collection<int, PagoCredito>  Una fila por cuota tocada, mismo recibo.
     */
    public function cobrar(Credito $credito, float|string $monto, array $datos, int $userId): Collection
    {
        if ($credito->estado === 'anulado') {
            throw new RuntimeException('Ese crédito está anulado: no se le puede cobrar.');
        }

        $entregado = ProrrateoDeGastos::aCentavos($monto);

        if ($entregado <= 0) {
            throw new RuntimeException('El monto del pago tiene que ser mayor que cero.');
        }

        $metodo = $datos['metodo_pago'] ?? 'efectivo';

        if (! array_key_exists($metodo, PagoCredito::METODOS_PAGO)) {
            throw new RuntimeException('El método de pago no es válido.');
        }

        $comprobante = isset($datos['comprobante_qr']) ? trim((string) $datos['comprobante_qr']) : '';

        // Mismo criterio que en la venta: lo que no entra al cajón necesita
        // respaldo del banco, o el día que no cuadre no habrá con qué mirar.
        if (in_array($metodo, PagoCredito::METODOS_CON_RESPALDO, true) && $comprobante === '') {
            throw new RuntimeException('Falta el respaldo del pago por '.PagoCredito::METODOS_PAGO[$metodo].'.');
        }

        $cajaId = $this->arqueo->abierta()?->id;
        $notas = isset($datos['notas']) && trim((string) $datos['notas']) !== ''
            ? trim((string) $datos['notas'])
            : null;

        return DB::transaction(function () use ($credito, $entregado, $metodo, $comprobante, $notas, $userId, $cajaId): Collection {
            // Se bloquean las cuotas hasta el commit: dos cobros a la vez sobre
            // el mismo crédito imputarían los dos a la misma cuota y el cliente
            // acabaría pagando de más.
            $pendientes = $credito->cuotas()
                ->pendientes()
                ->reorder('numero')
                ->lockForUpdate()
                ->get();

            $saldo = $pendientes->reduce(
                fn (int $suma, Cuota $cuota): int => $suma + $cuota->faltaEnCentavos(),
                0
            );

            if ($saldo === 0) {
                throw new RuntimeException('Ese crédito ya está pagado.');
            }

            // No se acepta de más. Un pago por encima del saldo dejaría un
            // saldo a favor que este sistema no lleva, y el cajero tendría un
            // sobrante en el cajón sin explicación al cerrar.
            if ($entregado > $saldo) {
                throw new RuntimeException(
                    'El pago supera el saldo del crédito ('.ProrrateoDeGastos::aDecimal($saldo).' Bs).'
                );
            }

            $porImputar = $entregado;
            $pagos = collect();

            foreach ($pendientes as $cuota) {
                if ($porImputar <= 0) {
                    break;
                }

                $aplicado = min($porImputar, $cuota->faltaEnCentavos());

                $pagos->push(PagoCredito::create([
                    'credito_id' => $credito->id,
                    'cuota_id' => $cuota->id,
                    // Se rellena al final, cuando ya hay un id del que sacarlo.
                    'recibo' => '',
                    'caja_id' => $cajaId,
                    'user_id' => $userId,
                    'monto' => ProrrateoDeGastos::aDecimal($aplicado),
                    'metodo_pago' => $metodo,
                    'comprobante_qr' => $comprobante !== '' ? $comprobante : null,
                    'pagado_en' => now(),
                    'notas' => $notas,
                ]));

                $pagado = $cuota->pagadoEnCentavos() + $aplicado;

                $cuota->update([
                    'monto_pagado' => ProrrateoDeGastos::aDecimal($pagado),
                    'pagada_en' => $pagado >= $cuota->montoEnCentavos() ? now() : null,
                ]);

                $porImputar -= $aplicado;
            }

            // El número de recibo sale del id de la primera fila: el
            // autoincremento ya garantiza que no se repita. Calcularlo con un
            // MAX+1 podría dar el mismo número a dos cajeros cobrando a
            // créditos distintos en el mismo instante.
            $recibo = 'REC-'.str_pad((string) $pagos->first()->id, 6, '0', STR_PAD_LEFT);

            PagoCredito::whereIn('id', $pagos->pluck('id'))->update(['recibo' => $recibo]);

            app(PlanDeCuotas::class)->cerrarSiNoQuedaSaldo($credito->refresh());

            return $pagos->map(fn (PagoCredito $pago): PagoCredito => $pago->refresh());
        });
    }
}
