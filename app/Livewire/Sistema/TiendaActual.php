<?php

namespace App\Livewire\Sistema;

use App\Support\TiendaActual as Servicio;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use RuntimeException;

/**
 * En qué tienda se está trabajando, en la barra superior del panel.
 *
 * Con la asistencia marcada la tienda es fija (la del turno). Sin ella, se
 * elige aquí y queda guardada en el usuario: es la que usan el punto de venta,
 * la caja y los cobros. Sin tiendas registradas no aparece.
 */
class TiendaActual extends Component
{
    public function elegir(int $tiendaId, Servicio $servicio): void
    {
        try {
            $tienda = $servicio->elegir(auth()->user(), $tiendaId);
        } catch (RuntimeException $e) {
            $this->dispatch('toast', tipo: 'error', mensaje: $e->getMessage());

            return;
        }

        // Se recarga la página: el punto de venta, la caja y el resto tienen
        // que verse con la tienda nueva, no con la que tenían cargada.
        session()->flash('success', "Ahora trabajas en {$tienda->nombre}.");
        $this->redirect(request()->header('Referer') ?: route('dashboard'));
    }

    public function render(Servicio $servicio): View
    {
        return view('livewire.sistema.tienda-actual', $servicio->paraMostrar(auth()->user()));
    }
}
