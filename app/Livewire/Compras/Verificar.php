<?php

namespace App\Livewire\Compras;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Support\RecepcionDeCompra;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Throwable;

/**
 * Verificar la mercadería de una compra asignada.
 *
 * Lo que ve quien verifica: proveedor, factura, cada producto con cuántos se
 * pidieron y cuántos faltan. **Sin costos ni pagos**: para contar cajas no
 * hacen falta, y el vendedor no tiene por qué saber cuánto costó cada aparato.
 *
 * La recepción puede ser por tandas, como en la app: hoy llegaron 7 de 11, se
 * verifican 7 y la compra sigue pendiente hasta que lleguen los otros 4. Los
 * productos con serial piden el serial de cada aparato; los demás, cuántos
 * llegaron.
 */
class Verificar extends Component
{
    public Compra $compra;

    /** @var array<int, array<int, string>> Seriales por línea (solo los que llevan). */
    public array $seriales = [];

    /** @var array<int, string> Cuántos llegaron, por línea (los que no llevan serial). */
    public array $cantidades = [];

    public function mount(Compra $compra): void
    {
        $usuario = auth()->user();

        // Su compra asignada, o quien administra compras.
        abort_unless($compra->esVerificadaPor($usuario) || ($usuario?->can('compras.crear') ?? false), 403);

        $this->compra = $compra->load(['proveedor', 'verificador']);
        $this->prepararCampos();
    }

    /** @return Collection<int, CompraDetalle> */
    #[Computed]
    public function lineas(): Collection
    {
        return $this->compra->detalles()
            ->with('producto')
            ->withCount('unidades')
            ->orderBy('id')
            ->get();
    }

    /** Cuántos aparatos faltan por recibir en total. */
    #[Computed]
    public function faltan(): int
    {
        return (int) $this->lineas->sum(fn (CompraDetalle $l) => max($l->cantidad - $l->unidades_count, 0));
    }

    /**
     * Un campo vacío por cada aparato con serial que falta, y la cantidad a
     * cero en los demás: nada se da por llegado sin que alguien lo diga.
     */
    private function prepararCampos(): void
    {
        $this->seriales = [];
        $this->cantidades = [];

        foreach ($this->lineas as $linea) {
            $faltan = max($linea->cantidad - $linea->unidades_count, 0);

            if ($faltan === 0) {
                continue;
            }

            if ($linea->producto->tiene_serial) {
                $this->seriales[$linea->id] = array_fill(0, $faltan, '');
            } else {
                $this->cantidades[$linea->id] = '';
            }
        }
    }

    /** Llegó todo lo que faltaba de una línea sin serial. */
    public function llegoTodo(int $lineaId): void
    {
        $linea = $this->lineas->firstWhere('id', $lineaId);

        if ($linea !== null) {
            $this->cantidades[$lineaId] = (string) max($linea->cantidad - $linea->unidades_count, 0);
        }
    }

    public function verificar(): void
    {
        $usuario = auth()->user();

        abort_unless(
            $this->compra->esVerificadaPor($usuario) || ($usuario?->can('compras.crear') ?? false),
            403,
        );

        if (! $this->compra->fresh()->puede_recepcionarse) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Esta compra ya se recepcionó o se anuló.');

            return;
        }

        $this->validate([
            'cantidades.*' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'seriales.*.*' => ['nullable', 'string', 'max:100'],
        ], [
            'cantidades.*.integer' => 'Escribe un número entero.',
            'cantidades.*.min' => 'No puede ser negativo.',
        ]);

        $verificacion = [];

        foreach ($this->lineas as $linea) {
            if ($linea->producto->tiene_serial) {
                $verificacion[$linea->id] = ['seriales' => $this->seriales[$linea->id] ?? []];
            } else {
                $verificacion[$linea->id] = ['cantidad_verificada' => (int) ($this->cantidades[$linea->id] ?? 0)];
            }
        }

        try {
            $generadas = app(RecepcionDeCompra::class)->recepcionar($this->compra->fresh(), $verificacion);
        } catch (Throwable $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        $this->compra->refresh();
        unset($this->lineas, $this->faltan);
        $this->prepararCampos();

        $this->dispatch('toast', tipo: 'success', mensaje: $this->compra->esta_recepcionada
            ? "Listo: {$generadas} aparatos entraron al stock y la compra quedó recepcionada."
            : "{$generadas} aparatos entraron al stock. Faltan {$this->faltan} por llegar.");
    }

    public function render(): View
    {
        return view('livewire.compras.verificar');
    }
}
