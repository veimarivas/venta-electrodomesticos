<?php

namespace App\Livewire\Sistema;

use App\Models\AccesoBiometrico;
use App\Support\Ajustes;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Seguridad de la app del teléfono, en Usuarios: tras cuántos minutos sin uso
 * se cierra la sesión y qué teléfonos entran con huella (y quitarlos: un
 * teléfono perdido o de alguien que ya no trabaja aquí).
 */
class SesionApp extends Component
{
    public int $minutos = 10;

    public function mount(Ajustes $ajustes): void
    {
        abort_unless(auth()->user()?->can('ajustes.editar') ?? false, 403);

        $this->minutos = $ajustes->inactividadMinutos();
    }

    public function updatedMinutos(Ajustes $ajustes): void
    {
        if (! in_array((int) $this->minutos, Ajustes::OPCIONES_INACTIVIDAD, true)) {
            $this->minutos = $ajustes->inactividadMinutos();

            return;
        }

        $ajustes->fijarInactividad((int) $this->minutos, (int) auth()->id());

        $this->dispatch('toast', tipo: 'success',
            mensaje: "La app cerrará la sesión tras {$this->minutos} minutos sin uso. Los teléfonos lo toman al volver a abrirla.");
    }

    public function quitar(int $id): void
    {
        $acceso = AccesoBiometrico::query()->with('user:id,name')->find($id);

        if ($acceso === null) {
            return;
        }

        $acceso->delete();
        unset($this->accesos);

        $this->dispatch('toast', tipo: 'success',
            mensaje: "El teléfono de {$acceso->user?->name} ya no entra con huella: la próxima vez le pedirá la contraseña.");
    }

    /** @return Collection<int, AccesoBiometrico> */
    #[Computed]
    public function accesos(): Collection
    {
        return AccesoBiometrico::query()
            ->with('user:id,name')
            ->latest('ultimo_uso_en')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.sistema.sesion-app', ['opciones' => Ajustes::OPCIONES_INACTIVIDAD]);
    }
}
