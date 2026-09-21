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

    /** Buscador de la lista. */
    public string $buscar = '';

    /** Deja solo los que todavía no tienen precio de hoy. */
    public bool $soloPendientes = false;

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
     * Productos con stock y su precio de referencia. Es la lista completa: la
     * validación y el guardado la recorren entera aunque la vista filtre.
     *
     * @return Collection<int, object>
     */
    #[Computed]
    public function revision(): Collection
    {
        return app(PreciosDelDia::class)->paraRevisar();
    }

    /**
     * La lista tal como se ve: acotada por el buscador y por «solo pendientes».
     *
     * @return Collection<int, object>
     */
    #[Computed]
    public function filtradas(): Collection
    {
        $termino = mb_strtolower(trim($this->buscar));

        return $this->revision
            ->filter(function ($fila) use ($termino): bool {
                if ($this->soloPendientes && $fila->precio_hoy !== null) {
                    return false;
                }

                if ($termino === '') {
                    return true;
                }

                return str_contains(mb_strtolower($fila->producto->nombre), $termino)
                    || str_contains(mb_strtolower((string) $fila->producto->categoria?->nombre), $termino);
            })
            ->values();
    }

    /** Cuántos ya tienen precio de hoy. */
    #[Computed]
    public function fijados(): int
    {
        return $this->revision->whereNotNull('precio_hoy')->count();
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
            'filas' => $this->filtradas,
            'total' => $this->revision->count(),
            'fijados' => $this->fijados,
            'pendientes' => $this->revision->count() - $this->fijados,
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
