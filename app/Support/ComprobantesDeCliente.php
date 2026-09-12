<?php

namespace App\Support;

use App\Models\Credito;
use App\Models\Reparacion;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Comprobantes que se le entregan al cliente.
 *
 * Se generan al vuelo desde los datos guardados, no se archivan: el crédito,
 * la venta y la reparación llevan su propio histórico, y guardar un PDF por
 * documento solo sería inventario que mantener. El panel y la app comparten
 * estas mismas vistas para que el papel sea idéntico salga de donde salga.
 */
class ComprobantesDeCliente
{
    /** Estado de cuenta de un crédito, en PDF (A4). */
    public static function estadoDeCuenta(Credito $credito): string
    {
        $credito->loadMissing([
            'cliente.persona',
            'venta',
            'cuotas',
            'pagos.cuota',
            'pagos.user',
        ]);

        return Pdf::loadView('backend.creditos.estado-cuenta', [
            'credito' => $credito,
            'tienda' => config('app.name'),
        ])->setPaper('a4')->output();
    }

    /** Orden de servicio técnico, en PDF (A4). */
    public static function ordenDeReparacion(Reparacion $reparacion): string
    {
        $reparacion->loadMissing([
            'unidad.producto.marca',
            'cliente.persona',
            'tecnico',
            'recibidaPor',
        ]);

        return Pdf::loadView('backend.reparaciones.orden', [
            'reparacion' => $reparacion,
            'tienda' => config('app.name'),
        ])->setPaper('a4')->output();
    }
}
