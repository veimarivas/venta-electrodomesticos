<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use App\Support\ComprobantesDeCliente;
use Illuminate\Http\Response;

/**
 * Estado de cuenta de un crédito, en PDF, desde el panel.
 *
 * Se genera al vuelo: el crédito no se edita (sus cuotas y pagos son la
 * verdad), así que volver a emitirlo mañana da el mismo papel.
 */
class EstadoCuentaController extends Controller
{
    public function __invoke(Credito $credito): Response
    {
        $contenido = ComprobantesDeCliente::estadoDeCuenta($credito);

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Estado-de-cuenta-'
                .($credito->venta?->codigo ?? $credito->id).'.pdf"',
            'Content-Length' => (string) strlen($contenido),
        ]);
    }
}
