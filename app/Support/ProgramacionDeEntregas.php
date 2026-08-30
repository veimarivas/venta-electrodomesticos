<?php

namespace App\Support;

use App\Models\Entrega;
use App\Models\EntregaDetalle;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Programar entregas y llevarlas por sus estados.
 *
 * ```
 * pendiente ──despachar──▶ en_ruta ──confirmar──▶ entregada
 *     ▲                       │
 *     │                       └──fallar──▶ fallida ──reprogramar──┐
 *     └──────────────────────────────────────────────────────────┘
 *
 * pendiente / en_ruta / fallida ──cancelar──▶ cancelada
 * ```
 *
 * `entregada` y `cancelada` son finales: una entrega firmada no se edita, se
 * programa otra. Es la misma regla que la venta, y por lo mismo — el histórico
 * tiene que seguir diciendo lo que pasó.
 */
class ProgramacionDeEntregas
{
    /**
     * Programa la entrega de unas líneas concretas de una venta.
     *
     * @param  array<int, int>  $ventaDetalleIds
     * @param  array{direccion: string, referencia?: ?string, telefono_contacto?: ?string, programada_para?: ?string, con_instalacion?: bool, repartidor_id?: ?int, notas?: ?string}  $datos
     */
    public function programar(Venta $venta, array $ventaDetalleIds, array $datos, int $userId): Entrega
    {
        if ($venta->esta_anulada) {
            throw new RuntimeException('Esa venta está anulada: no hay nada que entregar.');
        }

        $direccion = trim((string) ($datos['direccion'] ?? ''));

        if ($direccion === '') {
            throw new RuntimeException('Hace falta la dirección de entrega.');
        }

        if ($ventaDetalleIds === []) {
            throw new RuntimeException('Elige al menos un aparato para entregar.');
        }

        $programada = $this->fechaOpcional($datos['programada_para'] ?? null);

        try {
            return DB::transaction(function () use ($venta, $ventaDetalleIds, $datos, $userId, $direccion, $programada): Entrega {
                // Solo líneas de ESTA venta y que no se hayan devuelto. Se
                // filtra en la consulta y no en PHP: el componente es un
                // endpoint invocable y los ids llegan del navegador.
                $lineas = $venta->detalles()
                    ->vigentes()
                    ->whereIn('id', $ventaDetalleIds)
                    ->lockForUpdate()
                    ->get();

                if ($lineas->count() !== count(array_unique($ventaDetalleIds))) {
                    throw new RuntimeException(
                        'Alguno de los aparatos ya no pertenece a esta venta o se devolvió.'
                    );
                }

                $entrega = Entrega::create([
                    'venta_id' => $venta->id,
                    'cliente_id' => $venta->cliente_id,
                    'direccion' => $direccion,
                    'referencia' => $this->texto($datos['referencia'] ?? null),
                    'telefono_contacto' => $this->texto($datos['telefono_contacto'] ?? null),
                    'programada_para' => $programada,
                    'estado' => 'pendiente',
                    'con_instalacion' => (bool) ($datos['con_instalacion'] ?? false),
                    'repartidor_id' => $datos['repartidor_id'] ?? null,
                    'creado_por' => $userId,
                    'notas' => $this->texto($datos['notas'] ?? null),
                ]);

                foreach ($lineas as $linea) {
                    EntregaDetalle::create([
                        'entrega_id' => $entrega->id,
                        'venta_detalle_id' => $linea->id,
                        // Guardia del doble reparto: se pone al programar y se
                        // suelta al cancelar o al devolver el aparato.
                        'venta_detalle_activo_id' => $linea->id,
                    ]);
                }

                return $entrega->fresh();
            });
        } catch (QueryException $e) {
            // El índice único es la última línea de defensa: entre la
            // comprobación y el insert, otra pestaña pudo programar el mismo
            // aparato.
            if ($this->esAparatoYaProgramado($e)) {
                throw new RuntimeException(
                    'Uno de esos aparatos ya está en otra entrega. Revisa las entregas de esta venta.'
                );
            }

            throw $e;
        }
    }

    /** Sale el camión. */
    public function despachar(Entrega $entrega, ?int $repartidorId, int $userId): Entrega
    {
        $this->exigirEstado($entrega, ['pendiente', 'fallida'], 'despachar');

        $repartidor = $repartidorId ?? $entrega->repartidor_id;

        // Sin repartidor no hay a quién preguntarle dónde está el aparato, que
        // es justo lo que el cliente llama a preguntar.
        if ($repartidor === null) {
            throw new RuntimeException('Indica quién lleva la entrega antes de despacharla.');
        }

        $entrega->update([
            'estado' => 'en_ruta',
            'repartidor_id' => $repartidor,
            'salio_en' => now(),
            // Se limpia el fallo anterior: la entrega vuelve a estar en juego y
            // dejar el motivo visible haría creer que sigue fallida.
            'motivo_fallo' => null,
        ]);

        return $entrega->refresh();
    }

