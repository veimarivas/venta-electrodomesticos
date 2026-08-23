<?php

namespace App\Events;

use App\Models\Venta;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se acaba de registrar una venta.
 *
 * Viaja por WebSocket al dashboard para que los contadores se muevan sin
 * recargar, y más adelante alimentará el push a la app del administrador.
 *
 * El payload es liviano y **explícito**: se arma a mano en vez de mandar el
 * modelo entero. Un `Venta` serializado arrastraría costos y datos del cliente
 * por un canal que escuchan varios usuarios, y crecería sin control cada vez
 * que alguien añada una columna.
 *
 * **ShouldBroadcastNow, no ShouldBroadcast.** Con la versión encolada el
 * broadcast se queda en la tabla `jobs` hasta que alguien levante un
 * `queue:work`, y el dashboard no se entera de nada — que es exactamente lo
 * que pasaba. Un panel "en vivo" que depende de un proceso más para funcionar
 * no está en vivo. El payload son unos cientos de bytes: enviarlo en la misma
 * petición cuesta milisegundos y elimina esa pieza móvil.
 *
 * Se despacha DESPUÉS del commit (ver RegistroDeVenta), así que enviar de
 * inmediato no puede anunciar una venta que acabe revertida.
 */
class VentaRegistrada implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Venta $venta) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('ventas')];
    }

    /**
     * Nombre corto: el cliente escucha `echo-private:ventas,VentaRegistrada`.
     */
    public function broadcastAs(): string
    {
        return 'VentaRegistrada';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $venta = $this->venta->loadMissing(['cliente.persona', 'user', 'detalles.producto']);

        return [
            'id' => $venta->id,
            'codigo' => $venta->codigo,
            'total' => (float) $venta->total,
            'ganancia' => (float) $venta->ganancia,
            'unidades' => $venta->detalles->count(),
            'vendedor' => $venta->user?->name,
            'cliente' => $venta->cliente?->persona?->nombre_completo ?? 'Público general',
            'productos' => $venta->detalles
                ->map(fn ($detalle): ?string => $detalle->producto?->nombre)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'hora' => $venta->vendida_en?->format('H:i'),
        ];
    }
}
