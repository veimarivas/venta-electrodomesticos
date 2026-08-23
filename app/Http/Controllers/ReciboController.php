<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Recibo de una venta, en PDF.
 *
 * Se genera al vuelo desde los datos ya guardados: el recibo no se archiva en
 * disco. Una venta es inmutable —no se edita, solo se anula—, así que volver a
 * generarlo mañana da exactamente el mismo papel, y guardar un archivo por
 * venta solo sería inventario que mantener.
 */
class ReciboController extends Controller
{
    /**
     * Ancho de un rollo térmico de 80 mm, en puntos PostScript (1 pt = 1/72").
     *
     * 80 mm ≈ 226,77 pt. Es el formato de ticket de mostrador; en una
     * impresora normal sale igual, centrado en la hoja.
     */
    private const ANCHO = 226.77;

    public function __invoke(Request $request, Venta $venta): Response
    {
        $venta->load([
            'detalles.unidad',
            'detalles.producto',
            'cliente.persona',
            'user',
            'qrCobro',
        ]);

        $pdf = Pdf::loadView('backend.ventas.recibo', [
            'venta' => $venta,
            'metodosPago' => Venta::METODOS_PAGO,
            'tienda' => config('app.name'),
        ])->setPaper([0, 0, self::ANCHO, $this->alto($venta)]);

        // Descarga directa: el recibo se entrega o se archiva, no se navega.
        return $pdf->download("Recibo-{$venta->codigo}.pdf");
    }

    /**
     * Alto del ticket, estimado a partir de lo que va a imprimirse.
     *
     * DomPDF no ajusta la página al contenido: con un alto fijo, un recibo de
     * un aparato saldría con media hoja en blanco y uno de quince se cortaría.
     * Se calcula una base (cabecera, totales y pie) más lo que ocupa cada
     * línea, y se deja holgura por si un nombre de producto parte en dos.
     */
    private function alto(Venta $venta): float
    {
        $base = 400;
        $porLinea = 46;
        $extras = 0;

        if ($venta->metodo_pago === 'mixto') {
            $extras += 30;
        }

        if ($venta->esta_anulada) {
            $extras += 50;
        }

        if (filled($venta->notas)) {
            // Una nota larga parte en varias líneas de ~38 caracteres.
            $extras += 20 + (ceil(mb_strlen($venta->notas) / 38) * 12);
        }

        return $base + ($venta->detalles->count() * $porLinea) + $extras;
    }
}
