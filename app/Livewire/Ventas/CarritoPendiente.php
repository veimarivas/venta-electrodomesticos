<?php

namespace App\Livewire\Ventas;

use App\Models\Unidad;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Recordatorio del carrito que quedó apartado.
 *
 * Si el cajero sale del punto de venta con aparatos en el carrito —lo llaman a
 * otra cosa, cierra la pestaña—, esos aparatos siguen reservados y el inventario
 * los cuenta como «en proceso de venta». Este indicador, al lado de las
 * notificaciones, lo dice y lleva de vuelta al POS para retomarlo.
 *
 * Solo cuenta las reservas **del propio cajero y vigentes**: las de otra caja no
 * se muestran (no son suyas) y las vencidas las suelta el barrido del middleware
 * antes de que esta pantalla se pinte, así que aquí no hace falta comprobarlo.
 */
class CarritoPendiente extends Component
{
    /**
     * Vuelve a contar. Lo dispara el sondeo de la vista, para que el aviso suba
     * sin recargar cuando el carrito se arma en otra pestaña.
     */
    public function refrescar(): void
    {
        unset($this->pendientes);
    }

    /**
     * Cuántos aparatos tiene el cajero apartados y en cuánto vencen.
     *
     * @return array{cantidad: int, minutos: int|null}
     */
    #[Computed]
    public function pendientes(): array
    {
        $unidades = Unidad::query()
            ->where('estado', 'reservado')
            ->where('reservado_por', auth()->id())
            ->whereNotNull('reservado_hasta')
            ->where('reservado_hasta', '>', now())
            ->get(['reservado_hasta']);

        if ($unidades->isEmpty()) {
            return ['cantidad' => 0, 'minutos' => null];
        }

        $vence = $unidades->min('reservado_hasta');

        return [
            'cantidad' => $unidades->count(),
            // El que vence antes manda: es el plazo real del carrito.
            'minutos' => (int) ceil(abs(now()->diffInSeconds($vence)) / 60),
        ];
    }

    public function render(): View
    {
        return view('livewire.ventas.carrito-pendiente');
    }
}
