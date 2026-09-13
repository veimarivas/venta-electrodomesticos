<?php

namespace App\Console\Commands;

use App\Support\ReservasDeUnidades;
use Illuminate\Console\Command;

/**
 * Suelta las reservas de POS que vencieron.
 *
 * Un carrito abandonado —el navegador se cerró, se cortó la luz— dejaría sus
 * aparatos en «en proceso de venta» para siempre. Este barrido los devuelve al
 * stock. Corre cada minuto desde `schedule:work`; además, el propio POS suelta
 * las suyas al cerrar el carrito, así que esto es la red de seguridad.
 *
 * La reserva también se puede tomar aunque esté vencida y el barrido no haya
 * pasado (ver `ReservasDeUnidades::reservar`), así que el aparato nunca queda
 * trabado de verdad; esto mantiene el inventario contando bien.
 */
class LiberarReservasVencidas extends Command
{
    protected $signature = 'reservas:liberar';

    protected $description = 'Devuelve al stock las unidades con reserva de POS vencida';

    public function handle(ReservasDeUnidades $reservas): int
    {
        $liberadas = $reservas->liberarVencidas();

        $this->info($liberadas === 0
            ? 'No había reservas vencidas.'
            : $liberadas.' '.($liberadas === 1 ? 'reserva liberada.' : 'reservas liberadas.'));

        return self::SUCCESS;
    }
}
