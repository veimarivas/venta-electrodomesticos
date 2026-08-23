<?php

namespace App\Livewire\Marcas;

use App\Models\Marca;
use App\Models\Producto;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Index extends Component
{
    use WithFileUploads, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $buscar = '';

    public string $ordenarPor = 'nombre';

    public string $direccionOrden = 'asc';

    /** Id de la marca en edición; null significa "registro nuevo". */
    public ?int $marcaId = null;

    /** Marca pendiente de eliminar. */
    public ?int $eliminarId = null;

    public string $eliminarNombre = '';

    public int $eliminarProductos = 0;

    // ---- Campos del formulario -------------------------------------------

    public string $nombre = '';

    public string $slug = '';

    /** Logo subido en este mismo formulario (Livewire lo deja en almacenamiento temporal). */
    public $logo = null;

    /** Logo ya persistido, para mostrarlo al editar y poder reemplazarlo. */
    public string $logoActual = '';

    public bool $quitarLogo = false;

    public bool $isActive = true;

    public bool $slugManual = false;

    private const CAMPOS = ['nombre', 'slug', 'logo', 'isActive'];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:100', Rule::unique('marcas', 'nombre')->ignore($this->marcaId)],
            'slug' => [
                'required', 'string', 'regex:/^[a-z0-9\-]+$/',
                Rule::unique('marcas', 'slug')->ignore($this->marcaId),
            ],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'isActive' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe una marca con este nombre.',
            'slug.required' => 'El slug es obligatorio.',
            'slug.regex' => 'El slug solo puede contener minúsculas, números y guiones.',
            'slug.unique' => 'Ya existe una marca con este slug.',
            'logo.image' => 'El archivo debe ser una imagen.',
            'logo.mimes' => 'El logo debe ser JPG, PNG o WebP.',
            'logo.max' => 'El logo no puede pesar más de 2 MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre de la marca',
            'slug' => 'slug',
            'logo' => 'logo',
            'isActive' => 'estado',
        ];
    }

    public function updatedNombre(): void
    {
        if (! $this->slugManual) {
            $this->slug = Str::slug($this->nombre);
        }
    }

    public function updatedSlug(): void
    {
        $this->slugManual = true;
    }

    public function updatedLogo(): void
    {
        $this->resetValidation('logo');
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, self::CAMPOS, true) && $campo !== 'logo') {
            $this->validateOnly($campo);
        }
    }

    public function updatedBuscar(): void
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
        $this->autorizar('marcas.crear');

        $this->limpiarFormulario();
        $this->dispatch('abrir-modal-marca');
    }

    public function abrirEditar(int $id): void
    {
        $this->autorizar('marcas.editar');

        $marca = Marca::findOrFail($id);

        $this->marcaId = $marca->id;
        $this->nombre = $marca->nombre;
        $this->slug = $marca->slug;
        $this->logoActual = (string) $marca->logo_ruta;
        $this->logo = null;
        $this->quitarLogo = false;
        $this->isActive = $marca->activa;
        $this->slugManual = true;

        $this->resetValidation();
        $this->dispatch('abrir-modal-marca');
    }

    public function guardar(): void
    {
        $this->autorizar($this->marcaId !== null ? 'marcas.editar' : 'marcas.crear');

        $validados = $this->validate();

        $datos = [
            'nombre' => $validados['nombre'],
            'slug' => $validados['slug'],
            'activa' => (bool) $this->isActive,
        ];

        if (! $this->slugManual) {
            $datos['slug'] = Str::slug($this->nombre);
        }

        $rutaLogo = $this->logoActual;

        if ($this->logo) {
            $rutaLogo = $this->logo->store('marcas', 'public');
        } elseif ($this->quitarLogo && $this->logoActual !== '') {
            Storage::disk('public')->delete($this->logoActual);
            $rutaLogo = null;
        }

        $datos['logo_ruta'] = $rutaLogo === '' ? null : $rutaLogo;

        if ($this->marcaId !== null) {
            // Al reemplazar el logo se borra el anterior para no dejar basura.
            if ($this->logo && $this->logoActual !== '') {
                Storage::disk('public')->delete($this->logoActual);
            }

            Marca::findOrFail($this->marcaId)->update($datos);
            $mensaje = 'Marca actualizada correctamente.';
        } else {
            Marca::create($datos);
            $mensaje = 'Marca creada correctamente.';
        }

        $this->limpiarFormulario();
        $this->dispatch('cerrar-modal-marca');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    // ---- Eliminación ------------------------------------------------------

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('marcas.eliminar');

        $marca = Marca::withCount('productos')->findOrFail($id);

        $this->eliminarId = $marca->id;
        $this->eliminarNombre = $marca->nombre;
        $this->eliminarProductos = $marca->productos_count;

        $this->dispatch('abrir-modal-eliminar-marca');
    }

    public function eliminar(): void
    {
        $this->autorizar('marcas.eliminar');

        if ($this->eliminarId === null) {
            return;
        }

        $marca = Marca::withCount('productos')->findOrFail($this->eliminarId);

        // La FK es restrictOnDelete: avisar antes de que reviente la base de datos.
        if ($marca->productos_count > 0) {
            $this->dispatch('cerrar-modal-eliminar-marca');
            $this->dispatch('toast', tipo: 'error', mensaje: "No se puede eliminar: {$marca->productos_count} producto(s) usan esta marca.");

            return;
        }

        if ($marca->logo_ruta) {
            Storage::disk('public')->delete($marca->logo_ruta);
        }

        $marca->delete();

        $this->reset(['eliminarId', 'eliminarNombre', 'eliminarProductos']);
        $this->resetPage();

        $this->dispatch('cerrar-modal-eliminar-marca');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Marca eliminada correctamente.');
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    public function limpiarFormulario(): void
    {
        $this->reset([...self::CAMPOS, 'marcaId', 'logoActual', 'quitarLogo']);
        $this->slugManual = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        $marcas = Marca::query()
            ->withCount('productos')
            ->when($this->buscar !== '', fn ($q) => $q->where('nombre', 'like', '%'.trim($this->buscar).'%'))
            ->orderBy($this->ordenarPor, $this->direccionOrden)
            ->orderBy('id')
            ->paginate(10);

        $totales = Marca::selectRaw('count(*) as total, sum(activa) as activas')->first();

        return view('livewire.marcas.index', [
            'marcas' => $marcas,
            'totalMarcas' => (int) $totales->total,
            'activas' => (int) $totales->activas,
            'inactivas' => (int) $totales->total - (int) $totales->activas,
            'totalProductos' => Producto::count(),
        ]);
    }
}
