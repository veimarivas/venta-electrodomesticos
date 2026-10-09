<?php

namespace App\Livewire\Reportes;

use App\Support\ResumenDiario as Resumen;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Resumen del día: qué entró y qué salió, y por dónde. Solo el administrador
 * (`reportes.seguimiento`). El cálculo vive en `App\Support\ResumenDiario`,
 * el mismo que sirve la API a la app.
 */
class ResumenDiario extends Component
{
    public string $fecha = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reportes.seguimiento') ?? false, 403);

        $this->fecha = now()->toDateString();
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function resumen(): array
    {
        return app(Resumen::class)->del(Carbon::parse($this->fecha));
    }

    public function updatedFecha(): void
    {
        // Un día futuro no tiene nada que contar.
        if (Carbon::parse($this->fecha)->isFuture()) {
            $this->fecha = now()->toDateString();
        }
    }

    public function diaAnterior(): void
    {
        $this->fecha = Carbon::parse($this->fecha)->subDay()->toDateString();
    }

    public function diaSiguiente(): void
    {
        $siguiente = Carbon::parse($this->fecha)->addDay();

        if (! $siguiente->isFuture()) {
            $this->fecha = $siguiente->toDateString();
        }
    }

    public function render(): View
    {
        return view('livewire.reportes.resumen-diario');
    }
}
