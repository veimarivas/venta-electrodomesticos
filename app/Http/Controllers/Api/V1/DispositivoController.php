<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DispositivoResource;
use App\Models\Dispositivo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alta y baja de los teléfonos que reciben notificaciones push.
 */
class DispositivoController extends Controller
{
    /**
     * Registra o actualiza el token FCM del teléfono.
     *
     * Se usa updateOrCreate sobre el token, no sobre (usuario, token): Firebase
     * lo emite por instalación, así que si un teléfono cambia de dueño el token
     * debe migrar de fila en vez de duplicarse en dos usuarios.
     */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'plataforma' => ['required', 'in:android,ios'],
            'nombre_dispositivo' => ['nullable', 'string', 'max:120'],
        ]);

        $dispositivo = Dispositivo::updateOrCreate(
            ['token' => $datos['token']],
            [
                'user_id' => $request->user()->id,
                'plataforma' => $datos['plataforma'],
                'nombre_dispositivo' => $datos['nombre_dispositivo'] ?? null,
                'ultimo_uso_en' => now(),
            ]
        );

        return response()->json(
            ['dispositivo' => new DispositivoResource($dispositivo)],
            $dispositivo->wasRecentlyCreated ? 201 : 200
        );
    }

    /**
     * Da de baja el teléfono. Solo el dueño puede hacerlo: si no, conociendo
     * un token cualquiera se podría dejar a otro sin notificaciones.
     */
    public function destroy(Request $request, string $token): JsonResponse
    {
        $borrados = Dispositivo::where('token', $token)
            ->where('user_id', $request->user()->id)
            ->delete();

        if ($borrados === 0) {
            return response()->json(['mensaje' => 'Ese dispositivo no está registrado a tu nombre.'], 404);
        }

        return response()->json(['mensaje' => 'Dispositivo dado de baja.']);
    }

    /** Teléfonos del usuario, para que la app pueda mostrarlos y depurarlos. */
    public function index(Request $request)
    {
        return DispositivoResource::collection(
            $request->user()->dispositivos()->latest('ultimo_uso_en')->get()
        );
    }
}
