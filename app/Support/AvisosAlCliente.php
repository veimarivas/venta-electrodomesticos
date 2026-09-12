<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos que van al cliente, no al personal.
 *
 * El sistema ya sabe **cuándo** hay que avisar (la entrega sale hoy, la
 * reparación está lista, la cuota vence). Esta clase resuelve **cómo** llega:
 * el transporte se elige con `config/avisos.php`.
 *
 * Hoy hay dos canales —`log` y `correo`—; WhatsApp y SMS se añaden aquí el día
 * que se elija proveedor, sin tocar los disparadores. Cada disparador llama a
 * `enviar()` con el teléfono y el correo del cliente; si el canal elegido no
 * puede usarlos (el correo sin dirección, por ejemplo), se deja constancia en
 * el log en vez de fallar: un aviso que no sale no puede tumbar la operación.
 */
class AvisosAlCliente
{
    /**
     * Manda un aviso al cliente. Devuelve si se pudo entregar por el canal
     * configurado.
     */
    public function enviar(?string $celular, ?string $correo, string $mensaje): bool
    {
        return match (config('avisos.canal', 'log')) {
            'correo' => $this->porCorreo($correo, $mensaje),
            default => $this->porRegistro($celular, $mensaje),
        };
    }

    /**
     * Canal por defecto: el mensaje queda en el log, con el teléfono al que
     * iría. Sirve para comprobar el texto y a quién le toca antes de conectar
     * un proveedor de verdad.
     */
    private function porRegistro(?string $celular, string $mensaje): bool
    {
        Log::channel(config('logging.default'))->info(
            '[aviso al cliente]'.($celular ? " a {$celular}" : '').": {$mensaje}"
        );

        return true;
    }

    private function porCorreo(?string $correo, string $mensaje): bool
    {
        if (! filled($correo)) {
            Log::warning('[aviso al cliente] sin correo: no se pudo enviar.', [
                'mensaje' => $mensaje,
            ]);

            return false;
        }

        Mail::raw($mensaje, fn ($m) => $m->to($correo)->subject('Aviso de la tienda'));

        return true;
    }
}
