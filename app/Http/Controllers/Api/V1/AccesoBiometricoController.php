<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AccesoBiometrico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Entrar con huella o rostro desde un teléfono registrado.
 *
 * La huella la verifica el teléfono; el servidor nunca la ve. Lo que el
 * servidor sí controla es **qué teléfono** puede abrir sesión sin contraseña:
 * al registrarlo le entrega una llave al azar que el teléfono guarda cifrada y
 * solo saca tras verificar la huella. Antes se guardaba la contraseña en el
 * teléfono; con la llave, cambiar la contraseña o bloquear la cuenta cierra la
 * puerta (ver `User::booted`), y el administrador puede quitar un teléfono.
 */
class AccesoBiometricoController extends Controller
{
    private const MENSAJE_NO_REGISTRADO = 'Este teléfono ya no está registrado para entrar con huella. Entra con tu contraseña.';

    /** Registra el teléfono de quien ya entró con su contraseña. */
    public function registrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'dispositivo_id' => ['required', 'string', 'min:16', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
            'nombre' => ['nullable', 'string', 'max:120'],
        ]);

        $llave = Str::random(64);

        // Un teléfono, una persona: si otro usuario lo tenía registrado, deja
        // de valer para él (ver la migración).
        AccesoBiometrico::query()->updateOrCreate(
            ['dispositivo_id' => $datos['dispositivo_id']],
            [
                'user_id' => $request->user()->id,
                'nombre' => $datos['nombre'] ?? null,
                'llave_hash' => AccesoBiometrico::hashDe($llave),
                'ultimo_uso_en' => now(),
            ],
        );

        return response()->json([
            'llave' => $llave,
            'mensaje' => 'Listo: la próxima vez entra con tu huella o tu rostro.',
        ], 201);
    }

    /** Abre sesión con la llave del teléfono, ya verificada la huella. */
    public function entrar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'dispositivo_id' => ['required', 'string', 'max:64'],
            'llave' => ['required', 'string', 'max:128'],
            'dispositivo' => ['required', 'string', 'max:120'],
        ]);

        $acceso = AccesoBiometrico::query()
            ->with('user')
            ->where('dispositivo_id', $datos['dispositivo_id'])
            ->first();

        if ($acceso === null || ! $acceso->aceptaLlave($datos['llave'])) {
            throw ValidationException::withMessages(['llave' => self::MENSAJE_NO_REGISTRADO]);
        }

        if (! $acceso->user->is_active) {
            throw ValidationException::withMessages([
                'usuario' => \App\Providers\FortifyServiceProvider::MENSAJE_CUENTA_BLOQUEADA,
            ]);
        }

        $acceso->forceFill(['ultimo_uso_en' => now()])->save();

        return AuthController::sesionPara($acceso->user, $datos['dispositivo']);
    }

    /** El usuario apaga la huella en su teléfono. */
    public function quitar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'dispositivo_id' => ['required', 'string', 'max:64'],
        ]);

        $request->user()->accesosBiometricos()
            ->where('dispositivo_id', $datos['dispositivo_id'])
            ->delete();

        return response()->json(['mensaje' => 'Este teléfono ya no entra con huella.']);
    }

    /** Teléfonos con huella del usuario autenticado. */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->accesosBiometricos()
                ->latest('ultimo_uso_en')
                ->get()
                ->map(fn (AccesoBiometrico $a): array => [
                    'id' => $a->id,
                    'dispositivo_id' => $a->dispositivo_id,
                    'nombre' => $a->nombre,
                    'registrado_en' => $a->created_at?->toIso8601String(),
                    'ultimo_uso_en' => $a->ultimo_uso_en?->toIso8601String(),
                ]),
        ]);
    }
}
