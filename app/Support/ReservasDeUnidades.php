<?php

namespace App\Support;

use App\Events\InventarioActualizado;
use App\Models\Unidad;
use Throwable;

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
    public const MINUTOS = 20;

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

        if ($afectadas === 1) {
            $this->avisar();
        }

        return $afectadas === 1;
    }

    /**
     * Reserva varias unidades de un producto **sin serial**, las más antiguas
     * primero. Es la venta por cantidad: «tres cables HDMI» no obliga a escanear
     * tres etiquetas, y entre aparatos idénticos sale primero el que lleva más
     * tiempo en el almacén.
     *
     * Los productos con serial no pasan por aquí: su garantía va atada al
     * aparato concreto, así que se siguen escaneando uno por uno.
     *
     * Puede devolver menos de las pedidas si no hay tantas libres; la reserva
     * de cada una sigue siendo atómica, así que dos cajas que pidan a la vez se
     * reparten lo que hay sin pisarse.
     *
     * @param  array<int, int>  $excluir  Las que ya están en el carrito.
     * @return array<int, int> Ids de las unidades reservadas.
     */
    public function reservarCantidad(int $productoId, int $cantidad, int $userId, array $excluir = []): array
    {
        if ($cantidad <= 0) {
            return [];
        }

        $candidatas = Unidad::query()
            ->where('producto_id', $productoId)
            ->whereNotIn('id', $excluir)
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
            ->orderBy('ingresado_en')
            ->orderBy('id')
            // Holgura por si otra caja se lleva alguna entre la consulta y la
            // reserva: así no hay que volver a consultar.
            ->limit($cantidad + 10)
            ->pluck('id');

        $reservadas = [];

        foreach ($candidatas as $id) {
            if (count($reservadas) === $cantidad) {
                break;
            }

            if ($this->reservar((int) $id, $userId)) {
                $reservadas[] = (int) $id;
            }
        }

        return $reservadas;
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

        $afectadas = Unidad::query()
            ->whereIn('id', $unidadIds)
            ->where('estado', 'reservado')
            ->when($userId !== null, fn ($query) => $query->where('reservado_por', $userId))
            ->update([
                'estado' => 'en_stock',
                'reservado_por' => null,
                'reservado_hasta' => null,
            ]);

        if ($afectadas > 0) {
            $this->avisar();
        }
    }

    /**
     * Suelta todas las reservas vencidas. Devuelve cuántas liberó.
     *
     * También suelta las que no tienen fecha: una reserva sin `reservado_hasta`
     * no bloquea nada (`reservaVigente()` la considera libre), pero dejaría el
     * aparato pintado como «en proceso de venta». Un aparato sin fecha es un dato
     * a medias, no un bloqueo.
     */
    public function liberarVencidas(): int
    {
        $afectadas = Unidad::query()
            ->where('estado', 'reservado')
            ->where(function ($query): void {
                $query->whereNull('reservado_hasta')
                    ->orWhere('reservado_hasta', '<', now());
            })
            ->update([
                'estado' => 'en_stock',
                'reservado_por' => null,
                'reservado_hasta' => null,
            ]);

        if ($afectadas > 0) {
            $this->avisar();
        }

        return $afectadas;
    }

    /**
     * Avisa a las pantallas de disponibilidad. Si Reverb no está corriendo, el
     * broadcast falla y no debe tumbar la reserva: el sondeo lo cubre.
     */
    private function avisar(): void
    {
        try {
            event(new InventarioActualizado);
        } catch (Throwable) {
            // Sin WebSocket, las pantallas se actualizan por su sondeo.
        }
    }
}
