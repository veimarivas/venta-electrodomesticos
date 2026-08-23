<?php

namespace App\Http\Middleware;

use App\Providers\FortifyServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión de un usuario que fue desactivado mientras la tenía
 * abierta. Sin esto, dar de baja a un vendedor no surtiría efecto hasta
 * que cerrara sesión por su cuenta.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! Auth::user()->is_active) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => FortifyServiceProvider::MENSAJE_CUENTA_BLOQUEADA]);
        }

        return $next($request);
    }
}
