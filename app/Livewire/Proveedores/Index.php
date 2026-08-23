<?php

namespace App\Livewire\Proveedores;

use App\Models\Proveedor;
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

    /** todos | activos | inactivos */
    public string $filtroEstado = 'todos';

    public ?int $proveedorId = null;

    public ?int $eliminarId = null;

    public string $eliminarNombre = '';

    public int $eliminarCompras = 0;

    // ---- Campos del formulario -------------------------------------------

    public string $nombre = '';

    public string $nit = '';

    public string $contacto = '';

    public string $telefono = '';

    public string $correo = '';

    public string $direccion = '';

    public string $notas = '';

    public bool $activo = true;

    private const CAMPOS = [
        'nombre', 'nit', 'contacto', 'telefono', 'correo', 'direccion', 'notas', 'activo',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'nit' => [
                'nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z\-]+$/',
                Rule::unique('proveedores', 'nit')->ignore($this->proveedorId)->whereNull('deleted_at'),
            ],
            'contacto' => ['nullable', 'string', 'min:3', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s\-]{7,30}$/'],
            'correo' => ['nullable', 'email:rfc', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nit.regex' => 'El NIT solo puede contener números, letras y guiones.',
            'nit.unique' => 'Ya existe un proveedor con este NIT.',
            'telefono.regex' => 'El teléfono solo puede contener números, espacios, guiones y el signo +.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre',
            'nit' => 'NIT',
            'contacto' => 'nombre de contacto',
            'telefono' => 'teléfono',
            'correo' => 'correo',
            'direccion' => 'dirección',
            'notas' => 'notas',
        ];
    }

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

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

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
        $this->autorizar('proveedores.crear');

        $this->limpiarFormulario();
        $this->dispatch('abrir-modal-proveedor');
    }

    public function abrirEditar(int $id): void
    {
        $this->autorizar('proveedores.editar');

        $proveedor = Proveedor::findOrFail($id);

        $this->proveedorId = $proveedor->id;
        $this->nombre = (string) $proveedor->nombre;
        $this->nit = (string) $proveedor->nit;
        $this->contacto = (string) $proveedor->contacto;
        $this->telefono = (string) $proveedor->telefono;
        $this->correo = (string) $proveedor->correo;
        $this->direccion = (string) $proveedor->direccion;
        $this->notas = (string) $proveedor->notas;
        $this->activo = (bool) $proveedor->activo;

        $this->resetValidation();
        $this->dispatch('abrir-modal-proveedor');
    }

    public function guardar(): void
    {
        $this->autorizar($this->proveedorId !== null ? 'proveedores.editar' : 'proveedores.crear');

        $datos = $this->validate();

        // Los opcionales vacíos van como NULL: dos proveedores sin NIT
        // chocarían contra el índice único si se guardara cadena vacía.
        foreach (['nit', 'contacto', 'telefono', 'correo', 'direccion', 'notas'] as $campo) {
            $datos[$campo] = $datos[$campo] === '' ? null : $datos[$campo];
        }

        if ($this->proveedorId !== null) {
            Proveedor::findOrFail($this->proveedorId)->update($datos);
            $mensaje = 'Proveedor actualizado correctamente.';
        } else {
            Proveedor::create($datos);
            $mensaje = 'Proveedor registrado correctamente.';
        }

        $this->limpiarFormulario();
        $this->dispatch('cerrar-modal-proveedor');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    public function alternarEstado(int $id): void
    {
        $this->autorizar('proveedores.editar');

        $proveedor = Proveedor::findOrFail($id);
        $proveedor->update(['activo' => ! $proveedor->activo]);

        $this->dispatch('toast', tipo: 'success', mensaje: $proveedor->activo
            ? "{$proveedor->nombre} fue reactivado."
            : "{$proveedor->nombre} ya no aparecerá al registrar compras.");
    }

    // ---- Eliminación ------------------------------------------------------

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('proveedores.eliminar');

        $proveedor = Proveedor::withCount('compras')->findOrFail($id);

        $this->eliminarId = $proveedor->id;
        $this->eliminarNombre = $proveedor->nombre;
        $this->eliminarCompras = $proveedor->compras_count;

        $this->dispatch('abrir-modal-eliminar-proveedor');
    }

    public function eliminar(): void
    {
        $this->autorizar('proveedores.eliminar');

        if ($this->eliminarId === null) {
            return;
        }

        $proveedor = Proveedor::withCount('compras')->findOrFail($this->eliminarId);

        // La FK es restrictOnDelete: un proveedor con compras registradas
        // dejaría el histórico de costos sin origen.
        if ($proveedor->compras_count > 0) {
            $this->dispatch('cerrar-modal-eliminar-proveedor');
            $this->dispatch('toast', tipo: 'error', mensaje: "No se puede eliminar: tiene {$proveedor->compras_count} compra(s) registrada(s). Desactívalo en su lugar.");

            return;
        }

        $proveedor->delete();

        $this->reset(['eliminarId', 'eliminarNombre', 'eliminarCompras']);
        $this->resetPage();

        $this->dispatch('cerrar-modal-eliminar-proveedor');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Proveedor eliminado correctamente.');
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    public function limpiarFormulario(): void
    {
        $this->reset([...self::CAMPOS, 'proveedorId']);
        $this->activo = true;
        $this->resetValidation();
    }

    public function render(): View
    {
        $proveedores = Proveedor::query()
            ->withCount('compras')
            ->buscar($this->buscar)
            ->when($this->filtroEstado === 'activos', fn ($q) => $q->where('activo', true))
            ->when($this->filtroEstado === 'inactivos', fn ($q) => $q->where('activo', false))
            ->orderBy($this->ordenarPor, $this->direccionOrden)
            // Desempate estable para que no salten filas entre páginas.
            ->orderBy('id')
            ->paginate(10);

        return view('livewire.proveedores.index', [
            'proveedores' => $proveedores,
            'totalProveedores' => Proveedor::count(),
            'totalActivos' => Proveedor::where('activo', true)->count(),
            'conCompras' => Proveedor::has('compras')->count(),
        ]);
    }
}
