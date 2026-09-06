<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UsuarioResource;
use App\Models\User;
use App\Http\Controllers\Api\V1\PersonaController;
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

    /**
     * Permite al usuario autenticado actualizar su propio perfil.
     *
     * Actualiza campos de la tabla `users` (name, email, phone) y los campos
     * de la tabla `personas` vinculada (nombres, apellidos, celular, dirección,
     * correo, fecha de nacimiento).
     *
     * No requiere permiso especial: cada uno edita lo suyo.
     */
    public function actualizarPerfil(Request $request): UsuarioResource
    {
        $usuario = $request->user();
        $persona = $usuario->persona;

        $soloLetras = '/^[\p{L}\s\'\-]+$/u';

        // Validar campos de users
        $usuarioDatos = $request->validate([
            'name' => ['sometimes', 'string', 'min:3', 'max:60'],
            'email' => ['sometimes', 'email:rfc', 'max:150'],
        ]);

        // Validar campos de personas (solo si se envían)
        $personaDatos = $request->validate([
            'nombres' => ['sometimes', 'string', 'min:2', 'max:100', "regex:{$soloLetras}"],
            'apellido_paterno' => ['nullable', 'string', 'min:2', 'max:60', "regex:{$soloLetras}"],
            'apellido_materno' => ['nullable', 'string', 'min:2', 'max:60', "regex:{$soloLetras}"],
            'celular' => ['nullable', 'string', 'regex:/^[0-9]{8}$/'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'correo' => ['nullable', 'email:rfc', 'max:150'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
        ], [
            'celular.regex' => 'El celular debe tener 8 números.',
            'nombres.min' => 'El nombre debe tener al menos 2 caracteres.',
        ]);

        // Actualizar campos de users si se enviaron
        if (!empty($usuarioDatos)) {
            $usuario->update($usuarioDatos);
        }

        // Actualizar campos de personas si se enviaron
        if (!empty($personaDatos) && $persona) {
            $columnas = PersonaController::aColumnas(
                array_merge([
                    'carnet' => $persona->carnet,
                    'nombres' => $persona->nombres,
                    'apellido_paterno' => $persona->apellido_paterno,
                    'apellido_materno' => $persona->apellido_materno,
                ], $personaDatos)
            );
            $persona->update($columnas);
        }

        return new UsuarioResource($usuario->fresh('persona'));
    }

    /**
     * Permite al usuario autenticado cambiar su propia contraseña.
     *
     * Requiere la contraseña actual para confirmar la identidad.
     */
    public function cambiarContrasena(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'password_actual' => ['required', 'string'],
            'password_nuevo' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password_actual.required' => 'Indica tu contraseña actual.',
            'password_nuevo.required' => 'Indica la nueva contraseña.',
            'password_nuevo.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password_nuevo.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $usuario = $request->user();

        if (! Hash::check($datos['password_actual'], $usuario->password)) {
            throw ValidationException::withMessages([
                'password_actual' => 'La contraseña actual no es correcta.',
            ]);
        }

        $usuario->update([
            'password' => Hash::make($datos['password_nuevo']),
        ]);

        return response()->json(['mensaje' => 'Contraseña actualizada.']);
    }
}
