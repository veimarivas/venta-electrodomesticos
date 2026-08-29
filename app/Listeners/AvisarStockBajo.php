<?php

namespace App\Listeners;

use App\Events\VentaRegistrada;
use App\Models\Producto;
use App\Models\User;
use App\Notifications\StockBajoPush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

/**
 * Avisa cuando una venta deja un producto en su mínimo de stock o por debajo.
 *
 * Se engancha a la venta porque es lo que consume el almacén: es el momento en
 * que la información sirve para algo, y evita tener que repasar el catálogo
 * entero cada noche buscando qué se agotó.
 *
 * Encolado, como el aviso de venta: enviar push tarda y no debe frenar el cobro.
 */
class AvisarStockBajo implements ShouldQueue
{
    public function handle(VentaRegistrada $evento): void
    {
        $cruzaron = $this->productosQueAcabanDeCaer($evento);

        if ($cruzaron->isEmpty()) {
            return;
        }

        // Quien repone es quien mira el almacén, no quien mira los importes:
        // el permiso es `stock.ver`, no `reportes.ver`. Y el aviso no lleva
        // dinero, solo cuántas unidades quedan.
        $destinatarios = User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $u): bool => $u->can('stock.ver'));

        if ($destinatarios->isEmpty()) {
            return;
        }

        foreach ($cruzaron as $fila) {
            Notification::send(
                $destinatarios,
                new StockBajoPush($fila['producto'], $fila['disponibles'])
            );
        }
    }

    /**
     * Productos de esta venta que **acaban de cruzar** un umbral.
     *
     * La condición no es «está bajo mínimo» sino «lo está ahora y no lo estaba
     * antes de esta venta». La diferencia importa: sin ella, cada venta de un
     * producto ya agotado volvería a avisar, y a la tercera nadie mira los
     * avisos. El estado anterior se reconstruye sumando lo que salió en esta
     * venta a lo que queda.
     *
     * Hay **dos umbrales, no uno**: el mínimo y el cero. Quedarse sin nada que
     * vender es más grave que rozar el mínimo, y con un solo umbral no se
     * avisaría nunca —para cuando llega a cero ya cruzó el mínimo antes, así
     * que la guarda de «no repetir» se lo tragaría—.
     *
     * @return \Illuminate\Support\Collection<int, array{producto: Producto, disponibles: int}>
     */
    private function productosQueAcabanDeCaer(VentaRegistrada $evento)
    {
        // Cuántas unidades salieron de cada producto en esta venta.
        $vendidas = $evento->venta->detalles()
            ->selectRaw('producto_id, count(*) as salieron')
            ->groupBy('producto_id')
            ->pluck('salieron', 'producto_id');

        if ($vendidas->isEmpty()) {
            return collect();
        }

        return Producto::query()
            ->whereIn('id', $vendidas->keys())
            ->where('activo', true)
            // Un mínimo de 0 significa «no lo controlo»: avisar de eso sería
            // ruido en cada venta de cualquier accesorio.
            ->where('stock_minimo', '>', 0)
            ->withCount(['unidades as disponibles' => fn ($q) => $q->disponibles()])
            ->get()
            ->map(fn (Producto $p): array => [
                'producto' => $p,
                'disponibles' => (int) $p->disponibles,
                'antes' => (int) $p->disponibles + (int) $vendidas[$p->id],
            ])
            ->filter(function (array $f): bool {
                $minimo = (int) $f['producto']->stock_minimo;

                $cayoAlMinimo = $f['disponibles'] <= $minimo && $f['antes'] > $minimo;
                $seAgoto = $f['disponibles'] === 0 && $f['antes'] > 0;

                return $cayoAlMinimo || $seAgoto;
            })
            ->values();
    }
}
