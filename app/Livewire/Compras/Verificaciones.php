<?php

namespace App\Livewire\Compras;

use App\Models\Compra;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * «Por verificar»: las compras que el administrador le asignó a este usuario.
 *
 * Es la única puerta de Compras que tiene el vendedor. Ve las suyas —primero
 * las pendientes, luego las últimas que ya verificó— y nada más: ni las demás
 * compras ni sus costos.
 */
class Verificaciones extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('compras.verificar') ?? false, 403);
    }

    /** @return Collection<int, Compra> */
    #[Computed]
    public function pendientes(): Collection
    {
        return Compra::query()
            ->asignadasA((int) auth()->id())
            ->whereIn('estado', ['borrador', 'pendiente'])
            ->with('proveedor')
            ->withCount('detalles')
            ->withSum('detalles', 'cantidad')
            ->withCount('unidades')
            ->orderBy('asignada_en')
            ->get();
    }

    /** @return Collection<int, Compra> */
    #[Computed]
    public function verificadas(): Collection
    {
        return Compra::query()
            ->asignadasA((int) auth()->id())
            ->where('estado', 'recepcionada')
            ->with('proveedor')
            ->withCount('unidades')
            ->latest('recepcionada_en')
            ->limit(10)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.compras.verificaciones');
    }
}
