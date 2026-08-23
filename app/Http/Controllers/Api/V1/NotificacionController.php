<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Historial de avisos del usuario (tabla `notifications` de Laravel).
 */
class NotificacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notificaciones = $request->user()
            ->notifications()
            ->latest()
            ->paginate($request->integer('por_pagina', 20));

        return response()->json([
            'data' => collect($notificaciones->items())->map(fn ($n): array => [
                'id' => $n->id,
                'leida' => $n->read_at !== null,
                'creada_en' => $n->created_at?->toIso8601String(),
                ...$n->data,
            ]),
            'meta' => [
                'pagina' => $notificaciones->currentPage(),
                'total' => $notificaciones->total(),
                'ultima_pagina' => $notificaciones->lastPage(),
                'sin_leer' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    /** Marca una notificación como leída. */
    public function marcarLeida(Request $request, string $id): JsonResponse
    {
        $notificacion = $request->user()->notifications()->whereKey($id)->first();

        if ($notificacion === null) {
            return response()->json(['mensaje' => 'Ese aviso no es tuyo o ya no existe.'], 404);
        }

        $notificacion->markAsRead();

        return response()->json(['mensaje' => 'Aviso marcado como leído.']);
    }

    public function marcarTodasLeidas(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['mensaje' => 'Todos los avisos quedaron leídos.']);
    }
}
