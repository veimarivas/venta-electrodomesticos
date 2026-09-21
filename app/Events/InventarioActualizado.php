<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Cambió la disponibilidad del inventario: una unidad se apartó al entrar a un
 * carrito o se soltó al salir.
 *
 * Lo escuchan las pantallas que muestran cuántas unidades quedan —categorías,
 * marcas, productos, stock y unidades— para refrescar sus conteos sin recargar
 * la página. El payload es vacío a propósito: no lleva datos, solo avisa de que
 * hay que volver a mirar.
 *
 * **ShouldBroadcastNow, no ShouldBroadcast.** Encolado, el aviso se quedaría en
 * `jobs` hasta que corra un worker, y esto es justo lo que se quiere ver al
 * instante. Además, el sondeo de cada pantalla es la red de seguridad para
 * cuando Reverb no está corriendo.
 */
class InventarioActualizado implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('inventario')];
    }

    /**
     * El punto es obligatorio en el cliente: sin él, Echo antepone el namespace.
     */
    public function broadcastAs(): string
    {
        return 'InventarioActualizado';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['en' => now()->toIso8601String()];
    }
}
