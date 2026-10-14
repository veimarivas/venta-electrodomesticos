<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tienda;
use App\Support\TiendaActual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tiendas desde la app del administrador: alta, edición (con el punto elegido
 * en el mapa o la posición actual del teléfono), quitar y marcar la principal.
 * Mismas reglas que el panel.
 */
class TiendaController extends Controller
{
    public function index(TiendaActual $tiendaActual): JsonResponse
    {
        $principal = $tiendaActual->principal()?->id;

        return response()->json([
            'data' => Tienda::query()->orderBy('nombre')->get()
                ->map(fn (Tienda $t): array => [
                    ...AsistenciaController::tienda($t),
                    'principal' => $t->id === $principal,
                ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tienda = Tienda::query()->create($this->datos($request));

        return response()->json([
            'mensaje' => "Tienda {$tienda->nombre} registrada.",
            'tienda' => AsistenciaController::tienda($tienda),
        ], 201);
    }

    public function update(Request $request, Tienda $tienda): JsonResponse
    {
        $tienda->update($this->datos($request));

        return response()->json([
            'mensaje' => "Tienda {$tienda->nombre} actualizada.",
            'tienda' => AsistenciaController::tienda($tienda->fresh()),
        ]);
    }

    /** Se archiva: su historial de asistencia y de ventas la sigue nombrando. */
    public function destroy(Tienda $tienda): JsonResponse
    {
        $tienda->update(['activa' => false]);
        $tienda->delete();

        return response()->json(['mensaje' => 'Tienda quitada. Su historial se conserva.']);
    }

    public function principal(Request $request, Tienda $tienda, TiendaActual $tiendaActual): JsonResponse
    {
        abort_unless($tienda->activa, 422, 'Una tienda inactiva no puede ser la principal.');

        $asignado = $tiendaActual->hacerPrincipal($tienda, (int) $request->user()->id);

        return response()->json([
            'mensaje' => array_sum($asignado) > 0
                ? "{$tienda->nombre} es la principal. Se le asignaron {$asignado['ventas']} ventas de antes."
                : "{$tienda->nombre} es ahora la tienda principal.",
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

    /** @return array<string, mixed> */
    private function datos(Request $request): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:2', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'latitud' => ['required', 'numeric', 'between:-90,90'],
            'longitud' => ['required', 'numeric', 'between:-180,180'],
            'radio_metros' => ['required', 'integer', 'min:10', 'max:500'],
            'hora_entrada' => ['nullable', 'date_format:H:i'],
            'tolerancia_minutos' => ['nullable', 'integer', 'min:0', 'max:120'],
            'activa' => ['nullable', 'boolean'],
        ], [
            'nombre.required' => 'Ponle un nombre a la tienda.',
            'latitud.required' => 'Elige el punto de la tienda en el mapa.',
            'radio_metros.min' => 'Como mínimo 10 m.',
            'radio_metros.max' => 'Como máximo 500 m.',
        ]);

        return [
            'nombre' => trim($datos['nombre']),
            'direccion' => isset($datos['direccion']) && trim($datos['direccion']) !== '' ? trim($datos['direccion']) : null,
            'latitud' => round((float) $datos['latitud'], 7),
            'longitud' => round((float) $datos['longitud'], 7),
            'radio_metros' => (int) $datos['radio_metros'],
            'hora_entrada' => $datos['hora_entrada'] ?? null,
            'tolerancia_minutos' => (int) ($datos['tolerancia_minutos'] ?? 10),
            'activa' => (bool) ($datos['activa'] ?? true),
        ];
    }
}
