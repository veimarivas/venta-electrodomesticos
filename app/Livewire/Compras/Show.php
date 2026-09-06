<?php

namespace App\Livewire\Compras;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\PagoCompra;
use App\Models\Unidad;
use App\Models\VentaDetalle;
use App\Support\ProrrateoDeGastos;
use App\Support\RecepcionDeCompra;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class Show extends Component
{
    use WithFileUploads;

    public Compra $compra;

    public bool $mostrarRecepcion = false;

    /** @var array<int, string[]>  Seriales por id de línea. */
    public array $seriales = [];

    /** @var array<int, bool>  Verificación (check) por id de línea. */
    public array $verificadas = [];

    // ---- Pagos ------------------------------------------------------------

    public bool $mostrarPago = false;

    public string $monto = '';

    public string $fecha = '';

    public string $notasPago = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $boucher = null;

    public function mount(Compra $compra): void
    {
        abort_unless(auth()->user()?->can('compras.ver') ?? false, 403);

        $this->compra = $compra->load(['proveedor', 'detalles.producto', 'user', 'pagos.user']);
    }

    #[Computed]
    public function lineas(): Collection
    {
        return $this->compra->detalles->load('producto');
    }

    public function abrirRecepcion(): void
    {
        $this->autorizar('compras.crear');

        // Carga el estado inicial por línea: los que llevan serial, tantos
        // campos vacíos como cantidad; los demás, sin marcar.
        $this->seriales = [];
        $this->verificadas = [];

        foreach ($this->lineas as $linea) {
            if ($linea->producto->tiene_serial) {
                $this->seriales[$linea->id] = array_fill(0, $linea->cantidad, '');
            } else {
                $this->verificadas[$linea->id] = false;
            }
        }

        $this->mostrarRecepcion = true;
    }

    /**
     * Recepciona la compra con la verificación: seriales de los que los llevan
     * y confirmación de los demás. Solo así entran las unidades al stock.
     */
    public function recepcionar(): void
    {
        $this->autorizar('compras.crear');

        if (! $this->compra->puede_recepcionarse) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Esta compra ya se recepcionó o se anuló.');

            return;
        }

        $verificacion = [];

        foreach ($this->lineas as $linea) {
            if ($linea->producto->tiene_serial) {
                $verificacion[$linea->id] = ['seriales' => $this->seriales[$linea->id] ?? []];
            } else {
                $verificacion[$linea->id] = ['verificada' => (bool) ($this->verificadas[$linea->id] ?? false)];
            }
        }

        try {
            app(RecepcionDeCompra::class)->recepcionar($this->compra->fresh(), $verificacion);

            $this->mostrarRecepcion = false;
            $this->compra = $this->compra->fresh()->load([
                'proveedor', 'detalles.producto', 'user', 'pagos.user',
            ]);

            unset($this->lineas, $this->unidades, $this->resumenUnidades, $this->rentabilidad);

            $this->dispatch('toast', tipo: 'success', mensaje: 'Compra recepcionada: las unidades entraron al stock.');
        } catch (Throwable $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());
        }
    }

    // ---- Pagos ------------------------------------------------------------

    public function abrirPago(): void
    {
        $this->autorizar('compras.crear');

        $this->reset(['monto', 'notasPago', 'boucher']);
        $this->fecha = now()->toDateString();
        $this->mostrarPago = true;
    }

    public function guardarPago(): void
    {
        $this->autorizar('compras.crear');

        $this->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'fecha' => ['required', 'date'],
            'boucher' => ['nullable', 'image', 'max:5120'],
            'notasPago' => ['nullable', 'string', 'max:500'],
        ]);

        $pago = $this->compra->pagos()->create([
            'user_id' => auth()->id(),
            'monto' => $this->monto,
            'fecha' => $this->fecha,
            'imagen' => $this->boucher
                ? $this->boucher->store('comprobantes-compra', 'public')
                : null,
            'notas' => trim($this->notasPago) !== '' ? trim($this->notasPago) : null,
        ]);

        $this->mostrarPago = false;
        $this->compra->unsetRelation('pagos');
        $this->compra->load('pagos.user');

        $this->reset(['monto', 'fecha', 'notasPago', 'boucher']);

        $this->dispatch('toast', tipo: 'success', mensaje: 'Pago registrado con su boucher.');
    }

    public function eliminarPago(int $pagoId): void
    {
        $this->autorizar('compras.crear');

        $pago = PagoCompra::find($pagoId);

        if ($pago === null || $pago->compra_id !== $this->compra->id) {
            return;
        }

        if ($pago->imagen) {
            Storage::disk('public')->delete($pago->imagen);
        }

        $pago->delete();

        $this->compra->unsetRelation('pagos');
        $this->compra->load('pagos.user');

        $this->dispatch('toast', tipo: 'success', mensaje: 'Pago eliminado.');
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    #[Computed]
    public function unidades(): Collection
    {
        return Unidad::query()
            ->with('producto')
            ->where('compra_id', $this->compra->id)
            ->orderBy('producto_id')
            ->orderBy('id')
            ->get();
    }

    #[Computed]
    public function unidadesPorProducto(): array
    {
        return $this->unidades
            ->groupBy(fn (Unidad $u) => $u->producto->nombre ?? 'Sin producto')
            ->map(fn ($grupo, $nombre) => [
                'nombre' => $nombre,
                'unidades' => $grupo,
                'total' => $grupo->count(),
                'en_stock' => $grupo->where('estado', 'en_stock')->count(),
                'vendidas' => $grupo->where('estado', 'vendido')->count(),
            ])
            ->values()
            ->toArray();
    }

    #[Computed]
    public function resumenUnidades(): array
    {
        $unidades = $this->unidades;

        return [
            'total' => $unidades->count(),
            'en_stock' => $unidades->where('estado', 'en_stock')->count(),
            'vendidas' => $unidades->where('estado', 'vendido')->count(),
            'reservadas' => $unidades->where('estado', 'reservado')->count(),
            'danadas' => $unidades->where('estado', 'danado')->count(),
            'garantia' => $unidades->where('estado', 'garantia')->count(),
        ];
    }

    #[Computed]
    public function rentabilidad(): array
    {
        if (! $this->compra->esta_recepcionada) {
            return [];
        }

        $unidades = $this->compra->unidades()->get();
        $vendidas = $unidades->where('estado', 'vendido');
        $enStock = $unidades->where('estado', 'en_stock');

        $centavos = fn ($valor) => ProrrateoDeGastos::aCentavos($valor);

        $lineasVendidas = VentaDetalle::query()
            ->whereIn('unidad_id', $vendidas->pluck('id'))
            ->whereHas('venta', fn ($v) => $v->where('estado', 'completada'))
            ->get();

        $inversion = $centavos($this->compra->total);
        $ingreso = (int) $lineasVendidas->sum(
            fn (VentaDetalle $l) => $centavos($l->precio_unitario) - $centavos($l->descuento)
        );
        $costoVendidas = (int) $lineasVendidas->sum(fn (VentaDetalle $l) => $centavos($l->costo_unitario));
        $potencial = (int) $enStock->sum(fn ($i) => $centavos($i->precio_venta) - $centavos($i->costo_unitario));

        return [
            'inversion' => ProrrateoDeGastos::aDecimal($inversion),
            'unidades' => $unidades->count(),
            'vendidas' => $vendidas->count(),
            'en_stock' => $enStock->count(),
            'ingreso' => ProrrateoDeGastos::aDecimal($ingreso),
            'ganancia' => ProrrateoDeGastos::aDecimal($ingreso - $costoVendidas),
            'potencial' => ProrrateoDeGastos::aDecimal($potencial),
            'recuperado' => $inversion > 0 ? round($ingreso / $inversion * 100, 1) : 0,
            'margen' => $ingreso > 0 ? round(($ingreso - $costoVendidas) / $ingreso * 100, 1) : 0,
        ];
    }

    public function render(): View
    {
        return view('livewire.compras.show');
    }
}