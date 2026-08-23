<?php

namespace App\Livewire\Proveedores;

use App\Models\Compra;
use App\Models\Proveedor;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Ficha completa de un proveedor y su historial de compras.
 *
 * Es una vista de solo lectura: se llega desde la fila del listado y se puede
 * volver atrás. No modifica datos.
 */
class Show extends Component
{
    public Proveedor $proveedor;

    public function mount(Proveedor $proveedor): void
    {
        $this->proveedor = $proveedor->load('compras.user');
    }

    /**
     * Compras del proveedor de la más reciente a la más antigua.
     *
     * @return \Illuminate\Support\Collection<int, Compra>
     */
    #[Computed]
    public function compras(): \Illuminate\Support\Collection
    {
        return $this->proveedor->compras
            ->loadMissing('detalles.producto')
            ->loadCount(['unidades', 'detalles'])
            ->sortByDesc('fecha_compra')
            ->sortByDesc('id')
            ->values();
    }

    /** Compras registradas del proveedor. */
    #[Computed]
    public function totalCompras(): int
    {
        return $this->compras->count();
    }

    /** Suma de lo invertido en todas sus compras. */
    #[Computed]
    public function totalInvertido(): string
    {
        return number_format((float) $this->compras->sum('total'), 2);
    }

    /** Unidades físicas que generaron sus compras recepcionadas. */
    #[Computed]
    public function totalUnidades(): int
    {
        return (int) $this->compras->sum('unidades_count');
    }

    /** Compras que ya se recepcionaron. */
    #[Computed]
    public function comprasRecepcionadas(): int
    {
        return $this->compras->where('estado', 'recepcionada')->count();
    }

    public function render(): View
    {
        return view('livewire.proveedores.show');
    }
}