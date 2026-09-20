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

    /**
     * Etiquetas de varias unidades en un solo PDF, una por página, cada una del
     * tamaño del adhesivo. Es lo que se imprime al recepcionar una compra
     * entera o un lote seleccionado, sin sacarlas de a una.
     *
     * @param  iterable<int, Unidad>  $unidades
     */
    public static function generarLote(iterable $unidades, string $tamano = 'mediana'): string
    {
        $config = self::MEDIDAS[$tamano] ?? self::MEDIDAS['mediana'];

        $paginas = [];

        foreach ($unidades as $unidad) {
            $unidad->loadMissing(['producto.marca']);

            $paginas[] = [
                'unidad' => $unidad,
                'qr' => app(GeneradorEtiquetas::class)->cuadroQrPng($unidad->codigo_interno),
            ];
        }

        $pdf = Pdf::loadView('backend.etiquetas.pdf-lote', [
            'paginas' => $paginas,
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
