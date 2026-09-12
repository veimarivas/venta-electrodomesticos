<?php

namespace App\Http\Controllers;

use App\Models\Reparacion;
use App\Support\ComprobantesDeCliente;
use Illuminate\Http\Response;

/**
 * Orden de servicio técnico, en PDF, desde el panel.
 *
 * Es el papel que se le entrega al cliente al recibir el aparato; se genera al
 * vuelo desde la orden guardada.
 */
class OrdenReparacionController extends Controller
{
    public function __invoke(Reparacion $reparacion): Response
    {
        $contenido = ComprobantesDeCliente::ordenDeReparacion($reparacion);

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Orden-'.$reparacion->codigo.'.pdf"',
            'Content-Length' => (string) strlen($contenido),
        ]);
    }
}
