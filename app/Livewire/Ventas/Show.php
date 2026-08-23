<?php

namespace App\Livewire\Ventas;

use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public Venta $venta;

    public function mount(Venta $venta): void
    {
        abort_unless(auth()->user()?->can('ventas.ver') ?? false, 403);

        $this->venta = $venta->load([
            'detalles.unidad',
            'detalles.producto.marca',
            'cliente.persona',
            'user',
            'qrCobro',
        ]);
    }

    public function render(): View
    {
        return view('livewire.ventas.show', [
            'estados' => Venta::ESTADOS,
            'metodosPago' => Venta::METODOS_PAGO,
            'puedeVerCostos' => auth()->user()?->can('reportes.ver_costos') ?? false,
        ]);
    }
}
