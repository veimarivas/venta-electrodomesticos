<?php

namespace App\Livewire\Precios;

use App\Support\PreciosDelDia;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Precios del día: confirmar el precio de venta de cada producto al empezar la
 * jornada.
 *
 * Se listan los productos **con stock**, cada uno con el precio de la jornada
 * anterior (o el inicial, si nunca se fijó) ya escrito: confirmar sin tocar
 * nada mantiene el de ayer. Si entró mercadería con otro costo, la fila trae
 * una sugerencia que **no se aplica sola**: el precio solo cambia si alguien la
 * acepta y confirma. Al confirmar, el último precio queda como el que ofrece el
 * punto de venta.
 */
class Index extends Component
{
    /** Precio de hoy por producto: producto_id => texto del campo. */
    public array $precios = [];

    /** Buscador de la lista. */
    public string $buscar = '';

    /** todos | pendientes | sugerencias | cambiados */
    public string $filtro = 'todos';

    public function mount(): void
    {
        $this->autorizar();
        $this->rellenar();

        // Si hay sugerencias, se abre por ellas: es lo que hay que decidir hoy.
        if ($this->sugerencias > 0) {
            $this->filtro = 'sugerencias';
        }
    }

    /**
     * Productos con stock y su precio de referencia. Es la lista completa: la
     * validación y el guardado la recorren entera aunque la vista filtre.
     *
     * @return Collection<int, object>
     */
    #[Computed]
    public function revision(): Collection
    {
        return app(PreciosDelDia::class)->paraRevisar();
    }

    /**
     * La lista tal como se ve: acotada por el buscador y por el filtro.
     *
     * @return Collection<int, object>
     */
    #[Computed]
    public function filtradas(): Collection
    {
        $termino = mb_strtolower(trim($this->buscar));

        return $this->revision
            ->filter(function ($fila) use ($termino): bool {
                $pasa = match ($this->filtro) {
                    'pendientes' => $fila->precio_hoy === null,
                    'sugerencias' => $fila->sugerencia !== null,
                    'cambiados' => $this->cambio($fila) !== 0.0,
                    default => true,
                };

                if (! $pasa) {
                    return false;
                }

                if ($termino === '') {
                    return true;
                }

                return str_contains(mb_strtolower($fila->producto->nombre), $termino)
                    || str_contains(mb_strtolower((string) $fila->producto->categoria?->nombre), $termino);
            })
            ->values();
    }

    /** Cuántos ya tienen precio de hoy. */
    #[Computed]
    public function fijados(): int
    {
        return $this->revision->whereNotNull('precio_hoy')->count();
    }

    /** Cuántos productos tienen una sugerencia por compra nueva. */
    #[Computed]
    public function sugerencias(): int
    {
        return $this->revision->whereNotNull('sugerencia')->count();
    }

    /**
     * Al corregir un precio se quita su marca roja: si no, la fila seguiría
     * diciendo «debe superar el costo» con un precio que ya lo supera.
     */
    public function updatedPrecios(mixed $valor, string $productoId): void
    {
        $this->resetErrorBag(['precios.'.$productoId, 'precios']);
    }

    /** Pone en el campo el precio que sugiere la compra nueva. */
    public function aplicarSugerencia(int $productoId): void
    {
        $fila = $this->revision->first(fn ($f): bool => $f->producto->id === $productoId);

        if ($fila?->sugerencia !== null) {
            $this->precios[$productoId] = $this->texto($fila->sugerencia->precio_sugerido);
            $this->updatedPrecios(null, (string) $productoId);
        }
    }

    /** Aplica todas las sugerencias de una vez. */
    public function aplicarSugerencias(): void
    {
        foreach ($this->revision as $fila) {
            if ($fila->sugerencia !== null) {
                $this->precios[$fila->producto->id] = $this->texto($fila->sugerencia->precio_sugerido);
            }
        }
    }

    /** Devuelve el campo al precio de la jornada anterior. */
    public function restablecer(int $productoId): void
    {
        $fila = $this->revision->first(fn ($f): bool => $f->producto->id === $productoId);

        if ($fila !== null) {
            $this->precios[$productoId] = $this->texto($fila->precio_anterior);
            $this->updatedPrecios(null, (string) $productoId);
        }
    }

