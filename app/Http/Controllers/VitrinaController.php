<?php

namespace App\Http\Controllers;

use App\Support\Vitrina;
use Illuminate\Contracts\View\View;

/**
 * Vitrina del catálogo en el panel.
 *
 * Es la misma vista «de tienda» que la app muestra en su pestaña Tienda: los
 * más vendidos arriba y el catálogo ordenado por categorías, para navegar con
 * el cliente delante sin entrar en el CRUD.
 */
class VitrinaController extends Controller
{
    public function __invoke(): View
    {
        $datos = Vitrina::datos();

        return view('backend.catalogo.vitrina', [
            'title' => 'Vitrina',
            'breadcrumbs' => ['Inicio' => null, 'Catálogo' => null, 'Vitrina' => null],
            'recomendados' => $datos['recomendados'],
            'categorias' => $datos['categorias'],
            'hayVentas' => $datos['recomendados']->isNotEmpty(),
        ]);
    }
}