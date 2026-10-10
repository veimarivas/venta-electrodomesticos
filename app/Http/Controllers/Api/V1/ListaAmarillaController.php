<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Ajustes;
use App\Support\ListaAmarilla;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Aparatos que llevan demasiado tiempo en la tienda.
 *
 * Sin `dias`, usa el umbral que fijó el administrador (6 meses de fábrica).
 * El costo y el capital parado solo van a quien tiene `reportes.ver_costos`.
 */
class ListaAmarillaController extends Controller
{
    public function index(Request $request, ListaAmarilla $lista, Ajustes $ajustes): JsonResponse
    {
        $datos = $request->validate([
            'dias' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $umbral = $ajustes->listaAmarillaDias();

        $resultado = $lista->consultar(
            (int) ($datos['dias'] ?? $umbral),
            $datos['buscar'] ?? null,
            $request->user()->can('reportes.ver_costos'),
        );

        return response()->json([
            'data' => $resultado['productos'],
            'meta' => [
                'dias' => $resultado['dias'],
                'desde' => $resultado['desde'],
                'umbral' => $umbral,
                'resumen' => $resultado['resumen'],
                'puede_configurar' => $request->user()->can('ajustes.editar'),
            ],
        ]);
    }

    /** El administrador fija desde cuántos días un aparato entra a la lista. */
    public function umbral(Request $request, Ajustes $ajustes): JsonResponse
    {
        $datos = $request->validate([
            'dias' => ['required', 'integer', 'min:30', 'max:1095'],
        ], [
            'dias.min' => 'Como mínimo 30 días.',
            'dias.max' => 'Como máximo 3 años.',
        ]);

        $ajustes->fijarListaAmarillaDias((int) $datos['dias'], (int) $request->user()->id);

        return response()->json([
            'mensaje' => "La lista amarilla empieza ahora a los {$datos['dias']} días.",
            'umbral' => $ajustes->listaAmarillaDias(),
        ]);
    }
}
