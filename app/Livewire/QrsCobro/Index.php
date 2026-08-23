<?php

namespace App\Livewire\QrsCobro;

use App\Models\QrCobro;
use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * CRUD de los QR de cobro que el POS muestra al cliente.
 *
 * Un QR bancario es una imagen con fecha de caducidad. El módulo existe para
 * que el cajero no tenga que buscarla en el celular del dueño cada vez: se
 * registra una vez, el POS la muestra mientras esté vigente y deja de
 * ofrecerla sola el día que vence.
 */
class Index extends Component
{
    use WithFileUploads, WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $buscar = '';

    /** vigentes | caducados | todos */
    public string $filtroEstado = 'vigentes';

    /** Id del QR en edición; null significa "registro nuevo". */
    public ?int $qrId = null;

    public ?int $eliminarId = null;

    public string $eliminarNombre = '';

    // ---- Campos del formulario -------------------------------------------

    public string $nombre = '';

    public string $banco = '';

    public string $titular = '';

    /** Imagen subida en este formulario (Livewire la deja en temporal). */
    public $imagen = null;

    /** Imagen ya guardada, para poder reemplazarla al editar. */
    public string $imagenActual = '';

    public string $fechaLimite = '';

    public bool $isActive = true;

    public string $notas = '';

    private const CAMPOS = ['nombre', 'banco', 'titular', 'fechaLimite', 'isActive', 'notas'];

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:2', 'max:100'],
            'banco' => ['nullable', 'string', 'max:100'],
            'titular' => ['nullable', 'string', 'max:150'],
            // Obligatoria solo al crear: al editar, no subir nada significa
            // conservar la imagen que ya tiene.
            'imagen' => [$this->qrId === null ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            // Se admite registrar un QR ya caducado (queda archivado), pero el
            // POS no lo ofrece: la vigencia la decide scopeVigentes.
            'fechaLimite' => ['required', 'date', 'after_or_equal:2020-01-01'],
            'isActive' => ['boolean'],
            'notas' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nombre.required' => 'Ponle un nombre reconocible al QR.',
            'imagen.required' => 'Sube la imagen del QR.',
            'imagen.image' => 'El archivo debe ser una imagen.',
            'imagen.mimes' => 'La imagen debe ser JPG, PNG o WebP.',
            'imagen.max' => 'La imagen no puede pesar más de 3 MB.',
            'fechaLimite.required' => 'Indica hasta qué día sirve este QR.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'nombre' => 'nombre',
            'banco' => 'banco',
            'titular' => 'titular',
            'imagen' => 'imagen del QR',
            'fechaLimite' => 'fecha límite',
            'isActive' => 'estado',
            'notas' => 'notas',
        ];
    }

    public function updated(string $campo): void
    {
        if (in_array($campo, self::CAMPOS, true)) {
            $this->validateOnly($campo);
        }
    }

    public function updatedImagen(): void
    {
        $this->resetValidation('imagen');
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
        $datos = $this->only(self::CAMPOS);
        $datos['imagen'] = $this->imagen;

        return Validator::make(
            $datos,
            $this->rules(),
            $this->messages(),
            $this->validationAttributes()
        )->passes();
    }

    // ---- Alta y edición ---------------------------------------------------

    public function abrirCrear(): void
    {
        $this->autorizar('qrs_cobro.crear');

        $this->limpiarFormulario();

        // Un mes es el plazo habitual de los QR del banco; se puede cambiar.
        $this->fechaLimite = now()->addMonth()->toDateString();

        $this->dispatch('abrir-modal-qr');
    }

    public function abrirEditar(int $id): void
    {
        $this->autorizar('qrs_cobro.editar');

        $qr = QrCobro::findOrFail($id);

        $this->qrId = $qr->id;
        $this->nombre = $qr->nombre;
        $this->banco = (string) $qr->banco;
        $this->titular = (string) $qr->titular;
        $this->imagenActual = (string) $qr->imagen;
        $this->imagen = null;
        $this->fechaLimite = $qr->fecha_limite?->toDateString() ?? '';
        $this->isActive = $qr->activo;
        $this->notas = (string) $qr->notas;

        $this->resetValidation();
        $this->dispatch('abrir-modal-qr');
    }

    public function guardar(): void
    {
        $this->autorizar($this->qrId !== null ? 'qrs_cobro.editar' : 'qrs_cobro.crear');

        $validados = $this->validate();

        $datos = [
            'nombre' => $validados['nombre'],
            'banco' => $validados['banco'] === '' ? null : $validados['banco'],
            'titular' => $validados['titular'] === '' ? null : $validados['titular'],
            'fecha_limite' => $validados['fechaLimite'],
            'activo' => (bool) $this->isActive,
            'notas' => $validados['notas'] === '' ? null : $validados['notas'],
        ];

        if ($this->imagen) {
            $datos['imagen'] = $this->imagen->store('qrs-cobro', 'public');
        }

        if ($this->qrId !== null) {
            $qr = QrCobro::findOrFail($this->qrId);

            // Al reemplazar la imagen se borra la anterior para no dejar basura.
            if ($this->imagen && $this->imagenActual !== '') {
                Storage::disk('public')->delete($this->imagenActual);
            }

            $qr->update($datos);
            $mensaje = 'QR de cobro actualizado.';
        } else {
            QrCobro::create($datos);
            $mensaje = 'QR de cobro registrado.';
        }

        $this->limpiarFormulario();
        $this->dispatch('cerrar-modal-qr');
        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    /** Atajo del listado: activar o desactivar sin abrir el formulario. */
    public function alternarActivo(int $id): void
    {
        $this->autorizar('qrs_cobro.editar');

        $qr = QrCobro::findOrFail($id);
        $qr->update(['activo' => ! $qr->activo]);

        $this->dispatch('toast', tipo: 'success', mensaje: $qr->activo
            ? "«{$qr->nombre}» vuelve a ofrecerse en el punto de venta."
            : "«{$qr->nombre}» ya no se ofrece en el punto de venta.");
    }

    // ---- Eliminación ------------------------------------------------------

    public function confirmarEliminar(int $id): void
    {
        $this->autorizar('qrs_cobro.eliminar');

        $qr = QrCobro::findOrFail($id);

        $this->eliminarId = $qr->id;
        $this->eliminarNombre = $qr->nombre;

        $this->dispatch('abrir-modal-eliminar-qr');
    }

    /**
     * Archivar, no borrar: las ventas cobradas con este QR lo referencian y la
     * imagen es parte del respaldo de ese cobro. Por eso tampoco se borra el
     * archivo del disco.
     */
    public function eliminar(): void
    {
        $this->autorizar('qrs_cobro.eliminar');

        if ($this->eliminarId === null) {
            return;
        }

        QrCobro::findOrFail($this->eliminarId)->delete();

        $this->reset(['eliminarId', 'eliminarNombre']);
        $this->resetPage();

        $this->dispatch('cerrar-modal-eliminar-qr');
        $this->dispatch('toast', tipo: 'success', mensaje: 'QR archivado. Las ventas que lo usaron conservan su respaldo.');
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    public function limpiarFormulario(): void
    {
        $this->reset([...self::CAMPOS, 'qrId', 'imagen', 'imagenActual']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $qrs = QrCobro::query()
            ->buscar($this->buscar)
            ->when($this->filtroEstado === 'vigentes', fn ($q) => $q->vigentes())
            ->when($this->filtroEstado === 'caducados', fn ($q) => $q->where(
                fn ($s) => $s->where('activo', false)->orWhereDate('fecha_limite', '<', now()->toDateString())
            ))
            ->withCount('ventas')
            // Los que caducan antes, primero: son los que hay que renovar.
            ->orderBy('fecha_limite')
            ->orderBy('id')
            ->paginate(10);

        return view('livewire.qrs-cobro.index', [
            'qrs' => $qrs,
            'totalQrs' => QrCobro::count(),
            'vigentes' => QrCobro::vigentes()->count(),
            'porVencer' => QrCobro::vigentes()
                ->whereDate('fecha_limite', '<=', now()->addDays(7)->toDateString())
                ->count(),
            'ventasConQr' => Venta::whereNotNull('qr_cobro_id')->count(),
        ]);
    }
}
