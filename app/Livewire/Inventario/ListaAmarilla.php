<?php

namespace App\Livewire\Inventario;

use App\Support\Ajustes;
use App\Support\ListaAmarilla as Lista;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Lista amarilla del panel: aparatos que llevan demasiado tiempo en la
 * tienda, contados a la fecha de hoy. El cálculo vive en `ListaAmarilla`.
 */
class ListaAmarilla extends Component
{
    /** Días en tienda desde los que se lista; arranca en el umbral guardado. */
    #[Url(as: 'dias')]
    public int $dias = 0;

    #[Url(as: 'q')]
    public string $buscar = '';

    /** Producto con sus aparatos desplegados. */
    public ?int $abierto = null;

    /** Para cambiar el umbral guardado (solo `ajustes.editar`). */
    public int $umbralNuevo = 0;

    public function mount(Ajustes $ajustes): void
    {
        abort_unless(auth()->user()?->can('unidades.ver') ?? false, 403);

        $this->umbralNuevo = $ajustes->listaAmarillaDias();

        if ($this->dias < 1) {
            $this->dias = $this->umbralNuevo;
        }
    }

    public function updatedDias(): void
    {
        $this->dias = max(1, min(3650, (int) $this->dias));
        $this->abierto = null;
        unset($this->resultado);
    }

    public function updatedBuscar(): void
    {
        unset($this->resultado);
    }

    public function alternar(?int $productoId): void
    {
        $this->abierto = $this->abierto === $productoId ? null : $productoId;
    }

    public function guardarUmbral(Ajustes $ajustes): void
    {
        abort_unless(auth()->user()?->can('ajustes.editar') ?? false, 403);

        $this->validate(['umbralNuevo' => ['required', 'integer', 'min:30', 'max:1095']], [
            'umbralNuevo.min' => 'Como mínimo 30 días.',
            'umbralNuevo.max' => 'Como máximo 3 años.',
        ]);

        $ajustes->fijarListaAmarillaDias($this->umbralNuevo, (int) auth()->id());
        $this->dias = $this->umbralNuevo;
        unset($this->resultado);

        $this->dispatch('toast', tipo: 'success', mensaje: "La lista amarilla empieza ahora a los {$this->umbralNuevo} días.");
    }

    /** @return array<string, mixed> */
    #[Computed]
    public function resultado(): array
    {
        return app(Lista::class)->consultar(
            $this->dias,
            $this->buscar,
            auth()->user()?->can('reportes.ver_costos') ?? false,
        );
    }

    public function render(Ajustes $ajustes): View
    {
        return view('livewire.inventario.lista-amarilla', [
            'umbral' => $ajustes->listaAmarillaDias(),
            'verCostos' => auth()->user()?->can('reportes.ver_costos') ?? false,
        ]);
    }
}
