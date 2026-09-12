<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Versión del contrato de la API.
 *
 * Es la primera llamada que hace la app: con `app_minima` sabe si el teléfono
 * quedó atrás y avisa antes de que el usuario se choque con una pantalla rota.
 * No exige sesión: un APK viejo tiene que poder enterarse aunque su token ya
 * no sirva.
 */
class VersionController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                // Sube cuando un cambio deja de ser compatible hacia atrás.
                'api' => 1,
                'app_minima' => (string) config('ventas.app_minima'),
            ],
        ]);
    }
}
