<?php

namespace App\Support;

use Milon\Barcode\DNS1D;

/**
 * Genera los códigos de barras de las etiquetas de inventario.
 *
 * Se usa Code128, que admite letras, números y guiones: es lo que necesita el
 * formato de código interno ({SKU}-{AAMM}-{correlativo}). Los formatos EAN o
 * UPC solo aceptan dígitos y no servirían.
 */
class GeneradorEtiquetas
{
    /** Tamaños de etiqueta disponibles: ancho del módulo y alto del código. */
    public const TAMANOS = [
        'pequena' => ['etiqueta' => 'Pequeña (50 × 25 mm)', 'ancho' => 1, 'alto' => 22],
        'mediana' => ['etiqueta' => 'Mediana (70 × 35 mm)', 'ancho' => 2, 'alto' => 32],
        'grande' => ['etiqueta' => 'Grande (100 × 50 mm)', 'ancho' => 2, 'alto' => 45],
    ];

    public function __construct(private readonly DNS1D $generador) {}

    /**
     * SVG del código de barras, listo para incrustar en el HTML.
     *
     * La librería devuelve el SVG con prólogo XML y DOCTYPE, que no se pueden
     * meter en medio de un documento HTML: se recortan y queda solo el <svg>.
     */
    public function codigoDeBarras(string $codigo, string $tamano = 'mediana'): string
    {
        $config = self::TAMANOS[$tamano] ?? self::TAMANOS['mediana'];

        $svg = $this->generador->getBarcodeSVG(
            $codigo,
            'C128',
            $config['ancho'],
            $config['alto'],
            'black',
            false // sin el texto: se imprime aparte para controlar su tipografía
        );

        $inicio = strpos($svg, '<svg');

        return $inicio === false ? '' : substr($svg, $inicio);
    }

    /**
     * Nombre legible del tamaño, para el selector de la pantalla.
     *
     * @return array<string, string>
     */
    public static function opcionesDeTamano(): array
    {
        return array_map(fn (array $t) => $t['etiqueta'], self::TAMANOS);
    }
}
