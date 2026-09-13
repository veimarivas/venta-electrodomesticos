<?php

namespace App\Support;

use App\Models\Unidad;

/**
 * Reserva temporal de unidades mientras están en un carrito del POS.
 *
 * El problema que resuelve: dos cajeros pueden escanear el mismo aparato a la
 * vez y solo se enteran al cobrar. Reservar al agregar al carrito mueve la
 * sorpresa al principio, cuando todavía no hay un cliente esperando.
 *
 * La reserva tiene vencimiento: si el carrito se abandona, el aparato vuelve
 * solo al stock. Las operaciones son `UPDATE ... WHERE` —atómicas— para que dos
 * cajas no reserven la misma unidad ni se pisen al liberarla.
 */
class ReservasDeUnidades
{
    /** Cuánto dura la reserva sin que el carrito se toque de nuevo. */
    public const MINUTOS = 15;

    /**
     * Reserva una unidad para el usuario. Devuelve false si otro la tiene.
     *
     * También toma una reserva ya vencida: si el carrito que la apartó se
     * abandonó, la unidad está libre de hecho aunque el barrido no haya pasado.
     */
    public function reservar(int $unidadId, int $userId): bool
    {
        $afectadas = Unidad::query()
            ->whereKey($unidadId)
            ->where(function ($query): void {
                $query->where('estado', 'en_stock')
                    ->orWhere(function ($query): void {
                        $query->where('estado', 'reservado')
                            ->where(function ($query): void {
                                $query->whereNull('reservado_hasta')
                                    ->orWhere('reservado_hasta', '<', now());
                            });
                    });
            })
            ->update([
                'estado' => 'reservado',
                'reservado_por' => $userId,
                'reservado_hasta' => now()->addMinutes(self::MINUTOS),
            ]);

        return $afectadas === 1;
    }

    /** Extiende la reserva de unas unidades del propio usuario. */
    public function refrescar(array $unidadIds, int $userId): void
    {
        if ($unidadIds === []) {
            return;
        }

        Unidad::query()
            ->whereIn('id', $unidadIds)
            ->where('estado', 'reservado')
            ->where('reservado_por', $userId)
            ->update(['reservado_hasta' => now()->addMinutes(self::MINUTOS)]);
    }

    /**
     * Devuelve unidades al stock.
     *
     * Con `$userId` solo libera las del propio usuario; sin él, todas las que
     * estén en la lista (lo usa el barrido). Nunca toca una unidad vendida: el
     * `where estado = reservado` lo garantiza.
     */
    public function liberar(array $unidadIds, ?int $userId = null): void
    {
        if ($unidadIds === []) {
            return;
        }

        Unidad::query()
            ->whereIn('id', $unidadIds)
            ->where('estado', 'reservado')
            ->when($userId !== null, fn ($query) => $query->where('reservado_por', $userId))
            ->update([
                'estado' => 'en_stock',
                'reservado_por' => null,
                'reservado_hasta' => null,
            ]);
    }

    /** Suelta todas las reservas vencidas. Devuelve cuántas liberó. */
    public function liberarVencidas(): int
    {
        return Unidad::query()
            ->where('estado', 'reservado')
            ->whereNotNull('reservado_hasta')
            ->where('reservado_hasta', '<', now())
            ->update([
                'estado' => 'en_stock',
                'reservado_por' => null,
                'reservado_hasta' => null,
            ]);
    }
}
