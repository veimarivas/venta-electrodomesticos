<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeceras de seguridad en cada respuesta.
 *
 * Son instrucciones para el navegador, no para el servidor: no impiden que
 * exista un fallo, impiden que un fallo se convierta en un robo de sesión.
 * Aquí importan más de lo normal porque el panel se abre en la caja de la
 * tienda, en un navegador que puede tener de todo instalado.
 */
class CabecerasDeSeguridad
{
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);

        // Impide que otro sitio meta el panel en un <iframe> invisible y
        // engañe al cajero para que pulse «Anular venta» creyendo que pulsa
        // otra cosa (clickjacking).
        $respuesta->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // El navegador respeta el Content-Type que declaramos en vez de
        // adivinarlo. Sin esto, una imagen subida por el usuario que en
        // realidad contenga HTML podría ejecutarse como página.
        $respuesta->headers->set('X-Content-Type-Options', 'nosniff');

        // No se filtra la URL del panel —que lleva ids y rutas internas— al
        // navegar a un sitio externo.
        $respuesta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // El sistema no usa cámara, micrófono ni ubicación. Declararlo cierra
        // la puerta a que un script inyectado los pida.
        $respuesta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), interest-cohort=()');

        // HSTS solo con HTTPS real y en producción: enviarlo en desarrollo
        // dejaría el navegador convencido de que http://localhost debe ser
        // seguro, y el proyecto dejaría de abrir hasta limpiar la caché HSTS.
        if ($request->secure() && app()->isProduction()) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $respuesta;
    }
}
