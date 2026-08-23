<?php

namespace App\Notifications;

use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Aviso al administrador de que se registró una venta.
 *
 * Va por dos canales:
 *
 *  · `database` — el historial que consulta GET /api/v1/notificaciones.
 *    Funciona siempre, sin depender de Firebase.
 *  · `fcm` — el push al teléfono. Solo se añade si el paquete está instalado
 *    y hay credenciales; si no, la notificación se guarda igual y no revienta.
 *
 * Esta SÍ va encolada (a diferencia del broadcast del dashboard): un push
 * puede tardar segundos en salir sin que a nadie le importe, y no debe frenar
 * el cobro en el mostrador.
 */
class VentaRegistradaPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Venta $venta) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $canales = ['database'];

        // El canal FCM se activa solo cuando el paquete está instalado Y hay
        // credenciales configuradas. Así el sistema funciona sin Firebase y
        // empieza a enviar push en cuanto se configure, sin tocar código.
        if ($this->fcmDisponible() && $notifiable->dispositivos()->exists()) {
            $canales[] = 'fcm';
        }

        return $canales;
    }

    /**
     * Historial que lee la app.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => 'venta_registrada',
            'venta_id' => $this->venta->id,
            'titulo' => 'Nueva venta',
            'cuerpo' => $this->cuerpo(),
            'total' => (float) $this->venta->total,
            'codigo' => $this->venta->codigo,
            // Enlace profundo para que tocar el aviso abra la venta en la app.
            'enlace' => "app://ventas/{$this->venta->id}",
        ];
    }

    /**
     * Payload del push. Se construye como arreglo plano —lo que espera FCM en
     * `data`— para no acoplar este archivo al paquete de notificaciones.
     *
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'titulo' => 'Nueva venta',
            'cuerpo' => $this->cuerpo(),
            'data' => [
                'tipo' => 'venta_registrada',
                'venta_id' => (string) $this->venta->id,
                'enlace' => "app://ventas/{$this->venta->id}",
            ],
        ];
    }

    private function cuerpo(): string
    {
        $unidades = $this->venta->detalles()->count();

        return "{$this->venta->codigo} · Bs ".number_format((float) $this->venta->total, 2, ',', '.').
            " · {$unidades} ".($unidades === 1 ? 'aparato' : 'aparatos');
    }

    private function fcmDisponible(): bool
    {
        return class_exists(\NotificationChannels\Fcm\FcmChannel::class)
            && filled(config('services.fcm.credentials'));
    }
}
