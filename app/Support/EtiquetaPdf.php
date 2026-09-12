<?php

namespace App\Support;

use App\Models\Unidad;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Etiqueta imprimible de una unidad, en PDF.
 *
 * Una página del tamaño exacto del adhesivo (en milímetros, no en píxeles) con
 * el QR y los datos del aparato. El teléfono la abre con su visor y la manda a
 * la impresora, así que la etiqueta se puede sacar al recepcionar, sin pasar
 * por el panel.
 *
 * DomPDF no dibuja bien el SVG del QR: se usa un PNG con fondo blanco y el
 * margen del contenedor hace de zona de silencio.
 */
class EtiquetaPdf
{
    /** Medidas en milímetros de cada etiqueta, y su QR. */
    private const MEDIDAS = [
        'pequena' => ['ancho' => 50.0, 'alto' => 25.0, 'qr' => 14.0],
        'mediana' => ['ancho' => 70.0, 'alto' => 35.0, 'qr' => 22.0],
        'grande' => ['ancho' => 100.0, 'alto' => 50.0, 'qr' => 32.0],
    ];

    public static function generar(Unidad $unidad, string $tamano = 'mediana'): string
    {
        $unidad->loadMissing(['producto.marca']);

        $config = self::MEDIDAS[$tamano] ?? self::MEDIDAS['mediana'];

        $qr = app(GeneradorEtiquetas::class)->cuadroQrPng($unidad->codigo_interno);

        $pdf = Pdf::loadView('backend.etiquetas.pdf', [
            'unidad' => $unidad,
            'qr' => $qr,
            'qrMm' => $config['qr'],
        ])->setPaper([0, 0, self::aPuntos($config['ancho']), self::aPuntos($config['alto'])]);

        return $pdf->output();
    }

    /** Milímetros a puntos PostScript (1 pt = 1/72"). */
    private static function aPuntos(float $mm): float
    {
        return round($mm * 72 / 25.4, 2);
    }
}
