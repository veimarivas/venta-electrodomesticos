<?php

namespace App\Livewire\Cargos;

use App\Models\Cargo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    /** Livewire usa la paginación de Tailwind si no se le indica el tema. */
    protected string $paginationTheme = 'bootstrap';

    public string $buscar = '';

    public string $ordenarPor = 'nombre';

    public string $direccionOrden = 'asc';

    /** Id del cargo en edición; null significa "registro nuevo". */
    public ?int $cargoId = null;

    /** Cargo pendiente de eliminar. */
    public ?int $eliminarId = null;

    public string $eliminarNombre = '';

    /** Trabajadores vigentes con este cargo (para el aviso de "en uso"). */
    public int $eliminarTrabajadoresActivos = 0;

    /** Total de trabajadores que lo referencian, incluidos los dados de baja. */
    public int $eliminarTrabajadoresTotal = 0;

    // ---- Campos del formulario -------------------------------------------

    public string $nombre = '';

    private const CAMPOS = ['nombre'];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'min:3', 'max:80',
                // Letras, espacios y los signos habituales de un puesto
                // ("Técnico de instalación", "Chofer / repartidor").
                'regex:/^[\p{L}0-9\s\'\-\/\.]+$/u',
                Rule::unique('cargos', 'nombre')->ignore($this->cargoId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nombre.regex' => 'El nombre del cargo solo puede contener letras, números y los signos - / .',
            'nombre.unique' => 'Ya existe un cargo con este nombre.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre del cargo',
        ];
    }

    /**
     * Validación en tiempo real, campo por campo.
     */
    public function updated(string $campo): void
    {
        if (in_array($campo, self::CAMPOS, true)) {
            $this->validateOnly($campo);
        }
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    /**
     * De esto depende que el botón de guardar esté habilitado.
     */
    #[Computed]
    public function formularioValido(): bool
    {
        return Validator::make(
            $this->only(self::CAMPOS),
            $this->rules(),
            $this->messages(),
            $this->validationAttributes()
        )->passes();
    }

    public function ordenar(string $campo): void
    {
        $this->direccionOrden = $this->ordenarPor === $campo && $this->direccionOrden === 'asc'
            ? 'desc'
            : 'asc';

        $this->ordenarPor = $campo;
        $this->resetPage();
    }

    // ---- Alta y edición ---------------------------------------------------

    public function abrirCrear(): void
    {
        $this->autorizar('cargos.crear');

        $this->limpiarFormulario();
        $this->dispatch('abrir-modal-cargo');
    }

    public function abrirEditar(int $id): void
    {
        $this->autorizar('cargos.editar');

        $cargo = Cargo::findOrFail($id);

        $this->cargoId = $cargo->id;
        $this->nombre = (string) $cargo->nombre;

        $this->resetValidation();
        $this->dispatch('abrir-modal-cargo');
    }

    public function guardar(): void
    {
        $this->autorizar($this->cargoId !== null ? 'cargos.editar' : 'cargos.crear');

        $datos = $this->validate();

        if ($this->cargoId !== null) {
            Cargo::findOrFail($this->cargoId)->update($datos);
            $mensaje = 'Cargo actualizado correctamente.';
        } else {
            Cargo::create($datos);
            $mensaje = 'Cargo registrado correctamente.';
        }

        $this->limpiarFormulario();
        $this->dispatch('cerrar-modal-cargo');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    // ---- Eliminación ------------------------------------------------------

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('cargos.eliminar');

        $cargo = Cargo::withCount([
            'trabajadores as trabajadores_activos',
            'trabajadores as trabajadores_total' => fn ($q) => $q->withTrashed(),
        ])->findOrFail($id);

        $this->eliminarId = $cargo->id;
        $this->eliminarNombre = $cargo->nombre;
        $this->eliminarTrabajadoresActivos = $cargo->trabajadores_activos;
        $this->eliminarTrabajadoresTotal = $cargo->trabajadores_total;

        $this->dispatch('abrir-modal-eliminar-cargo');
    }

    public function eliminar(): void
    {
        $this->autorizar('cargos.eliminar');

        if ($this->eliminarId === null) {
            return;
        }

        $cargo = Cargo::withCount([
            'trabajadores as trabajadores_activos',
            'trabajadores as trabajadores_total' => fn ($q) => $q->withTrashed(),
        ])->findOrFail($this->eliminarId);

        // La FK es restrictOnDelete y cuenta TODAS las filas de trabajadores,
        // también las que están dadas de baja (SoftDeletes): aunque el cargo ya
        // no tenga personal vigente, los registros históricos siguen apuntando
        // a él, así que el borrado reventaría igual con un error de base de datos.
        if ($cargo->trabajadores_total > 0) {
            $this->dispatch('cerrar-modal-eliminar-cargo');
            $this->dispatch('toast', tipo: 'error', mensaje: "No se puede eliminar: {$cargo->trabajadores_total} trabajador(es) tienen o tuvieron este cargo.");

            return;
        }

        $cargo->delete();

        $this->reset(['eliminarId', 'eliminarNombre', 'eliminarTrabajadoresActivos', 'eliminarTrabajadoresTotal']);
        $this->resetPage();

        $this->dispatch('cerrar-modal-eliminar-cargo');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Cargo eliminado correctamente.');
    }

    /**
     * Un componente Livewire es un endpoint invocable: el permiso se comprueba
     * aquí además de ocultar los botones en la vista.
     */
    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    public function limpiarFormulario(): void
    {
        $this->reset([...self::CAMPOS, 'cargoId']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $cargos = Cargo::query()
            ->withCount([
                'trabajadores as trabajadores_activos',
                'trabajadores as trabajadores_total' => fn ($q) => $q->withTrashed(),
            ])
            ->when($this->buscar !== '', fn ($q) => $q->where('nombre', 'like', '%'.trim($this->buscar).'%'))
            ->orderBy($this->ordenarPor, $this->direccionOrden)
            // Desempate estable para que no salten filas entre páginas.
            ->orderBy('id')
            ->paginate(10);

        return view('livewire.cargos.index', [
            'cargos' => $cargos,
            'totalCargos' => Cargo::count(),
            'cargosEnUso' => Cargo::whereHas('trabajadores', fn ($q) => $q->withTrashed())->count(),
            'totalAsignaciones' => \App\Models\Trabajador::count(),
        ]);
    }
}
