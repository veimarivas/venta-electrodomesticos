<?php

namespace App\Events;

use App\Models\SolicitudDescuento;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Un vendedor pidió autorización para bajar de su mínimo.
 *
 * Llega a la pantalla «Autorizaciones» por WebSocket, para que el administrador
 * la vea sin recargar. El canal es privado y solo lo escucha quien puede
 * autorizar: el payload lleva el costo del aparato.
 *
 * ShouldBroadcastNow por la misma razón que `VentaRegistrada`: un aviso que
 * espera a un worker no está en vivo.
 */
class SolicitudDeDescuentoCreada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly SolicitudDescuento $solicitud) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('autorizaciones')];
    }

    public function broadcastAs(): string
    {
        return 'SolicitudDeDescuentoCreada';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $solicitud = $this->solicitud->loadMissing(['producto', 'unidad', 'solicitante']);

        return [
            'id' => $solicitud->id,
            'producto' => $solicitud->producto?->nombre,
            'codigo' => $solicitud->unidad?->codigo_interno,
            'precio_lista' => (float) $solicitud->precio_lista,
            'descuento_maximo' => (float) $solicitud->descuento_maximo,
            'costo' => (float) $solicitud->costo_unitario,
            'precio_solicitado' => (float) $solicitud->precio_solicitado,
            'vendedor' => $solicitud->solicitante?->name,
            'hora' => $solicitud->created_at?->format('H:i'),
        ];
    }
}
