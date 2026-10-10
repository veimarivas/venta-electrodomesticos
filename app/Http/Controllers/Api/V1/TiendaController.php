<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tienda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tiendas para la app del administrador. El alta y la edición completas van
 * en el panel; desde el teléfono lo útil es **fijar la ubicación estando
 * dentro de la tienda**, que es el dato más exacto que se puede conseguir.
 */
class TiendaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Tienda::query()->orderBy('nombre')->get()
                ->map(fn (Tienda $t): array => AsistenciaController::tienda($t))->values(),
        ]);
    }

    public function ubicacion(Request $request, Tienda $tienda): JsonResponse
    {
        $datos = $request->validate([
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'precision' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'radio_metros' => ['nullable', 'integer', 'min:10', 'max:500'],
        ], [
            'precision.max' => 'La señal del GPS es demasiado débil para fijar la tienda. Espera a que mejore.',
        ]);

        $tienda->update([
            'latitud' => round((float) $datos['latitud'], 7),
            'longitud' => round((float) $datos['longitud'], 7),
            'radio_metros' => $datos['radio_metros'] ?? $tienda->radio_metros,
        ]);

        return response()->json([
            'mensaje' => "Ubicación de {$tienda->nombre} guardada.",
            'tienda' => AsistenciaController::tienda($tienda->fresh()),
        ]);
    }
}
