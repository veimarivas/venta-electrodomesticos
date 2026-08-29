<?php

namespace App\Livewire\Dashboard;

use App\Models\Producto;
use App\Models\Unidad;
use App\Models\Venta;
use App\Support\Reportes;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel principal.
 *
 * Antes era una plantilla estática con ceros escritos a mano y un hueco de
 * ApexCharts que nunca recibía datos. Ahora lee de App\Support\Reportes, la
 * misma fuente que la pantalla de reportes, y se actualiza en vivo con las
 * ventas que llegan por WebSocket.
 */
class Panel extends Component
{
    /**
     * Ventas llegadas por WebSocket desde que se abrió el panel.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $enVivo = [];

    private function reportes(): Reportes
    {
        return app(Reportes::class);
    }

    /**
     * Llega una venta nueva: se apunta arriba y se recalculan los totales.
     *
     * El punto de `.VentaRegistrada` es obligatorio: sin él Echo escucharía
     * `App.Events.VentaRegistrada` y el evento pasaría de largo. Ver la nota
     * larga en App\Livewire\Reportes\Index.
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('echo-private:ventas,.VentaRegistrada')]
    public function alRegistrarseUnaVenta(array $payload): void
    {
        array_unshift($this->enVivo, $payload);
        $this->enVivo = array_slice($this->enVivo, 0, 5);

        unset($this->hoy, $this->semana, $this->mes, $this->serie, $this->ultimasVentas, $this->topProductos);
    }

    #[Computed]
    public function hoy(): array
    {
        return $this->reportes()->resumen(now()->startOfDay(), now()->endOfDay());
    }

    #[Computed]
    public function semana(): array
    {
        return $this->reportes()->resumen(now()->startOfWeek(), now()->endOfWeek());
    }

    #[Computed]
    public function mes(): array
    {
        return $this->reportes()->resumen(now()->startOfMonth(), now()->endOfMonth());
    }

    /** Últimos 14 días: cabe en la tarjeta sin apretar las barras. */
    #[Computed]
    public function serie()
    {
        return $this->reportes()->porDia(now()->subDays(13)->startOfDay(), now()->endOfDay());
    }

    #[Computed]
    public function topProductos()
    {
        return $this->reportes()->topProductos(now()->startOfMonth(), now()->endOfMonth(), 5);
    }

    /**
     * @return Collection<int, Venta>
     */
    #[Computed]
    public function ultimasVentas(): Collection
    {
        return Venta::query()
            ->with(['user', 'cliente.persona'])
            ->withCount('detalles')
            ->completadas()
            ->orderByDesc('vendida_en')
            ->orderByDesc('id')
            ->limit(6)
            ->get();
    }

    /**
     * Productos cuyo stock disponible cayó a su mínimo o por debajo. Es la
     * alerta que hace accionable el panel: sin ella habría que ir producto por
     * producto para descubrir qué falta reponer.
     *
     * @return Collection<int, Producto>
     */
    #[Computed]
    public function bajoMinimo(): Collection
    {
        return Producto::query()
            ->with('marca')
            ->where('activo', true)
            ->where('stock_minimo', '>', 0)
            ->withCount(['unidades as disponibles' => fn ($q) => $q->disponibles()])
            ->get()
            ->filter(fn (Producto $p): bool => $p->disponibles <= $p->stock_minimo)
            ->sortBy('disponibles')
            ->take(6)
            ->values();
    }

    #[Computed]
    public function inventario(): array
    {
        return $this->reportes()->inventarioEnStock();
    }

    public function render(): View
    {
        return view('livewire.dashboard.panel', [
            // Los importes del panel van tras el mismo permiso que su
            // equivalente en la API (GET /api/v1/dashboard/*). Sin esto, un
            // vendedor veía la caja del día aquí aunque la app se la negase.
            'puedeVerReportes' => auth()->user()?->can('reportes.ver') ?? false,
            'puedeVerCostos' => auth()->user()?->can('reportes.ver_costos') ?? false,
            'puedeVerVentas' => auth()->user()?->can('ventas.ver') ?? false,
            'unidadesEnStock' => Unidad::disponibles()->count(),
        ]);
    }
}
