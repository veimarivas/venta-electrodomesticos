<?php

namespace App\Livewire\Sistema;

use App\Support\TipoDeCambio as Servicio;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * El dólar en la barra superior del panel: paralelo (compra y venta) y oficial
 * del BCB, con su historia de dos semanas y un conversor rápido.
 *
 * Perezoso a propósito: si la fuente tarda, la página no espera por él. Se
 * repinta cada 10 minutos.
 */
#[Lazy]
class TipoDeCambio extends Component
{
    public function placeholder(): string
    {
        return <<<'HTML'
            <div class="ms-1 header-item dolar-topbar">
                <span class="dolar-chip dolar-chip--cargando" aria-hidden="true">
                    <i class="ri-money-dollar-circle-line"></i>
                    <span class="placeholder col-6 rounded"></span>
                </span>
            </div>
            HTML;
    }

    public function render(Servicio $servicio): View
    {
        return view('livewire.sistema.tipo-de-cambio', ['dolar' => $servicio->actual()]);
    }
}
