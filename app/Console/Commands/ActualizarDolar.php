<?php

namespace App\Console\Commands;

use App\Support\TipoDeCambio;
use Illuminate\Console\Command;

/**
 * Lee el dólar oficial (BCB) y el paralelo y guarda lo que cambió.
 *
 * Corre cada 30 minutos desde `schedule:work`. Si no corre, el dato se
 * refresca igual al abrir el panel o la app (ver `TipoDeCambio::actual`); esto
 * hace que el primero que entra no tenga que esperar a la fuente.
 */
class ActualizarDolar extends Command
{
    protected $signature = 'dolar:actualizar';

    protected $description = 'Actualiza el tipo de cambio del dólar (oficial BCB y paralelo)';

    public function handle(TipoDeCambio $tipoDeCambio): int
    {
        if (! $tipoDeCambio->actualizar()) {
            $this->warn('La fuente del dólar no respondió; se mantiene el último valor guardado.');

            return self::FAILURE;
        }

        $actual = $tipoDeCambio->actual(refrescar: false);

        $this->info(sprintf(
            'Dólar actualizado. Paralelo: compra %s · venta %s. Oficial BCB: %s.',
            $actual['paralelo']['compra'] ?? '—',
            $actual['paralelo']['venta'] ?? '—',
            $actual['oficial']['venta'] ?? '—',
        ));

        return self::SUCCESS;
    }
}
