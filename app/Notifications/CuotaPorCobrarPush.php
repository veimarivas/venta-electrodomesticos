<?php

namespace App\Notifications;

use App\Models\Cuota;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Aviso de una cuota que vence hoy o que acaba de vencerse.
 *
 * La cartera ya se puede consultar en pantalla, pero un listado solo sirve a
 * quien se acuerda de abrirlo. Una cuota que nadie mira se convierte en mora
 * en silencio, y la deuda se cobra mejor el día que vence que tres meses
 * después.
 *
 * Mismos dos canales que los demás avisos: `database` siempre —el historial
 * que lee la app— y `fcm` solo si Firebase está configurado y el usuario tiene
 * algún teléfono registrado.
 */
class CuotaPorCobrarPush extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'hoy'|'vencida'  $motivo
     */
    public function __construct(
        public readonly Cuota $cuota,
        public readonly string $motivo,
    ) {}

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
            'tipo' => 'cuota_por_cobrar',
            'cuota_id' => $this->cuota->id,
            'credito_id' => $this->cuota->credito_id,
            'titulo' => $this->titulo(),
            'cuerpo' => $this->cuerpo(),
            'monto' => $this->cuota->falta,
            'vence_en' => $this->cuota->vence_en->toDateString(),
            'enlace' => "app://ventas/creditos/{$this->cuota->credito_id}",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'titulo' => $this->titulo(),
            'cuerpo' => $this->cuerpo(),
            'data' => [
                'tipo' => 'cuota_por_cobrar',
                'credito_id' => (string) $this->cuota->credito_id,
                'enlace' => "app://ventas/creditos/{$this->cuota->credito_id}",
            ],
        ];
    }

    /**
     * «Vence hoy» y «se venció» son dos urgencias distintas: la primera se
     * resuelve con una llamada y la segunda ya es cobranza. Mezclarlas
     * obligaría a abrir el aviso para saber cuál es.
     */
    private function titulo(): string
    {
        return $this->motivo === 'hoy' ? 'Cuota vence hoy' : 'Cuota vencida';
    }

    private function cuerpo(): string
    {
        $cliente = $this->cuota->credito?->cliente?->persona?->nombre_completo ?? 'Un cliente';
        $monto = number_format((float) $this->cuota->falta, 2, ',', '.');

        return $this->motivo === 'hoy'
            ? "{$cliente} debe Bs {$monto} de la cuota {$this->cuota->numero}, que vence hoy."
            : "{$cliente} no pagó Bs {$monto} de la cuota {$this->cuota->numero}, vencida el ".
                $this->cuota->vence_en->format('d/m/Y').'.';
    }

    private function fcmDisponible(): bool
    {
        return class_exists(\NotificationChannels\Fcm\FcmChannel::class)
            && filled(config('services.fcm.credentials'));
    }
}
