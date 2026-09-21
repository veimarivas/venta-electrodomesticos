<?php

namespace App\Livewire\Precios;

use App\Support\PreciosDelDia;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Precios del día: fijar el precio de venta de cada producto al empezar la
 * jornada.
 *
 * Se listan los productos **con stock**, cada uno con el precio de la jornada
 * anterior (o el inicial, si nunca se fijó) y un campo para el de hoy. Al
 * guardar, el último precio queda como el que ofrece el punto de venta.
 */
class Index extends Component
{
    /** Precio de hoy por producto: producto_id => texto del campo. */
    public array $precios = [];

    public function mount(): void
    {
        $this->autorizar();

        foreach ($this->revision as $fila) {
            $this->precios[$fila->producto->id] = number_format(
                $fila->precio_hoy ?? $fila->precio_anterior,
                2,
                '.',
                '',
            );
        }
    }

    /**
     * Productos con stock y su precio de referencia.
     *
     * @return Collection<int, object>
     */
    #[Computed]
    public function revision(): Collection
    {
        return app(PreciosDelDia::class)->paraRevisar();
    }

    public function guardar(): void
    {
        $this->autorizar();

        $this->validate([
            'precios' => ['required', 'array', 'min:1'],
            'precios.*' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ], [
            'precios.*.required' => 'Falta el precio de un producto.',
            'precios.*.numeric' => 'El precio debe ser un número.',
            'precios.*.min' => 'El precio debe ser mayor a cero.',
        ]);

        // El precio tiene que superar al costo: vender por debajo sería regalar
        // el aparato.
        $debajo = [];

        foreach ($this->revision as $fila) {
            $precio = (float) ($this->precios[$fila->producto->id] ?? 0);

            if ($fila->costo > 0 && $precio <= $fila->costo) {
                $debajo[] = $fila->producto->nombre;
            }
        }

        if ($debajo !== []) {
            $this->addError('precios', 'Estos productos quedaron en o por debajo de su costo: '.implode(', ', $debajo).'.');

            return;
        }

        $guardados = app(PreciosDelDia::class)->guardar($this->precios, (int) auth()->id());

        // La revisión queda vieja: el precio de hoy ya no es el anterior.
        unset($this->revision);

        $this->dispatch('toast', tipo: 'success', mensaje: "Precios del día guardados ({$guardados} productos).");
    }

    public function render(): View
    {
        return view('livewire.precios.index', [
            'filas' => $this->revision,
        ]);
    }

    private function autorizar(): void
    {
        $usuario = auth()->user();

        abort_unless(
            $usuario !== null && ($usuario->can('caja.gestionar') || $usuario->can('productos.editar')),
            403,
        );
    }
}
