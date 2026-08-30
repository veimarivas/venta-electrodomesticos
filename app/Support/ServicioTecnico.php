<?php

namespace App\Support;

use App\Models\Reparacion;
use App\Models\Unidad;
use App\Models\VentaDetalle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * El paso de un aparato por el taller.
 *
 * ```
 * recibida ──▶ en_reparacion ⇄ esperando_repuesto ──▶ lista ──▶ entregada
 *                    │
 *                    └──▶ irreparable
 *
 * cualquiera abierta ──▶ cancelada
 * ```
 *
 * El aparato pasa al estado `garantia` de `unidades` mientras está en el
 * taller —el nombre viene del enum original, la etiqueta dice «En taller»— y
 * vuelve a su estado anterior al salir. Cada paso deja su movimiento en el
 * kardex: para eso existía ya.
 */
class ServicioTecnico
{
    public function __construct(
        private readonly GeneradorCodigoReparacion $generador,
        private readonly Kardex $kardex,
    ) {}

    /**
     * Recibe un aparato en el taller.
     *
     * @param  array{falla_reportada: string, prometida_para?: ?string, costo?: float|string, tecnico_id?: ?int, notas?: ?string}  $datos
     */
    public function recibir(Unidad $unidad, array $datos, int $userId): Reparacion
    {
        $falla = trim((string) ($datos['falla_reportada'] ?? ''));

        if ($falla === '') {
            throw new RuntimeException('Anota qué le pasa al aparato según el cliente.');
        }

        // Un aparato ya en el taller no se recibe dos veces: la segunda orden
        // partiría el historial de una misma reparación en dos.
        if ($this->reparacionAbierta($unidad) !== null) {
            throw new RuntimeException('Ese aparato ya está en el taller con una orden abierta.');
        }

        // El producto hace falta para calcular la garantía, y con
        // `Model::shouldBeStrict()` una relación no cargada revienta en vez de
        // consultarse sola.
        $unidad->loadMissing('producto');

        // La cobertura se decide AHORA y se congela: si mañana alguien cambia
        // los meses de garantía del producto, esta orden no puede volverse
        // cobrable sola.
        $enGarantia = $unidad->en_garantia;
        $garantiaHasta = $unidad->garantia_hasta;

        $linea = $this->lineaDeVenta($unidad);

        return DB::transaction(function () use ($unidad, $datos, $userId, $falla, $enGarantia, $garantiaHasta, $linea): Reparacion {
            $estadoAnterior = $unidad->estado;

            $reparacion = $this->generador->crearCon([
                'unidad_id' => $unidad->id,
                'venta_id' => $linea?->venta_id,
                'cliente_id' => $linea?->venta?->cliente_id,
                'en_garantia' => $enGarantia,
                'garantia_hasta' => $garantiaHasta,
                'falla_reportada' => $falla,
                'estado' => 'recibida',
                // En garantía no se cobra, y no se acepta un costo que
                // contradiga eso: sería una promesa rota por escrito.
                'costo' => $enGarantia
                    ? ProrrateoDeGastos::aDecimal(0)
                    : ProrrateoDeGastos::aDecimal(ProrrateoDeGastos::aCentavos($datos['costo'] ?? 0)),
                'tecnico_id' => $datos['tecnico_id'] ?? null,
                'prometida_para' => $this->fechaOpcional($datos['prometida_para'] ?? null),
                'recibida_en' => now(),
                'recibida_por' => $userId,
                // De aquí sale al terminar: a un aparato vendido hay que
                // devolverle su `vendido`, y a uno de stock su `en_stock`.
                'estado_unidad_origen' => $estadoAnterior,
                'notas' => $this->texto($datos['notas'] ?? null),
            ]);

            $unidad->update(['estado' => 'garantia']);

            $this->kardex->cambioDeEstado(
                $unidad->refresh(),
                $estadoAnterior,
                $reparacion,
                "Entra al taller ({$reparacion->codigo}): {$falla}"
            );

            return $reparacion->fresh();
        });
    }

    /** El técnico dice qué encontró y se pone con ello. */
    public function diagnosticar(Reparacion $reparacion, string $diagnostico, ?int $tecnicoId = null, float|string|null $costo = null): Reparacion
    {
        $this->exigirEstado($reparacion, ['recibida', 'en_reparacion', 'esperando_repuesto'], 'diagnosticar');

        $diagnostico = trim($diagnostico);

        if ($diagnostico === '') {
            throw new RuntimeException('Escribe qué encontraste.');
        }

        $reparacion->update([
            'estado' => 'en_reparacion',
            'diagnostico' => $diagnostico,
            'tecnico_id' => $tecnicoId ?? $reparacion->tecnico_id,
            // En garantía el costo se queda en cero pase lo que pase.
            'costo' => $reparacion->en_garantia || $costo === null
                ? $reparacion->costo
                : ProrrateoDeGastos::aDecimal(ProrrateoDeGastos::aCentavos($costo)),
        ]);

        return $reparacion->refresh();
    }

    /** Falta una pieza: el reloj del taller sigue corriendo, el trabajo no. */
    public function esperarRepuesto(Reparacion $reparacion, string $detalle): Reparacion
    {
        $this->exigirEstado($reparacion, ['recibida', 'en_reparacion'], 'poner en espera');

        $detalle = trim($detalle);

        if ($detalle === '') {
            throw new RuntimeException('Di qué repuesto hace falta.');
        }

        $reparacion->update([
            'estado' => 'esperando_repuesto',
            'notas' => trim(($reparacion->notas ?? '')."\n".
                now()->format('d/m/Y').' — Esperando repuesto: '.$detalle),
        ]);

        return $reparacion->refresh();
    }

