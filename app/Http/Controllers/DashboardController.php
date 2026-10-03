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
        // Sin `title`: el saludo del panel ya es el encabezado de la pantalla y
        // repetir «Dashboard» justo encima solo añadía ruido. El nombre de la
        // pestaña lo sigue fijando la sección `title` de la vista.
        return view('backend.dashboard.index');
    }
}
