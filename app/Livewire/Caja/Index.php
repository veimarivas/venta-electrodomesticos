<?php

namespace App\Livewire\Caja;

use App\Models\Caja;
use App\Support\ArqueoDeCaja;
use App\Support\ProrrateoDeGastos;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;
use RuntimeException;

/**
 * Abrir y cerrar el turno de caja, y repasar los cierres anteriores.
 *
 * Dos permisos distintos porque son dos trabajos distintos: quien está en el
 * mostrador **gestiona** su turno; quien supervisa **ve** el histórico de
 * cierres de todos y sus diferencias.
 */
class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    /** Importes como cadena para conservar lo que se teclea. */
    public string $montoInicial = '';

    public string $montoDeclarado = '';

    public string $notas = '';

    public function mount(): void
    {
        abort_unless($this->puedeVer() || $this->puedeGestionar(), 403);
    }

    private function puedeGestionar(): bool
    {
        return auth()->user()?->can('caja.gestionar') ?? false;
    }

    private function puedeVer(): bool
    {
        return auth()->user()?->can('caja.ver') ?? false;
    }

    private function arqueo(): ArqueoDeCaja
    {
        return app(ArqueoDeCaja::class);
    }

    public function abrir(): void
    {
        abort_unless($this->puedeGestionar(), 403);

        $this->validate(
            ['montoInicial' => ['required', 'numeric', 'min:0', 'max:99999999']],
            [
                'montoInicial.required' => 'Escribe con cuánto empieza la caja.',
                'montoInicial.numeric' => 'El fondo tiene que ser un número.',
                'montoInicial.min' => 'El fondo no puede ser negativo.',
            ]
        );

        try {
            $this->arqueo()->abrir(
                auth()->id(),
                $this->montoInicial,
                $this->notas !== '' ? $this->notas : null
            );
        } catch (RuntimeException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->reset(['montoInicial', 'notas']);
        $this->dispatch('cerrar-modal-abrir-caja');
        $this->dispatch('toast', tipo: 'success', mensaje: 'Caja abierta.');
    }

    /**
     * Abre el diálogo de cierre.
     *
     * El importe contado se deja **en blanco** a propósito: si el sistema
     * propusiera lo esperado, cerrar sería darle a aceptar y el arqueo dejaría
     * de comparar dos números para comparar uno consigo mismo.
     */
    public function confirmarCierre(): void
    {
        abort_unless($this->puedeGestionar(), 403);

        $this->montoDeclarado = '';
        $this->resetValidation();

        $this->dispatch('abrir-modal-cerrar-caja');
    }

    public function cerrar(): void
    {
        abort_unless($this->puedeGestionar(), 403);

        $this->validate(
            ['montoDeclarado' => ['required', 'numeric', 'min:0', 'max:99999999']],
            [
                'montoDeclarado.required' => 'Escribe cuánto contaste en el cajón.',
                'montoDeclarado.numeric' => 'Lo contado tiene que ser un número.',
                'montoDeclarado.min' => 'Lo contado no puede ser negativo.',
            ]
        );

        $caja = $this->arqueo()->abierta();

        if ($caja === null) {
            $this->dispatch('cerrar-modal-cerrar-caja');
            $this->dispatch('toast', tipo: 'error', mensaje: 'No hay ninguna caja abierta.');

            return;
        }

        try {
            $cerrada = $this->arqueo()->cerrar(
                $caja,
                auth()->id(),
                $this->montoDeclarado,
                $this->notas !== '' ? $this->notas : null
            );
        } catch (RuntimeException $e) {
            $this->dispatch('cerrar-modal-cerrar-caja');
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->reset(['montoDeclarado', 'notas']);
        $this->dispatch('cerrar-modal-cerrar-caja');

        // El mensaje dice el resultado, no solo que se hizo: un faltante tiene
        // que verse en el momento, no al abrir el histórico mañana.
        $diferencia = (float) $cerrada->diferencia;

        $this->dispatch(
            'toast',
            tipo: $diferencia === 0.0 ? 'success' : 'warning',
            mensaje: match (true) {
                $diferencia === 0.0 => 'Caja cerrada y cuadrada.',
                $diferencia > 0 => 'Caja cerrada. Sobran Bs '.number_format($diferencia, 2, ',', '.').'.',
                default => 'Caja cerrada. Faltan Bs '.number_format(abs($diferencia), 2, ',', '.').'.',
            }
        );
    }

    public function render(): View
    {
        $abierta = $this->arqueo()->abierta();

        if ($abierta !== null) {
            $abierta->loadMissing('abiertaPor');
        }

        // El histórico es de quien supervisa. Al cajero se le enseña su turno
        // y nada más: los descuadres de sus compañeros no son asunto suyo.
        $cierres = $this->puedeVer()
            ? Caja::query()
                ->cerradas()
                ->with(['abiertaPor', 'cerradaPor'])
                ->withCount('ventas')
                ->latest('cerrada_en')
                ->paginate(10)
            : null;

        return view('livewire.caja.index', [
            'abierta' => $abierta,
            'cierres' => $cierres,
            'puedeGestionar' => $this->puedeGestionar(),
            'puedeVer' => $this->puedeVer(),
            // Lo que el sistema cree que debería haber. Se enseña solo con
            // permiso de ver: al cajero se le pide contar, no comparar.
            'esperado' => $abierta !== null && $this->puedeVer()
                ? ProrrateoDeGastos::aDecimal($this->arqueo()->esperadoEnCentavos($abierta))
                : null,
            'ventasDelTurno' => $abierta?->ventas()->completadas()->count() ?? 0,
            'sueltas' => $abierta !== null ? $this->arqueo()->ventasSueltas($abierta) : 0,
        ]);
    }
}
