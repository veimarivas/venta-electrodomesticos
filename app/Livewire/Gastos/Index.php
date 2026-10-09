<?php

namespace App\Livewire\Gastos;

use App\Models\Gasto;
use App\Models\User;
use App\Support\ArqueoDeCaja;
use App\Support\RegistroDeGastos;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * Gastos de la tienda: comida, fletes, servicios, insumos. Solo el
 * administrador (`gastos.*`).
 *
 * Se listan por día —es como se leen: «¿en qué se fue la plata hoy?»— con su
 * total, por categoría y por método de pago. Cada gasto dice para quién fue
 * (un vendedor, un administrador o la tienda) y lleva su comprobante, que es
 * lo normal cuando se paga por QR.
 */
class Index extends Component
{
    use WithFileUploads;

    /** Día que se mira. */
    public string $fecha = '';

    // ---- Formulario ----------------------------------------------------------

    public ?int $gastoId = null;

    public string $fechaGasto = '';

    public string $concepto = '';

    public string $categoria = 'comida';

    public string $monto = '';

    public string $metodoPago = 'qr';

    public ?int $beneficiarioId = null;

    public string $notas = '';

    /** Sale del cajón del turno abierto (solo efectivo de hoy). */
    public bool $deCaja = true;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $comprobante = null;

    public ?int $archivarId = null;

    public function mount(): void
    {
        $this->autorizar('gastos.ver');
        $this->fecha = now()->toDateString();
    }

    /** @return Collection<int, Gasto> */
    #[Computed]
    public function gastos(): Collection
    {
        return Gasto::query()
            ->with(['beneficiario', 'user'])
            ->delDia($this->fecha)
            ->latest('id')
            ->get();
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function personas(): Collection
    {
        return User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function hayCajaAbierta(): bool
    {
        return app(ArqueoDeCaja::class)->abierta() !== null;
    }

    public function diaAnterior(): void
    {
        $this->fecha = \Illuminate\Support\Carbon::parse($this->fecha)->subDay()->toDateString();
    }

    public function diaSiguiente(): void
    {
        $this->fecha = \Illuminate\Support\Carbon::parse($this->fecha)->addDay()->toDateString();
    }

    public function nuevo(): void
    {
        $this->autorizar('gastos.crear');

        $this->reset(['gastoId', 'concepto', 'monto', 'beneficiarioId', 'notas', 'comprobante']);
        $this->categoria = 'comida';
        $this->metodoPago = 'qr';
        $this->deCaja = true;
        $this->fechaGasto = $this->fecha;
        $this->resetValidation();

        $this->dispatch('abrir-modal-gasto');
    }

    public function editar(int $id): void
    {
        $this->autorizar('gastos.editar');

        $gasto = Gasto::findOrFail($id);

        $this->gastoId = $gasto->id;
        $this->fechaGasto = $gasto->fecha->toDateString();
        $this->concepto = $gasto->concepto;
        $this->categoria = $gasto->categoria;
        $this->monto = number_format((float) $gasto->monto, 2, '.', '');
        $this->metodoPago = $gasto->metodo_pago;
        $this->beneficiarioId = $gasto->beneficiario_id;
        $this->notas = (string) $gasto->notas;
        $this->deCaja = $gasto->caja_id !== null;
        $this->comprobante = null;
        $this->resetValidation();

        $this->dispatch('abrir-modal-gasto');
    }

    public function guardar(): void
    {
        $this->autorizar($this->gastoId ? 'gastos.editar' : 'gastos.crear');

        $this->validate([
            'fechaGasto' => ['required', 'date', 'before_or_equal:today'],
            'concepto' => ['required', 'string', 'min:2', 'max:160'],
            'categoria' => ['required', Rule::in(array_keys(Gasto::CATEGORIAS))],
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'metodoPago' => ['required', Rule::in(array_keys(Gasto::METODOS))],
            'beneficiarioId' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notas' => ['nullable', 'string', 'max:500'],
            'comprobante' => ['nullable', 'image', 'max:5120'],
        ], [
            'fechaGasto.before_or_equal' => 'Un gasto no puede ser de un día que todavía no llegó.',
            'concepto.required' => 'Di en qué se gastó.',
            'monto.min' => 'El monto tiene que ser mayor que cero.',
        ]);

        try {
            app(RegistroDeGastos::class)->guardar(
                [
                    'fecha' => $this->fechaGasto,
                    'concepto' => $this->concepto,
                    'categoria' => $this->categoria,
                    'monto' => $this->monto,
                    'metodo_pago' => $this->metodoPago,
                    'beneficiario_id' => $this->beneficiarioId,
                    'notas' => $this->notas,
                    'de_caja' => $this->deCaja,
                ],
                (int) auth()->id(),
                $this->gastoId ? Gasto::findOrFail($this->gastoId) : null,
                $this->comprobante,
            );
        } catch (RuntimeException $e) {
            $this->addError('monto', $e->getMessage());

            return;
        }

        $this->fecha = $this->fechaGasto;
        unset($this->gastos);

        $this->dispatch('cerrar-modal-gasto');
        $this->dispatch('toast', tipo: 'success', mensaje: $this->gastoId ? 'Gasto corregido.' : 'Gasto registrado.');
    }

    public function confirmarArchivar(int $id): void
    {
        $this->autorizar('gastos.eliminar');

        $this->archivarId = $id;
        $this->dispatch('abrir-modal-archivar-gasto');
    }

    public function archivar(): void
    {
        $this->autorizar('gastos.eliminar');

        if ($this->archivarId !== null && ($gasto = Gasto::find($this->archivarId))) {
            app(RegistroDeGastos::class)->archivar($gasto);
        }

        $this->archivarId = null;
        unset($this->gastos);

        $this->dispatch('cerrar-modal-archivar-gasto');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Gasto archivado: ya no cuenta en el resumen.');
    }

    public function render(): View
    {
        $gastos = $this->gastos;
        $total = (float) $gastos->sum('monto');

        return view('livewire.gastos.index', [
            'total' => $total,
            'porMetodo' => collect(Gasto::METODOS)->map(fn ($etiqueta, $clave) => (float) $gastos->where('metodo_pago', $clave)->sum('monto')),
            'porCategoria' => $gastos->groupBy('categoria')->map(fn ($g) => (float) $g->sum('monto'))->sortDesc(),
        ]);
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }
}
