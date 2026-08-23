<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Buscador global del topbar. Se conectará a productos, seriales
     * (items) y ventas cuando existan esos módulos.
     */
    public function __invoke(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));

        return view('backend.search.index', [
            'title' => 'Resultados de búsqueda',
            'breadcrumbs' => ['Inicio' => route('dashboard'), 'Búsqueda' => null],
            'query' => $query,
            'results' => [],
        ]);
    }
}
