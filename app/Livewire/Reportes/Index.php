<?php

namespace App\Livewire\Reportes;

use App\Models\Venta;
use App\Support\Reportes;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reportes de ventas y rentabilidad, con el dashboard en vivo.
 *
 * El período se elige con atajos (hoy, semana, mes, año) o con un rango
 * propio. Todo lo pesado vive en App\Support\Reportes, para que la misma
 * lógica sirva luego a la API de la app Flutter.
 */
class Index extends Component
{
    /** hoy | semana | mes | anio | rango */
    public string $periodo = 'mes';

    public string $desde = '';

    public string $hasta = '';

    /**
     * Ventas que llegaron por WebSocket desde que se abrió la pantalla.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $enVivo = [];

    public function mount(): void
    {
        $this->aplicarPeriodo('mes');
    }

    /** Atajos de período. El rango propio los desactiva. */
    public function aplicarPeriodo(string $periodo): void
    {
        $this->periodo = $periodo;

        [$desde, $hasta] = match ($periodo) {
            'hoy' => [now()->startOfDay(), now()->endOfDay()],
            'semana' => [now()->startOfWeek(), now()->endOfWeek()],
            'anio' => [now()->startOfYear(), now()->endOfYear()],
            'rango' => [$this->desdeCarbon(), $this->hastaCarbon()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        $this->desde = $desde->format('Y-m-d');
        $this->hasta = $hasta->format('Y-m-d');

        $this->limpiarCache();
    }

    public function updatedDesde(): void
    {
        $this->periodo = 'rango';
        $this->limpiarCache();
    }

    public function updatedHasta(): void
    {
        $this->periodo = 'rango';
        $this->limpiarCache();
    }

    /**
     * Llega una venta nueva por WebSocket: se apunta en el panel en vivo y se
     * recalculan los totales, sin recargar la página.
     *
     * OJO CON EL PUNTO de `.VentaRegistrada`. Sin él, Echo interpreta el
     * nombre como una clase y escucha `App.Events.VentaRegistrada`, mientras
     * que el servidor emite `VentaRegistrada` a secas (lo que devuelve
     * broadcastAs). El evento llegaba al navegador y nadie lo recogía: el
     * panel se quedaba vacío hasta recargar la página. El punto inicial le
     * dice a Echo que el nombre ya viene completo.
     *
     * @param  array<string, mixed>  $payload
     */
    #[On('echo-private:ventas,.VentaRegistrada')]
    public function alRegistrarseUnaVenta(array $payload): void
    {
        // Se apila al principio y se recorta: el panel es un vistazo de lo que
        // acaba de pasar, no un historial —para eso está /ventas.
        array_unshift($this->enVivo, $payload);
        $this->enVivo = array_slice($this->enVivo, 0, 8);

        $this->limpiarCache();
    }

    /**
     * Las propiedades computadas se cachean por petición; hay que soltarlas
     * cuando cambia el período o entra una venta.
     */
    private function limpiarCache(): void
    {
        unset(
            $this->resumen,
            $this->serie,
            $this->topProductos,
            $this->porVendedor,
            $this->porMetodoPago,
            $this->porProveedor,
            $this->inventario,
        );
    }

    private function desdeCarbon(): Carbon
    {
        return $this->desde !== ''
            ? Carbon::parse($this->desde)->startOfDay()
            : now()->startOfMonth();
    }

    private function hastaCarbon(): Carbon
    {
        $hasta = $this->hasta !== ''
            ? Carbon::parse($this->hasta)->endOfDay()
            : now()->endOfDay();

        // Un rango invertido no devolvería nada y parecería que no hubo
        // ventas; se corrige en silencio a un solo día.
        return $hasta->lessThan($this->desdeCarbon())
            ? $this->desdeCarbon()->copy()->endOfDay()
            : $hasta;
    }

    private function reportes(): Reportes
    {
        return app(Reportes::class);
    }

    // ---- Datos ------------------------------------------------------------

    #[Computed]
    public function resumen(): array
    {
        return $this->reportes()->resumen($this->desdeCarbon(), $this->hastaCarbon());
    }

    #[Computed]
    public function serie()
    {
        return $this->reportes()->porDia($this->desdeCarbon(), $this->hastaCarbon());
    }

    #[Computed]
    public function topProductos()
    {
        return $this->reportes()->topProductos($this->desdeCarbon(), $this->hastaCarbon());
    }

    #[Computed]
    public function porVendedor()
    {
        return $this->reportes()->porVendedor($this->desdeCarbon(), $this->hastaCarbon());
    }

    #[Computed]
    public function porMetodoPago()
    {
        return $this->reportes()->porMetodoPago($this->desdeCarbon(), $this->hastaCarbon());
    }

    #[Computed]
    public function porProveedor()
    {
        return $this->reportes()->rentabilidadPorProveedor();
    }

    #[Computed]
    public function inventario(): array
    {
        return $this->reportes()->inventarioEnStock();
    }

    /** Etiqueta legible del período, para el encabezado. */
    #[Computed]
    public function etiquetaPeriodo(): string
    {
        $desde = $this->desdeCarbon();
        $hasta = $this->hastaCarbon();

        if ($desde->isSameDay($hasta)) {
            return $desde->translatedFormat('d \d\e F \d\e Y');
        }

        return $desde->format('d/m/Y').' — '.$hasta->format('d/m/Y');
    }

    public function render(): View
    {
        // La ganancia solo se muestra a quien puede ver costos.
        return view('livewire.reportes.index', [
            'metodosPago' => Venta::METODOS_PAGO,
            'puedeVerCostos' => auth()->user()?->can('reportes.ver_costos') ?? false,
        ]);
    }
}
