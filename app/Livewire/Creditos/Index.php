<?php

namespace App\Livewire\Creditos;

use App\Models\Credito;
use App\Models\Cuota;
use App\Support\ProrrateoDeGastos;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * La cartera: quién debe, cuánto y qué vence.
 *
 * El listado existe para responder una sola pregunta —**a quién hay que
 * llamar hoy**—, así que arranca por la mora y no por la fecha de la venta.
 */
class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    /** Cuántos días mira hacia adelante el filtro «por vencer». */
    public const DIAS_PROXIMOS = 7;

    public string $buscar = '';

    /** vigentes · mora · proximos · pagados · anulados · todos */
    public string $filtro = 'vigentes';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('creditos.ver') ?? false, 403);
    }

    public function updatedBuscar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltro(): void
    {
        $this->resetPage();
    }

    /**
     * Saldo total de la cartera viva, en centavos.
     *
     * Se calcula en SQL sobre las cuotas y no cargando los créditos: una
     * tienda con años de historia no cabe en memoria, y este número se pinta
     * en cada carga de la pantalla.
     */
    #[Computed]
    public function carteraEnCentavos(): int
    {
        return $this->sumaPendiente(fn (Builder $q) => $q);
    }

    #[Computed]
    public function moraEnCentavos(): int
    {
        return $this->sumaPendiente(fn (Builder $q) => $q->whereDate('cuotas.vence_en', '<', today()));
    }

    #[Computed]
    public function porVencerEnCentavos(): int
    {
        return $this->sumaPendiente(fn (Builder $q) => $q->whereBetween(
            'cuotas.vence_en',
            [today(), today()->addDays(self::DIAS_PROXIMOS)]
        ));
    }

    /** Clientes distintos con algo pendiente. */
    #[Computed]
    public function clientesConDeuda(): int
    {
        return Credito::query()->vigentes()->distinct()->count('cliente_id');
    }

    /**
     * Suma de lo que falta en las cuotas de los créditos vigentes.
     *
     * @param  callable(Builder): Builder  $acotar
     */
    private function sumaPendiente(callable $acotar): int
    {
        $consulta = Cuota::query()
            ->pendientes()
            ->whereHas('credito', fn (Builder $c) => $c->vigentes());

        $total = $acotar($consulta)->sum(DB::raw('monto - monto_pagado'));

        return ProrrateoDeGastos::aCentavos($total);
    }

    public function render(): View
    {
        $creditos = Credito::query()
            ->with(['cliente.persona', 'venta', 'cuotas'])
            // El saldo se arma en SQL para poder ordenar por él sin traer la
            // cartera entera a PHP.
            ->withSum('cuotas as comprometido', 'monto')
            ->withSum('cuotas as cobrado', 'monto_pagado')
            ->buscar($this->buscar)
            ->when($this->filtro === 'vigentes', fn (Builder $q) => $q->vigentes())
            ->when($this->filtro === 'mora', fn (Builder $q) => $q->enMora())
            ->when($this->filtro === 'proximos', fn (Builder $q) => $q->porVencer(self::DIAS_PROXIMOS))
            ->when($this->filtro === 'pagados', fn (Builder $q) => $q->where('estado', 'pagado'))
            ->when($this->filtro === 'anulados', fn (Builder $q) => $q->where('estado', 'anulado'))
            // Lo urgente arriba: primero los vigentes y, dentro de ellos, el
            // vencimiento más antiguo sin saldar.
            ->orderByRaw("FIELD(estado, 'vigente', 'pagado', 'anulado')")
            ->orderBy(
                Cuota::query()->selectRaw('MIN(vence_en)')
                    ->whereColumn('cuotas.credito_id', 'creditos.id')
                    ->pendientes()
            )
            ->orderByDesc('id')
            ->paginate(10);

        // El cobro vive en la ficha de cada crédito, no aquí: desde el listado
        // no se ve contra qué cuota se estaría imputando el dinero.
        return view('livewire.creditos.index', ['creditos' => $creditos]);
    }
}
