<?php

namespace App\Livewire\Ventas;

use App\Models\Venta;
use App\Support\RegistroDeVenta;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Historial de ventas.
 *
 * Las ventas no se editan ni se borran: se consultan y, si hubo un error, se
 * anulan. Anular devuelve los aparatos al stock y deja el rastro en el kardex.
 */
class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $buscar = '';

    /** todas | completada | anulada */
    public string $filtroEstado = 'todas';

    public string $desde = '';

    public string $hasta = '';

    public string $ordenarPor = 'vendida_en';

    public string $direccionOrden = 'desc';

    // ---- Anulación --------------------------------------------------------

    public ?int $anularId = null;

    public string $anularCodigo = '';

    public string $motivoAnulacion = '';

    // ---- Recibo -----------------------------------------------------------

    public ?Venta $reciboVenta = null;

    public function mount(): void
    {
        // Por defecto se muestra el mes actual (La Paz) — el filtro arranca
        // en el 1 y hasta el último día; el usuario puede cambiarlo libremente.
        $this->desde = now()->startOfMonth()->format('Y-m-d');
        $this->hasta = now()->endOfMonth()->format('Y-m-d');
    }

    private function desdeCarbon(): \Carbon\CarbonInterface
    {
        return $this->desde !== ''
            ? \Carbon\Carbon::parse($this->desde)->startOfDay()
            : now()->startOfMonth();
    }

    private function hastaCarbon(): \Carbon\CarbonInterface
    {
        $hasta = $this->hasta !== ''
            ? \Carbon\Carbon::parse($this->hasta)->endOfDay()
            : now()->endOfMonth();

        return $hasta->lessThan($this->desdeCarbon())
            ? $this->desdeCarbon()->copy()->endOfDay()
            : $hasta;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            // El motivo es obligatorio: una anulación sin explicación deja el
            // histórico sin poder auditarse.
            'motivoAnulacion' => ['required', 'string', 'min:4', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'motivoAnulacion.required' => 'Explica por qué se anula la venta.',
            'motivoAnulacion.min' => 'El motivo debe explicar algo: al menos 4 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return ['motivoAnulacion' => 'motivo'];
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function updatedDesde(): void
    {
        $this->resetPage();
        $this->limpiarGraficosCache();
    }

    public function updatedHasta(): void
    {
        $this->resetPage();
        $this->limpiarGraficosCache();
    }

    private function limpiarGraficosCache(): void
    {
        unset($this->ingresosPorPago, $this->serieIngresos);
    }

    public function ordenar(string $campo): void
    {
        $this->direccionOrden = $this->ordenarPor === $campo && $this->direccionOrden === 'asc'
            ? 'desc'
            : 'asc';

        $this->ordenarPor = $campo;
        $this->resetPage();
    }

    // =======================================================================
    // Recibo
    // =======================================================================

    public function verRecibo(int $id): void
    {
        $this->autorizar('ventas.ver');

        $this->reciboVenta = Venta::with(['detalles.unidad', 'detalles.producto', 'cliente.persona', 'user', 'qrCobro'])
            ->find($id);

        $this->dispatch('abrir-modal-recibo');
    }

    public function cerrarRecibo(): void
    {
        $this->reciboVenta = null;
        $this->dispatch('cerrar-modal-recibo');
    }

    // =======================================================================
    // Anulación
    // =======================================================================

    public function confirmarAnular(int $id): void
    {
        $this->autorizar('ventas.anular');

        $venta = Venta::findOrFail($id);

        if ($venta->esta_anulada) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Esta venta ya estaba anulada.');

            return;
        }

        $this->anularId = $venta->id;
        $this->anularCodigo = $venta->codigo;
        $this->motivoAnulacion = '';

        $this->resetValidation();
        $this->dispatch('abrir-modal-anular-venta');
    }

    public function anular(): void
    {
        $this->autorizar('ventas.anular');

        if ($this->anularId === null) {
            return;
        }

        $datos = $this->validate();

        $venta = Venta::findOrFail($this->anularId);

        try {
            $devueltas = app(RegistroDeVenta::class)->anular($venta, $datos['motivoAnulacion']);
        } catch (RuntimeException $e) {
            $this->dispatch('cerrar-modal-anular-venta');
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->reset(['anularId', 'anularCodigo', 'motivoAnulacion']);
        $this->resetPage();

        $this->dispatch('cerrar-modal-anular-venta');
        $this->dispatch('toast', tipo: 'success', mensaje: "Venta anulada: {$devueltas} ".
            ($devueltas === 1 ? 'aparato vuelve' : 'aparatos vuelven').' al stock.');
    }

    private function autorizar(string $permiso): void
    {
        abort_unless(auth()->user()?->can($permiso) ?? false, 403);
    }

    // ---- Gráficos de ingresos por método (Efectivo vs QR) ------------------

    /**
     * Ingresos desagregados por canal: efectivo y QR.
     * Las ventas en efectivo o QR suman su total directo;
     * las mixtas suman cada parte en su canal correspondiente
     * (monto_efectivo / monto_qr) para tener el detalle completo.
     *
     * @return array{efectivo: float, qr: float, total: float}
     */
    #[Computed]
    public function ingresosPorPago(): array
    {
        $r = Venta::completadas()
            ->whereBetween('vendida_en', [$this->desdeCarbon(), $this->hastaCarbon()])
            ->selectRaw('coalesce(sum(monto_efectivo),0) as efectivo, coalesce(sum(monto_qr),0) as qr, coalesce(sum(total),0) as total')
            ->first();

        return [
            'efectivo' => (float) ($r->efectivo ?? 0),
            'qr' => (float) ($r->qr ?? 0),
            'total' => (float) ($r->total ?? 0),
        ];
    }

    /**
     * Serie diaria de ingresos por canal dentro del rango filtrado.
     * Para la evolución apilada: cada día con su efectivo y qr.
     *
     * @return \Illuminate\Support\Collection<int, array{fecha: string, etiqueta: string, efectivo: float, qr: float, total: float}>
     */
    #[Computed]
    public function serieIngresos(): \Illuminate\Support\Collection
    {
        $desde = $this->desdeCarbon()->copy()->startOfDay();
        $hasta = $this->hastaCarbon()->copy()->startOfDay();

        $filas = Venta::completadas()
            ->whereBetween('vendida_en', [$this->desdeCarbon(), $this->hastaCarbon()])
            ->selectRaw('DATE(vendida_en) as dia, coalesce(sum(monto_efectivo),0) as efectivo, coalesce(sum(monto_qr),0) as qr, coalesce(sum(total),0) as total')
            ->groupBy('dia')
            ->get()
            ->keyBy('dia');

        $serie = collect();
        $cursor = $desde->copy();

        while ($cursor->lessThanOrEqualTo($hasta)) {
            $clave = $cursor->format('Y-m-d');
            $fila = $filas->get($clave);

            $serie->push([
                'fecha' => $clave,
                'etiqueta' => $cursor->format('d/m'),
                'efectivo' => (float) ($fila->efectivo ?? 0),
                'qr' => (float) ($fila->qr ?? 0),
                'total' => (float) ($fila->total ?? 0),
            ]);

            $cursor->addDay();
        }

        return $serie;
    }

    public function render(): View
    {
        $ventas = Venta::query()
            ->with(['cliente.persona', 'user'])
            ->withCount('detalles')
            ->buscar($this->buscar)
            ->when($this->filtroEstado !== 'todas', fn ($q) => $q->where('estado', $this->filtroEstado))
            ->when($this->desde !== '', fn ($q) => $q->whereDate('vendida_en', '>=', $this->desde))
            ->when($this->hasta !== '', fn ($q) => $q->whereDate('vendida_en', '<=', $this->hasta))
            ->orderBy($this->ordenarPor, $this->direccionOrden)
            // Desempate estable para que no salten filas entre páginas.
            ->orderBy('id')
            ->paginate(10);

        // Los totales solo cuentan ventas completadas: una anulada no es
        // ingreso, y sumarla inflaría todos los indicadores.
        $totales = Venta::completadas()
            ->selectRaw('count(*) as cantidad, sum(total) as ingreso, sum(ganancia) as ganancia')
            ->first();

        $hoy = Venta::completadas()
            ->whereDate('vendida_en', today())
            ->selectRaw('count(*) as cantidad, sum(total) as ingreso')
            ->first();

        return view('livewire.ventas.index', [
            'ventas' => $ventas,
            'estados' => Venta::ESTADOS,
            'metodosPago' => Venta::METODOS_PAGO,
            'totalVentas' => (int) $totales->cantidad,
            'ingresoTotal' => (float) $totales->ingreso,
            'gananciaTotal' => (float) $totales->ganancia,
            'ventasHoy' => (int) $hoy->cantidad,
            'ingresoHoy' => (float) $hoy->ingreso,
            'anuladas' => Venta::where('estado', 'anulada')->count(),
            'puedeVerCostos' => auth()->user()?->can('reportes.ver_costos') ?? false,
        ]);
    }
}
