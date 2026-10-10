<?php

namespace App\Livewire\Tiendas;

use App\Models\Asistencia;
use App\Models\Tienda;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Tiendas (sucursales): nombre, ubicación, radio para marcar asistencia y hora
 * de entrada. La ubicación se escribe, se pega desde Google Maps o se toma del
 * navegador; la más exacta se fija desde el teléfono estando dentro.
 */
class Index extends Component
{
    public ?int $tiendaId = null;

    public string $nombre = '';

    public string $direccion = '';

    public string $latitud = '';

    public string $longitud = '';

    public int $radio = 30;

    public string $horaEntrada = '';

    public int $tolerancia = 10;

    public bool $activa = true;

    /** Coordenadas pegadas desde Google Maps: «-17.78, -63.18» o un enlace. */
    public string $pegado = '';

    public ?int $eliminarId = null;

    public function mount(): void
    {
        $this->autorizar('tiendas.ver');
    }

    /** @return Collection<int, Tienda> */
    #[Computed]
    public function tiendas(): Collection
    {
        return Tienda::query()
            ->withCount(['asistencias as hoy' => fn ($q) => $q->whereDate('fecha', today())])
            ->orderBy('nombre')
            ->get();
    }

    public function nueva(): void
    {
        $this->autorizar('tiendas.crear');

        $this->reset(['tiendaId', 'nombre', 'direccion', 'latitud', 'longitud', 'horaEntrada', 'pegado']);
        $this->radio = 30;
        $this->tolerancia = 10;
        $this->activa = true;
        $this->resetValidation();

        $this->dispatch('abrir-modal-tienda');
    }

    public function editar(int $id): void
    {
        $this->autorizar('tiendas.editar');

        $t = Tienda::findOrFail($id);

        $this->tiendaId = $t->id;
        $this->nombre = $t->nombre;
        $this->direccion = (string) $t->direccion;
        $this->latitud = $t->latitud === null ? '' : (string) $t->latitud;
        $this->longitud = $t->longitud === null ? '' : (string) $t->longitud;
        $this->radio = $t->radio_metros;
        $this->horaEntrada = (string) $t->horaEntradaCorta();
        $this->tolerancia = $t->tolerancia_minutos;
        $this->activa = $t->activa;
        $this->pegado = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-tienda');
    }

    /**
     * Entiende lo que se copia de Google Maps: «-17.7833, -63.1821», un enlace
     * con «@-17.78,-63.18,17z» o con «?q=-17.78,-63.18».
     */
    public function updatedPegado(): void
    {
        if (preg_match('/(-?\d{1,2}\.\d{3,})\s*,\s*(-?\d{1,3}\.\d{3,})/', $this->pegado, $m)) {
            $this->latitud = $m[1];
            $this->longitud = $m[2];
            $this->resetValidation(['latitud', 'longitud']);
        } elseif (trim($this->pegado) !== '') {
            $this->addError('pegado', 'No se encontraron coordenadas. Copia el punto desde Google Maps (clic derecho sobre la tienda).');
        }
    }

    public function guardar(): void
    {
        $this->autorizar($this->tiendaId ? 'tiendas.editar' : 'tiendas.crear');

        $this->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'latitud' => ['nullable', 'required_with:longitud', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'required_with:latitud', 'numeric', 'between:-180,180'],
            'radio' => ['required', 'integer', 'min:10', 'max:500'],
            'horaEntrada' => ['nullable', 'date_format:H:i'],
            'tolerancia' => ['required', 'integer', 'min:0', 'max:120'],
        ], [
            'nombre.required' => 'Ponle un nombre a la tienda.',
            'radio.min' => 'Como mínimo 10 m.',
            'radio.max' => 'Como máximo 500 m.',
            'latitud.required_with' => 'Falta la latitud.',
            'longitud.required_with' => 'Falta la longitud.',
        ]);

        $datos = [
            'nombre' => trim($this->nombre),
            'direccion' => trim($this->direccion) ?: null,
            'latitud' => $this->latitud === '' ? null : round((float) $this->latitud, 7),
            'longitud' => $this->longitud === '' ? null : round((float) $this->longitud, 7),
            'radio_metros' => $this->radio,
            'hora_entrada' => $this->horaEntrada ?: null,
            'tolerancia_minutos' => $this->tolerancia,
            'activa' => $this->activa,
        ];

        $this->tiendaId
            ? Tienda::findOrFail($this->tiendaId)->update($datos)
            : Tienda::query()->create($datos);

        unset($this->tiendas);

        $this->dispatch('cerrar-modal-tienda');
        $this->dispatch('toast', tipo: 'success', mensaje: $this->tiendaId ? 'Tienda actualizada.' : 'Tienda registrada.');
    }

    public function alternar(int $id): void
    {
        $this->autorizar('tiendas.editar');

        $t = Tienda::findOrFail($id);
        $t->update(['activa' => ! $t->activa]);
        unset($this->tiendas);
    }

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('tiendas.eliminar');

        $this->eliminarId = $id;
        $this->dispatch('abrir-modal-eliminar-tienda');
    }

    public function eliminar(): void
    {
        $this->autorizar('tiendas.eliminar');

        $tienda = $this->eliminarId ? Tienda::find($this->eliminarId) : null;

        if ($tienda !== null) {
            // Con asistencias se archiva (borrado lógico): el historial la
            // sigue nombrando. Sin ellas, también: no hay razón para perderla.
            $tienda->update(['activa' => false]);
            $tienda->delete();
        }

        $this->eliminarId = null;
        unset($this->tiendas);

        $this->dispatch('cerrar-modal-eliminar-tienda');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Tienda quitada. Su historial de asistencia se conserva.');
    }

    public function render(): View
    {
        return view('livewire.tiendas.index', [
            'enTurno' => Asistencia::query()->whereDate('fecha', today())->abiertas()->count(),
        ]);
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }
}