    /**
     * Llegó y la recibieron.
     *
     * `recibida_por` es obligatorio a propósito: «entregada» sin nombre de
     * quien firmó no sirve para nada el día que el cliente dice que nunca le
     * llegó.
     */
    public function confirmar(Entrega $entrega, string $recibidaPor, bool $instalada = false): Entrega
    {
        $this->exigirEstado($entrega, ['en_ruta', 'pendiente'], 'confirmar');

        $recibidaPor = trim($recibidaPor);

        if ($recibidaPor === '') {
            throw new RuntimeException('Anota quién recibió el aparato.');
        }

        $entrega->update([
            'estado' => 'entregada',
            'entregada_en' => now(),
            'recibida_por' => $recibidaPor,
            // La instalación solo se marca si se pactó: dar por instalado algo
            // que nadie instaló cierra un trabajo pendiente sin hacerlo.
            'instalada_en' => $entrega->con_instalacion && $instalada ? now() : null,
            'motivo_fallo' => null,
        ]);

        return $entrega->refresh();
    }

    /** No estaba nadie, la dirección estaba mal, no cabía por la puerta… */
    public function fallar(Entrega $entrega, string $motivo): Entrega
    {
        $this->exigirEstado($entrega, ['en_ruta', 'pendiente'], 'marcar como fallida');

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new RuntimeException('Di por qué no se pudo entregar.');
        }

        $entrega->update([
            'estado' => 'fallida',
            'motivo_fallo' => $motivo,
            // Vuelve a estar en la tienda: la salida anterior ya no cuenta.
            'salio_en' => null,
        ]);

        return $entrega->refresh();
    }

    /** Se acordó otro día. */
    public function reprogramar(Entrega $entrega, ?string $fecha, ?string $direccion = null): Entrega
    {
        $this->exigirEstado($entrega, ['pendiente', 'en_ruta', 'fallida'], 'reprogramar');

        $entrega->update([
            'estado' => 'pendiente',
            'programada_para' => $this->fechaOpcional($fecha),
            'direccion' => $direccion !== null && trim($direccion) !== ''
                ? trim($direccion)
                : $entrega->direccion,
            'salio_en' => null,
        ]);

        return $entrega->refresh();
    }

    /**
     * La entrega se cae: el cliente pasa a recogerlo, o se anuló la venta.
     *
     * Suelta la guardia del doble reparto para que esos aparatos se puedan
     * programar en otra entrega. Las líneas no se borran: el histórico conserva
     * que hubo un intento.
     */
    public function cancelar(Entrega $entrega, ?string $motivo = null): Entrega
    {
        if ($entrega->esta_entregada) {
            throw new RuntimeException('Esa entrega ya se hizo: no se puede cancelar.');
        }

        if ($entrega->estado === 'cancelada') {
            return $entrega;
        }

        return DB::transaction(function () use ($entrega, $motivo): Entrega {
            $entrega->detalles()->update(['venta_detalle_activo_id' => null]);

            $entrega->update([
                'estado' => 'cancelada',
                'salio_en' => null,
                'motivo_fallo' => $motivo !== null && trim($motivo) !== '' ? trim($motivo) : $entrega->motivo_fallo,
            ]);

            return $entrega->refresh();
        });
    }

    /**
     * Se devolvió un aparato: sale de las entregas que todavía no se hicieron.
     *
     * Si con eso la entrega se queda sin nada que llevar, se cancela entera —
     * un camión saliendo con la caja vacía es peor que no salir.
     */
    public function liberar(VentaDetalle $detalle): void
    {
        $lineas = EntregaDetalle::query()
            ->where('venta_detalle_id', $detalle->id)
            ->whereNotNull('venta_detalle_activo_id')
            ->with('entrega')
            ->get();

        foreach ($lineas as $linea) {
            $entrega = $linea->entrega;

            // Una entrega ya firmada no se toca: el aparato se entregó y
            // después volvió, y las dos cosas pasaron de verdad.
            if ($entrega === null || ! $entrega->esta_abierta) {
                continue;
            }

            $linea->update(['venta_detalle_activo_id' => null]);

            if (! $entrega->detalles()->whereNotNull('venta_detalle_activo_id')->exists()) {
                $this->cancelar($entrega->refresh(), 'Se devolvieron todos sus aparatos.');
            }
        }
    }

    /** La venta se anuló: se caen todas sus entregas pendientes. */
    public function cancelarPorVenta(Venta $venta, string $motivo): void
    {
        foreach ($venta->entregas()->abiertas()->get() as $entrega) {
            $this->cancelar($entrega, "Venta anulada: {$motivo}");
        }
    }

    /**
     * @param  array<int, string>  $permitidos
     */
    private function exigirEstado(Entrega $entrega, array $permitidos, string $accion): void
    {
        if (! in_array($entrega->estado, $permitidos, true)) {
            throw new RuntimeException(
                'No se puede '.$accion.' una entrega '.
                mb_strtolower(Entrega::ESTADOS[$entrega->estado] ?? $entrega->estado).'.'
            );
        }
    }

    private function texto(?string $valor): ?string
    {
        return $valor !== null && trim($valor) !== '' ? trim($valor) : null;
    }

    /**
     * `Carbon::parse('')` devuelve «ahora» en vez de fallar, así que la cadena
     * vacía se trata como «sin fecha» antes de llegar a él.
     */
    private function fechaOpcional(?string $valor): ?Carbon
    {
        if ($valor === null || trim($valor) === '') {
            return null;
        }

        try {
            return Carbon::parse($valor)->startOfDay();
        } catch (Throwable) {
            throw new RuntimeException('La fecha de entrega no es válida.');
        }
    }

    private function esAparatoYaProgramado(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'entrega_detalles_venta_detalle_activo_id_unique')
            || (str_contains($e->getMessage(), 'Duplicate entry')
                && str_contains($e->getMessage(), 'venta_detalle_activo_id'));
    }
}
