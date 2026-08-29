<?php

namespace App\Notifications;

use App\Models\Producto;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Aviso de que un producto acaba de caer a su mínimo de stock.
 *
 * El panel y la app ya listaban el stock bajo, pero había que **ir a mirarlo**.
 * Un listado solo sirve a quien se acuerda de abrirlo; el momento en que hace
 * falta enterarse es justo cuando el aparato sale del almacén, que es cuando
 * todavía se puede reponer antes de quedarse sin nada que vender.
 *
 * Va por los mismos dos canales que el aviso de venta: `database` siempre —el
 * historial que lee la app— y `fcm` solo si Firebase está configurado y el
 * usuario tiene algún teléfono registrado.
 */
class StockBajoPush extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Producto $producto,
        public readonly int $disponibles,
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
     * Historial que lee la app.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'tipo' => 'stock_bajo',
            'producto_id' => $this->producto->id,
            'titulo' => $this->agotado() ? 'Sin stock' : 'Stock bajo',
            'cuerpo' => $this->cuerpo(),
            'disponibles' => $this->disponibles,
            'stock_minimo' => (int) $this->producto->stock_minimo,
            // Enlace profundo a la ficha del producto: desde ahí se ve el
            // detalle y se puede pedir reposición.
            'enlace' => "app://catalogo/productos/{$this->producto->id}",
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toFcm(object $notifiable): array
    {
        return [
            'titulo' => $this->agotado() ? 'Sin stock' : 'Stock bajo',
            'cuerpo' => $this->cuerpo(),
            'data' => [
                'tipo' => 'stock_bajo',
                'producto_id' => (string) $this->producto->id,
                'enlace' => "app://catalogo/productos/{$this->producto->id}",
            ],
        ];
    }

    private function agotado(): bool
    {
        return $this->disponibles === 0;
    }

    /**
     * Se distingue «se acabó» de «queda poco»: son dos urgencias distintas y
     * un aviso que las mezcle obliga a abrirlo para saber cuál es.
     */
    private function cuerpo(): string
    {
        $nombre = $this->producto->nombre;

        if ($this->agotado()) {
            return "{$nombre} se quedó sin unidades disponibles.";
        }

        $quedan = $this->disponibles === 1
            ? 'Queda 1 unidad'
            : "Quedan {$this->disponibles} unidades";

        return "{$nombre}. {$quedan} (mínimo: {$this->producto->stock_minimo}).";
    }

    private function fcmDisponible(): bool
    {
        return class_exists(\NotificationChannels\Fcm\FcmChannel::class)
            && filled(config('services.fcm.credentials'));
    }
}
