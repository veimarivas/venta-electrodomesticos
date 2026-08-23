<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsuarioResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Autenticación de la app móvil por token (Sanctum).
 */
class AuthController extends Controller
{
    /**
     * Entrega un token para el dispositivo.
     *
     * Acepta usuario o correo, igual que el login web: a los trabajadores se
     * les entrega un nombre de usuario, no un correo.
     */
    public function login(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'usuario' => ['required', 'string'],
            'password' => ['required', 'string'],
            // Identifica el token en la lista de sesiones del usuario, para
            // poder revocar un teléfono concreto sin cerrar los demás.
            'dispositivo' => ['required', 'string', 'max:120'],
        ], [
            'usuario.required' => 'Indica tu usuario o correo.',
            'password.required' => 'Indica tu contraseña.',
            'dispositivo.required' => 'Indica el nombre del dispositivo.',
        ]);

        $identificador = mb_strtolower(trim($datos['usuario']));

        $usuario = User::where('email', $identificador)->first()
            ?? User::where('name', $identificador)->orderBy('id')->first();

        if ($usuario === null || ! Hash::check($datos['password'], $usuario->password)) {
            // Mismo mensaje para usuario inexistente y contraseña mala: si
            // fueran distintos se podría averiguar qué cuentas existen.
            throw ValidationException::withMessages([
                'usuario' => 'Las credenciales no son correctas.',
            ]);
        }

        // Se comprueba DESPUÉS de la contraseña, por la misma razón.
        if (! $usuario->is_active) {
            throw ValidationException::withMessages([
                'usuario' => \App\Providers\FortifyServiceProvider::MENSAJE_CUENTA_BLOQUEADA,
            ]);
        }

        // Un token por dispositivo: volver a entrar desde el mismo teléfono
        // reemplaza el anterior en vez de acumular tokens vivos.
        $usuario->tokens()->where('name', $datos['dispositivo'])->delete();

        $token = $usuario->createToken($datos['dispositivo'])->plainTextToken;

        $usuario->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token' => $token,
            'usuario' => new UsuarioResource($usuario->load('persona')),
        ]);
    }

    /** Revoca solo el token con el que se hizo la petición. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['mensaje' => 'Sesión cerrada.']);
    }

    public function perfil(Request $request): UsuarioResource
    {
        return new UsuarioResource($request->user()->load('persona'));
    }
}
