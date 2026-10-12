<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\TiendaActual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * En qué tienda trabaja quien usa la app: la del turno de asistencia o la
 * elegida. Es la que se anota en ventas, caja y cobros.
 */
class TiendaActualController extends Controller
{
    public function show(Request $request, TiendaActual $tiendaActual): JsonResponse
    {
        return response()->json(['data' => $tiendaActual->paraMostrar($request->user())]);
    }

    public function elegir(Request $request, TiendaActual $tiendaActual): JsonResponse
    {
        $datos = $request->validate(['tienda_id' => ['required', 'integer']]);

        try {
            $tienda = $tiendaActual->elegir($request->user(), (int) $datos['tienda_id']);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['tienda_id' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'mensaje' => "Ahora trabajas en {$tienda->nombre}.",
            'data' => $tiendaActual->paraMostrar($request->user()->fresh()),
        ]);
    }
}
