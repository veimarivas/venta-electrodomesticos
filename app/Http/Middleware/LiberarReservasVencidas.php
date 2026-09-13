<?php

namespace App\Http\Middleware;

use App\Support\ReservasDeUnidades;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suelta las reservas de POS vencidas antes de atender la petición.
 *
 * El barrido programado (`reservas:liberar`) depende de que `schedule:work` esté
 * corriendo. Cuando ese proceso no está vivo —que es lo habitual mientras el
 * servidor no se termina de configurar—, un carrito abandonado deja sus aparatos
 * en «en proceso de venta» para siempre: se ve el aparato apartado incluso al
 * día siguiente.
 *
 * Comprobado aquí, una vez por minuto, el inventario se corrige solo. No hay
 * lógica nueva: es el mismo `UPDATE ... WHERE` del servicio, adelantado al
 * momento en que alguien vuelve a mirar.
 */
class LiberarReservasVencidas
{
    public function handle(Request $request, Closure $next): Response
    {
        // Una vez por minuto basta; sin la marca se escribiría en cada petición.
        // `add` devuelve true la primera vez y false mientras la clave siga viva,
        // así que dos peticiones a la vez no repiten el barrido.
        if (Cache::add('reservas:barrido', true, 60)) {
            app(ReservasDeUnidades::class)->liberarVencidas();
        }

        return $next($request);
    }
}
