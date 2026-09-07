<?php

namespace App\Livewire\Compras;

use App\Models\PagoCompra;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

/**
 * Historial de pagos a proveedores: cuánto salió de la tienda, con filtros
 * por día, semana o mes. Es el reporte de «¿cuánto le debo a los
 * proveedores?», al revés que la cartera de clientes.
 */
class Pagos extends Component
{
    /** hoy | semana | mes | todas */
    public string $filtro = 'mes';

    public string $buscar = '';

    /** @var \Illuminate\Support\Collection<int, \App\Models\PagoCompra> */
    public $pagos;

    public string $total = '0.00';

    public int $cantidad = 0;

    public function mount(): void
    {
        $this->cargar();
    }

    public function updatedFiltro(): void
    {
        $this->cargar();
    }

    public function updatedBuscar(): void
    {
        $this->cargar();
    }

    public function cargar(): void
    {
        $termino = trim($this->buscar);

        $pagos = PagoCompra::query()
            ->with(['compra.proveedor', 'user'])
            ->when($this->filtro !== 'todas', fn ($q) => $q->whereDate('fecha', '>=', $this->inicioDeRango()))
            ->when($termino !== '', fn ($q) => $q->whereHas('compra', function ($compra) use ($termino) {
                $compra->where('codigo', 'like', "%{$termino}%")
                    ->orWhere('numero_factura', 'like', "%{$termino}%")
                    ->orWhereHas('proveedor', fn ($p) => $p->where('nombre', 'like', "%{$termino}%"));
            }))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        $this->pagos = $pagos;
        $this->total = number_format($pagos->sum(fn (PagoCompra $p) => (float) $p->monto), 2, '.', '');
        $this->cantidad = $pagos->count();
    }

    private function inicioDeRango(): string
    {
        return match ($this->filtro) {
            'hoy' => now()->startOfDay()->toDateString(),
            'semana' => now()->startOfWeek()->toDateString(),
            'mes' => now()->startOfMonth()->toDateString(),
            default => now()->startOfDay()->toDateString(),
        };
    }

    public function urlBoucher(PagoCompra $pago): ?string
    {
        return $pago->imagen ? Storage::disk('public')->url($pago->imagen) : null;
    }

    public function render(): View
    {
        return view('livewire.compras.pagos');
    }
}