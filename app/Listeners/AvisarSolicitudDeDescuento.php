<?php

namespace App\Listeners;

use App\Events\SolicitudDeDescuentoCreada;
use App\Models\User;
use App\Notifications\SolicitudDeDescuentoPush;
use Illuminate\Support\Facades\Notification;

/**
 * Avisa a quien puede autorizar descuentos de que hay una solicitud nueva.
 *
 * **No encolado, a diferencia del aviso de venta.** Vender es urgente y el push
 * no debe frenar el cobro; pedir autorización no lo es, y aquí pesa más que el
 * aviso quede guardado de inmediato —campana del panel y avisos de la app— sin
 * depender de que haya un worker corriendo.
 */
class AvisarSolicitudDeDescuento
{
    public function handle(SolicitudDeDescuentoCreada $evento): void
    {
        $destinatarios = User::query()
            ->where('is_active', true)
            // Quien la pidió no se avisa a sí mismo.
            ->whereKeyNot($evento->solicitud->user_id)
            ->get()
            ->filter(fn (User $u): bool => $u->can('ventas.autorizar_descuento'));

        if ($destinatarios->isEmpty()) {
            return;
        }

        Notification::send($destinatarios, new SolicitudDeDescuentoPush($evento->solicitud));
    }
}
