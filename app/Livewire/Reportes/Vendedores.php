<?php

namespace App\Livewire\Reportes;

use App\Models\User;
use App\Models\Venta;
use App\Support\SeguimientoDeVendedores;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Ventas por vendedor: qué vendió cada uno, cuántas unidades, cuánto rebajó y
 * cuánto cobró por encima de la lista. Solo el administrador
 * (`reportes.seguimiento`). El cálculo vive en `SeguimientoDeVendedores`.
 */
class Vendedores extends Component
{
    /** hoy | semana | mes | rango */
    public string $periodo = 'hoy';

    public string $desde = '';

    public string $hasta = '';

    public ?int $vendedorId = null;

    /** Vendedor con el detalle abierto. */
    public ?int $abierto = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reportes.seguimiento') ?? false, 403);

        $this->aplicarPeriodo();
    }

    public function updatedPeriodo(): void
    {
        $this->aplicarPeriodo();
    }

    private function aplicarPeriodo(): void
    {
        [$desde, $hasta] = match ($this->periodo) {
            'semana' => [now()->startOfWeek(), now()],
            'mes' => [now()->startOfMonth(), now()],
            'rango' => [Carbon::parse($this->desde ?: now()), Carbon::parse($this->hasta ?: now())],
            default => [now(), now()],
        };

        $this->desde = $desde->toDateString();
        $this->hasta = $hasta->toDateString();
    }

    public function updatedDesde(): void
    {
        $this->periodo = 'rango';
    }

    public function updatedHasta(): void
    {
        $this->periodo = 'rango';
    }

    public function alternar(?int $vendedorId): void
    {
        $this->abierto = $this->abierto === $vendedorId ? null : $vendedorId;
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function vendedores(): array
    {
        $desde = Carbon::parse($this->desde);
        $hasta = Carbon::parse($this->hasta);

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return app(SeguimientoDeVendedores::class)->entre($desde, $hasta, $this->vendedorId);
    }

    /**
     * Quienes vendieron alguna vez, para el filtro.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function opciones(): Collection
    {
        return User::query()
            ->whereIn('id', Venta::query()->select('user_id')->distinct())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function render(): View
    {
        $filas = collect($this->vendedores);

        return view('livewire.reportes.vendedores', [
            'filas' => $filas,
            'totales' => [
                'total' => (float) $filas->sum('total'),
                'unidades' => (int) $filas->sum('unidades'),
                'descuento' => (float) $filas->sum('descuento'),
                'sobreprecio' => (float) $filas->sum('sobreprecio'),
            ],
        ]);
    }
}
