<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avisos recientes del panel, para el respaldo sin WebSocket.
 *
 * La campana se pinta con la página, y en vivo llega por Reverb. Cuando el
 * servidor de WebSockets no está corriendo —que es lo normal mientras no se
 * termina de configurar—, ese canal no avisa de nada. Este endpoint es lo que
 * sondea `resources/js/avisos.js` para que la campana se mueva y suene igual.
 *
 * Devuelve solo lo **no leído**: es lo que muestra la campana.
 */
class AvisoController extends Controller
{
    public function recientes(Request $request): JsonResponse
    {
        $notificaciones = $request->user()
            ->unreadNotifications()
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'data' => $notificaciones->map(fn ($n): array => [
                'id' => $n->id,
                'tipo' => $n->data['tipo'] ?? 'venta_registrada',
                'titulo' => $n->data['titulo'] ?? $n->data['title'] ?? 'Aviso',
                'cuerpo' => $n->data['cuerpo'] ?? '',
                'url' => $n->data['url'] ?? '#',
                // Se usa para no duplicar con el aviso que llegue por WebSocket.
                'solicitud_id' => $n->data['solicitud_id'] ?? null,
            ])->values(),
        ]);
    }
}