    public function guardar(): void
    {
        $this->autorizar();

        $this->validate([
            'precios' => ['required', 'array', 'min:1'],
            'precios.*' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ], [
            'precios.*.required' => 'Falta el precio de un producto.',
            'precios.*.numeric' => 'El precio debe ser un número.',
            'precios.*.min' => 'El precio debe ser mayor a cero.',
        ]);

        // El precio tiene que superar al costo: vender por debajo sería regalar
        // el aparato.
        $debajo = [];

        foreach ($this->revision as $fila) {
            $precio = (float) ($this->precios[$fila->producto->id] ?? 0);

            if ($fila->costo > 0 && $precio <= $fila->costo) {
                $debajo[] = $fila->producto->nombre;
                $this->addError(
                    'precios.'.$fila->producto->id,
                    'Debe superar el costo (Bs '.number_format($fila->costo, 2, ',', '.').').',
                );
            }
        }

        if ($debajo !== []) {
            $this->addError('precios', 'Estos productos quedaron en o por debajo de su costo: '.implode(', ', $debajo).'.');

            // Que se vean: con un filtro puesto, el producto que bloquea podía
            // quedar fuera de la lista y el botón parecía no hacer nada.
            $this->filtro = 'todos';
            $this->buscar = '';

            return;
        }

        $cambiados = $this->revision->filter(fn ($fila): bool => $this->cambio($fila) !== 0.0)->count();

        $guardados = app(PreciosDelDia::class)->guardar($this->precios, (int) auth()->id());

        // La revisión queda vieja: el precio de hoy ya no es el anterior.
        unset($this->revision);

        $mensaje = $cambiados === 0
            ? "Precios del día confirmados sin cambios ({$guardados} productos)."
            : "Precios del día confirmados: {$cambiados} con precio nuevo de {$guardados}.";

        $this->dispatch('toast', tipo: 'success', mensaje: $mensaje);
    }

    public function render(): View
    {
        $suben = $bajan = $sinGuardar = 0;

        foreach ($this->revision as $fila) {
            $cambio = $this->cambio($fila);
            $suben += $cambio > 0 ? 1 : 0;
            $bajan += $cambio < 0 ? 1 : 0;

            // Lo escrito que todavía no es el precio confirmado de hoy.
            $texto = $this->precios[$fila->producto->id] ?? null;
            $sinGuardar += $fila->precio_hoy === null
                || ! is_numeric($texto)
                || abs((float) $texto - $fila->precio_hoy) >= 0.005 ? 1 : 0;
        }

        return view('livewire.precios.index', [
            'filas' => $this->filtradas,
            'total' => $this->revision->count(),
            'fijados' => $this->fijados,
            'pendientes' => $this->revision->count() - $this->fijados,
            'sugerencias' => $this->sugerencias,
            'suben' => $suben,
            'bajan' => $bajan,
            'sinGuardar' => $sinGuardar,
            'confirmados' => $this->revision->count() > 0 && $this->fijados === $this->revision->count(),
        ]);
    }

    /** Diferencia entre lo escrito en el campo y el precio de la jornada anterior. */
    private function cambio(object $fila): float
    {
        $actual = $this->precios[$fila->producto->id] ?? null;

        if (! is_numeric($actual)) {
            return 0.0;
        }

        return round((float) $actual - $fila->precio_anterior, 2);
    }

    /**
     * Cada campo arranca con el precio de hoy si ya se confirmó o, si no, con
     * el de la jornada anterior: confirmar sin tocar nada mantiene el de ayer.
     */
    private function rellenar(): void
    {
        foreach ($this->revision as $fila) {
            $this->precios[$fila->producto->id] = $this->texto($fila->precio_hoy ?? $fila->precio_anterior);
        }
    }

    private function texto(float $precio): string
    {
        return number_format($precio, 2, '.', '');
    }

    private function autorizar(): void
    {
        $usuario = auth()->user();

        abort_unless(
            $usuario !== null && ($usuario->can('caja.gestionar') || $usuario->can('productos.editar')),
            403,
        );
    }
}
