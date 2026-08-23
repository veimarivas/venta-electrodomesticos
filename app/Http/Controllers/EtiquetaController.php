<?php

namespace App\Http\Controllers;

use App\Models\Unidad;
use App\Models\Compra;
use App\Support\GeneradorEtiquetas;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Hoja de etiquetas imprimible para las unidades físicas.
 *
 * Se abre en una pestaña aparte con un layout sin menú ni cabecera, pensado
 * para mandarlo directo a la impresora de etiquetas o a una hoja adhesiva.
 */
class EtiquetaController extends Controller
{
    /**
     * Etiquetas de todas las unidades generadas por una compra.
     *
     * Es el caso habitual: llega la mercadería, se recepciona y se imprimen
     * de una sola vez las etiquetas de todo el lote.
     */
    public function compra(Request $request, Compra $compra): View
    {
        $unidades = $compra->unidades()
            ->with('producto')
            ->orderBy('codigo_interno')
            ->get();

        return $this->hoja($request, $unidades, "Compra {$compra->codigo}");
    }

    /**
     * Etiquetas de unidades sueltas, elegidas desde el inventario.
     */
    public function unidades(Request $request): View
    {
        $datos = $request->validate([
            'ids' => ['required', 'string'],
        ]);

        // Los ids llegan como lista separada por comas en la URL, para poder
        // abrir la hoja en una pestaña nueva con un enlace normal.
        $ids = collect(explode(',', $datos['ids']))
            ->map(fn (string $id) => (int) trim($id))
            ->filter()
            ->unique()
            ->take(500) // tope de seguridad: nadie imprime más de una resma
            ->all();

        abort_if($ids === [], 404);

        $unidades = Unidad::with('producto')
            ->whereIn('id', $ids)
            ->orderBy('codigo_interno')
            ->get();

        abort_if($unidades->isEmpty(), 404);

        return $this->hoja($request, $unidades, 'Unidades seleccionadas');
    }

    /**
     * Arma la hoja con el tamaño y las copias pedidos.
     *
     * @param  \Illuminate\Support\Collection<int, Unidad>  $unidades
     */
    private function hoja(Request $request, $unidades, string $titulo): View
    {
        $tamano = $request->string('tamano', 'mediana')->toString();

        if (! array_key_exists($tamano, GeneradorEtiquetas::TAMANOS)) {
            $tamano = 'mediana';
        }

        // Copias por unidad: útil cuando la etiqueta se pega en la caja y en
        // el aparato. Se acota para no colgar el navegador por un typo.
        $copias = max(1, min((int) $request->integer('copias', 1), 5));

        $generador = app(GeneradorEtiquetas::class);

        $etiquetas = $unidades->flatMap(fn (Unidad $unidad) => array_fill(0, $copias, [
            'unidad' => $unidad,
            'svg' => $generador->codigoDeBarras($unidad->codigo_interno, $tamano),
        ]));

        return view('backend.etiquetas.hoja', [
            'etiquetas' => $etiquetas,
            'titulo' => $titulo,
            'tamano' => $tamano,
            'copias' => $copias,
            'tamanos' => GeneradorEtiquetas::opcionesDeTamano(),
            'autoImprimir' => $request->boolean('imprimir'),
        ]);
    }
}