    /** Arreglado y listo para que lo recojan. */
    public function marcarLista(Reparacion $reparacion, string $trabajoRealizado): Reparacion
    {
        $this->exigirEstado($reparacion, ['recibida', 'en_reparacion', 'esperando_repuesto'], 'dar por lista');

        $trabajo = trim($trabajoRealizado);

        if ($trabajo === '') {
            throw new RuntimeException('Anota qué se le hizo al aparato.');
        }

        $reparacion->update([
            'estado' => 'lista',
            'trabajo_realizado' => $trabajo,
            'lista_en' => now(),
        ]);

        return $reparacion->refresh();
    }

    /**
     * El cliente se lo lleva.
     *
     * `entregada_a` es obligatorio por lo mismo que en las entregas a
     * domicilio: sin el nombre de quien se lo llevó, «entregada» no sirve de
     * nada el día que alguien reclame el aparato.
     */
    public function entregar(Reparacion $reparacion, string $entregadaA): Reparacion
    {
        $this->exigirEstado($reparacion, ['lista', 'irreparable'], 'entregar');

        $entregadaA = trim($entregadaA);

        if ($entregadaA === '') {
            throw new RuntimeException('Anota quién se lleva el aparato.');
        }

        return DB::transaction(function () use ($reparacion, $entregadaA): Reparacion {
            $reparacion->update([
                'estado' => 'entregada',
                'entregada_en' => now(),
                'entregada_a' => $entregadaA,
            ]);

            $this->devolverUnidadASuEstado($reparacion, 'Sale del taller');

            return $reparacion->refresh();
        });
    }

    /**
     * No tiene arreglo.
     *
     * El aparato sigue en el taller hasta que el cliente venga por él, así que
     * la unidad no se toca todavía: se devuelve al entregarlo.
     */
    public function declararIrreparable(Reparacion $reparacion, string $motivo): Reparacion
    {
        $this->exigirEstado($reparacion, ['recibida', 'en_reparacion', 'esperando_repuesto'], 'declarar sin arreglo');

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new RuntimeException('Di por qué no tiene arreglo.');
        }

        $reparacion->update([
            'estado' => 'irreparable',
            'diagnostico' => $reparacion->diagnostico ?? $motivo,
            'trabajo_realizado' => $motivo,
            // Sin arreglo no se cobra mano de obra que no arregló nada.
            'costo' => ProrrateoDeGastos::aDecimal(0),
            'lista_en' => now(),
        ]);

        return $reparacion->refresh();
    }

    /** Se recibió por error, o el cliente se lo lleva sin tocarlo. */
    public function cancelar(Reparacion $reparacion, string $motivo): Reparacion
    {
        if (in_array($reparacion->estado, self::cerrados(), true)) {
            throw new RuntimeException('Esa orden ya está cerrada.');
        }

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new RuntimeException('Di por qué se cancela la orden.');
        }

        return DB::transaction(function () use ($reparacion, $motivo): Reparacion {
            $reparacion->update([
                'estado' => 'cancelada',
                'costo' => ProrrateoDeGastos::aDecimal(0),
                'notas' => trim(($reparacion->notas ?? '')."\n".
                    now()->format('d/m/Y').' — Cancelada: '.$motivo),
            ]);

            $this->devolverUnidadASuEstado($reparacion, "Orden cancelada: {$motivo}");

            return $reparacion->refresh();
        });
    }

    /** La orden abierta de un aparato, si la tiene. */
    public function reparacionAbierta(Unidad $unidad): ?Reparacion
    {
        return Reparacion::query()
            ->where('unidad_id', $unidad->id)
            ->abiertas()
            ->latest('id')
            ->first();
    }

    /**
     * Devuelve la unidad al estado del que salió y lo deja en el kardex.
     *
     * Se comprueba que siga en `garantia` antes de tocarla: si alguien la
     * movió a mano mientras estaba en el taller, esa decisión manda sobre esta.
     */
    private function devolverUnidadASuEstado(Reparacion $reparacion, string $nota): void
    {
        $unidad = $reparacion->unidad;

        if ($unidad === null || $unidad->estado !== 'garantia') {
            return;
        }

        $anterior = $unidad->estado;

        $unidad->update(['estado' => $reparacion->estado_unidad_origen]);

        $this->kardex->cambioDeEstado(
            $unidad->refresh(),
            $anterior,
            $reparacion,
            "{$nota} ({$reparacion->codigo})"
        );
    }

    /** La línea de venta por la que salió este aparato, si salió. */
    private function lineaDeVenta(Unidad $unidad): ?VentaDetalle
    {
        return VentaDetalle::query()
            ->with('venta')
            ->where('unidad_id', $unidad->id)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private static function cerrados(): array
    {
        return Reparacion::ESTADOS_CERRADOS;
    }

    /**
     * @param  array<int, string>  $permitidos
     */
    private function exigirEstado(Reparacion $reparacion, array $permitidos, string $accion): void
    {
        if (! in_array($reparacion->estado, $permitidos, true)) {
            throw new RuntimeException(
                'No se puede '.$accion.' una orden '.
                mb_strtolower(Reparacion::ESTADOS[$reparacion->estado] ?? $reparacion->estado).'.'
            );
        }
    }

    private function texto(?string $valor): ?string
    {
        return $valor !== null && trim($valor) !== '' ? trim($valor) : null;
    }

    private function fechaOpcional(?string $valor): ?Carbon
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->startOfDay();
        } catch (Throwable) {
            throw new RuntimeException('La fecha prometida no es válida.');
        }
    }
}
