<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una cuenta recién creada o reiniciada entra con su carnet como contraseña.
 * Hasta que ponga una propia no puede usar el sistema: el carnet lo conoce
 * cualquiera que tenga una fotocopia.
 *
 * - Panel: cualquier pantalla lleva a su perfil a cambiarla.
 * - API: todo responde 403 con `debe_cambiar_password`, salvo cambiarla, ver
 *   el perfil y cerrar sesión. La app pinta la pantalla de cambio al entrar;
 *   esto es lo que impide saltársela.
 */
class ExigirContrasenaNueva
{
    /** Lo que sí se puede hacer mientras tanto. */
    private const PERMITIDAS = [
        'profile.edit',
        'api.v1.auth.password.cambiar',
        'api.v1.auth.perfil',
        'api.v1.auth.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::user() ?? $request->user();

        if ($usuario === null || ! $usuario->debe_cambiar_password || $request->routeIs(...self::PERMITIDAS)) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Antes de seguir, cambia la contraseña temporal por una propia.',
                'debe_cambiar_password' => true,
            ], 403);
        }

        return redirect()->route('profile.edit')->with('contrasena_nueva', true);
    }
}
