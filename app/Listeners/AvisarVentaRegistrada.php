<?php

namespace App\Listeners;

use App\Events\VentaRegistrada;
use App\Models\User;
use App\Notifications\VentaRegistradaPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Avisa a quien supervisa la tienda de que se registró una venta.
 *
 * Encolado a propósito: enviar el push tarda y no debe frenar el cobro en el
 * mostrador. El dashboard en vivo no pasa por aquí — ese va por WebSocket y sí
 * es inmediato.
 */
class AvisarVentaRegistrada implements ShouldQueue
{
    public function handle(VentaRegistrada $evento): void
    {
        // Solo a quien puede ver los reportes: el aviso lleva importes.
        $destinatarios = User::query()
            ->where('is_active', true)
            ->whereKeyNot($evento->venta->user_id)
            ->get()
            ->filter(fn (User $u): bool => $u->can('reportes.ver'));

        if ($destinatarios->isEmpty()) {
            return;
        }

        Notification::send($destinatarios, new VentaRegistradaPush($evento->venta));
    }
}
