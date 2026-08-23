<?php

namespace App\Livewire\Compras;

use App\Models\Compra;
use App\Models\Unidad;
use App\Models\VentaDetalle;
use App\Support\ProrrateoDeGastos;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Show extends Component
{
    public Compra $compra;

    public function mount(Compra $compra): void
    {
        abort_unless(auth()->user()?->can('compras.ver') ?? false, 403);

        $this->compra = $compra->load(['proveedor', 'detalles.producto', 'user']);
    }

    #[Computed]
    public function lineas(): Collection
    {
        return $this->compra->detalles->load('producto');
    }

    #[Computed]
    public function unidades(): Collection
    {
        return Unidad::query()
            ->with('producto')
            ->where('compra_id', $this->compra->id)
            ->orderBy('producto_id')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function unidadesPorProducto(): array
    {
        return $this->unidades
            ->groupBy(fn (Unidad $u) => $u->producto->nombre ?? 'Sin producto')
            ->map(fn ($grupo, $nombre) => [
                'nombre' => $nombre,
                'sku' => $grupo->first()->producto->sku ?? '',
                'unidades' => $grupo,
                'total' => $grupo->count(),
                'en_stock' => $grupo->where('estado', 'en_stock')->count(),
                'vendidas' => $grupo->where('estado', 'vendido')->count(),
            ])
            ->values()
            ->toArray();
    }

    #[Computed]
    public function resumenUnidades(): array
    {
        $unidades = $this->unidades;

        return [
            'total' => $unidades->count(),
            'en_stock' => $unidades->where('estado', 'en_stock')->count(),
            'vendidas' => $unidades->where('estado', 'vendido')->count(),
            'reservadas' => $unidades->where('estado', 'reservado')->count(),
            'danadas' => $unidades->where('estado', 'danado')->count(),
            'garantia' => $unidades->where('estado', 'garantia')->count(),
        ];
    }

    #[Computed]
    public function rentabilidad(): array
    {
        if (! $this->compra->esta_recepcionada) {
            return [];
        }

        $unidades = $this->compra->unidades()->get();
        $vendidas = $unidades->where('estado', 'vendido');
        $enStock = $unidades->where('estado', 'en_stock');

        $centavos = fn ($valor) => ProrrateoDeGastos::aCentavos($valor);

        $lineasVendidas = VentaDetalle::query()
            ->whereIn('unidad_id', $vendidas->pluck('id'))
            ->whereHas('venta', fn ($v) => $v->where('estado', 'completada'))
            ->get();

        $inversion = $centavos($this->compra->total);
        $ingreso = (int) $lineasVendidas->sum(
            fn (VentaDetalle $l) => $centavos($l->precio_unitario) - $centavos($l->descuento)
        );
        $costoVendidas = (int) $lineasVendidas->sum(fn (VentaDetalle $l) => $centavos($l->costo_unitario));
        $potencial = (int) $enStock->sum(fn ($i) => $centavos($i->precio_venta) - $centavos($i->costo_unitario));

        return [
            'inversion' => ProrrateoDeGastos::aDecimal($inversion),
            'unidades' => $unidades->count(),
            'vendidas' => $vendidas->count(),
            'en_stock' => $enStock->count(),
            'ingreso' => ProrrateoDeGastos::aDecimal($ingreso),
            'ganancia' => ProrrateoDeGastos::aDecimal($ingreso - $costoVendidas),
            'potencial' => ProrrateoDeGastos::aDecimal($potencial),
            'recuperado' => $inversion > 0 ? round($ingreso / $inversion * 100, 1) : 0,
            'margen' => $ingreso > 0 ? round(($ingreso - $costoVendidas) / $ingreso * 100, 1) : 0,
        ];
    }

    public function render(): View
    {
        return view('livewire.compras.show');
    }
}