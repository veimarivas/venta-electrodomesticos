<?php

namespace App\Notifications;

use App\Models\SolicitudDescuento;
use Illuminate\Notifications\Notification;

/**
 * Aviso al administrador de que un vendedor pidió rebajar del mínimo.
 *
 * Va por dos canales, igual que el aviso de venta:
 *
 *  · `database` — la campana del panel y el historial de la app. Funciona
 *    siempre, sin depender de Firebase.
 *  · `fcm` — el push al teléfono, solo si hay paquete y credenciales.
 *
 * El aviso lleva el importe **pedido** y el **costo**, que es lo que hace falta
 * para decidir de un vistazo; al tocarlo se entra a la bandeja de autorizaciones.
 *
 * **No va encolada a propósito.** A diferencia de la venta —donde el push no
 * debe frenar el cobro—, pedir autorización no es una operación crítica y el
 * aviso tiene que quedar guardado aunque no haya un worker corriendo: sin él,
 * la campana y los avisos de la app se quedarían vacíos.
 */
class SolicitudDeDescuentoPush extends Notification
{
    public function __construct(public readonly SolicitudDescuento $solicitud) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $canales = ['database'];

        if ($this->fcmDisponible() && $notifiable->dispositivos()->exists()) {
            $canales[] = 'fcm';
        }

        return $canales;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $solicitud = $this->solicitud->loadMissing(['producto', 'unidad', 'solicitante']);

        return [
            'tipo' => 'solicitud_descuento',
            'solicitud_id' => $solicitud->id,
            'titulo' => 'Descuento por autorizar',
            'cuerpo' => $this->cuerpo(),
            'precio_lista' => (float) $solicitud->precio_lista,
            'costo_unitario' => (float) $solicitud->costo_unitario,
            'precio_solicitado' => (float) $solicitud->precio_solicitado,
            // Enlace para la campana del panel.
            'url' => route('ventas.autorizaciones.index'),
            // Enlace profundo para la app.
            'enlace' => 'app://autorizaciones',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'titulo' => 'Descuento por autorizar',
            'cuerpo' => $this->cuerpo(),
            'data' => [
                'tipo' => 'solicitud_descuento',
                'solicitud_id' => (string) $this->solicitud->id,
                'enlace' => 'app://autorizaciones',
            ],
        ];
    }

    private function cuerpo(): string
    {
        $solicitud = $this->solicitud;
        $producto = $solicitud->producto?->nombre ?? 'Producto';
        $codigo = $solicitud->unidad?->codigo_interno ?? '';
        $vendedor = $solicitud->solicitante?->name ?? 'un vendedor';

        return $producto.($codigo !== '' ? " · {$codigo}" : '').
            ' · pide '.number_format((float) $solicitud->precio_solicitado, 2, ',', '.').' Bs'.
            ' · '.$vendedor;
    }

    private function fcmDisponible(): bool
    {
        return class_exists(\NotificationChannels\Fcm\FcmChannel::class)
            && filled(config('services.fcm.credentials'));
    }
}
