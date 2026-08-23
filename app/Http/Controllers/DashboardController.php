<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * Panel principal. Por ahora muestra la estructura con datos en cero;
     * los indicadores se conectan al implementarse el módulo de ventas.
     */
    public function __invoke(): View
    {
        return view('backend.dashboard.index', [
            'title' => 'Dashboard',
            'breadcrumbs' => ['Inicio' => null, 'Dashboard' => null],
        ]);
    }
}
