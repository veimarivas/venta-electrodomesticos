<?php

namespace App\Notifications;

use App\Models\Compra;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Aviso al vendedor de que tiene una compra por verificar.
 *
 * Sin aviso, la asignación solo serviría si el vendedor entra a mirar «Por
 * verificar» por su cuenta, y la mercadería se queda en la puerta. Va por los
 * mismos canales que los demás avisos: `database` (el historial que lee la app
 * y la campana del panel) y `fcm` cuando hay Firebase.
 */
class CompraAsignadaPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Compra $compra) {}

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
        return [
            'tipo' => 'compra_asignada',
            'compra_id' => $this->compra->id,
            'titulo' => 'Compra por verificar',
            'cuerpo' => $this->cuerpo(),
            'enlace' => "app://compras/verificar/{$this->compra->id}",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'titulo' => 'Compra por verificar',
            'cuerpo' => $this->cuerpo(),
            'data' => [
                'tipo' => 'compra_asignada',
                'compra_id' => (string) $this->compra->id,
                'enlace' => "app://compras/verificar/{$this->compra->id}",
            ],
        ];
    }

    private function cuerpo(): string
    {
        $proveedor = $this->compra->proveedor?->nombre;

        return "Te asignaron la compra {$this->compra->codigo}"
            .($proveedor ? " de {$proveedor}" : '')
            .' para verificar la mercadería.';
    }

    private function fcmDisponible(): bool
    {
        return class_exists(\NotificationChannels\Fcm\FcmChannel::class)
            && filled(config('services.fcm.credentials'));
    }
}
