<?php

namespace App\Events;

use App\Models\SolicitudDescuento;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * El administrador resolvió una solicitud de descuento.
 *
 * Viaja al canal de esa solicitud en concreto, que escucha quien la pidió: el
 * POS actualiza el carrito solo, sin recargar. El canal lo comparte el
 * solicitante y quien puede autorizar, y nada más.
 */
class SolicitudDeDescuentoResuelta implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly SolicitudDescuento $solicitud) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('solicitud.'.$this->solicitud->id)];
    }

    public function broadcastAs(): string
    {
        return 'SolicitudDeDescuentoResuelta';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $solicitud = $this->solicitud;

        return [
            'id' => $solicitud->id,
            'estado' => $solicitud->estado,
            'precio_aprobado' => $solicitud->precio_aprobado !== null
                ? (float) $solicitud->precio_aprobado
                : null,
            'motivo' => $solicitud->motivo,
        ];
    }
}
