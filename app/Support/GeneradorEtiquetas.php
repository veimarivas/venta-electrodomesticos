<?php

namespace App\Support;

use Milon\Barcode\DNS1D;

/**
 * Genera los códigos de barras de las etiquetas de inventario.
 *
 * Se usa Code128, que admite letras, números y guiones: es lo que necesita el
 * formato de código interno ({AAMM}-{correlativo}). Los formatos EAN o
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

    /**
     * Zona muda a cada lado, en módulos.
     *
     * La norma de Code128 exige 10 módulos en blanco antes de la primera barra
     * y después de la última. La librería no los dibuja: entrega el patrón
     * pegado al borde del SVG, y la etiqueta lo deja a ras del texto o del
     * borde del adhesivo. Sin ese margen muchos lectores —el de un teléfono el
     * primero— no encuentran dónde empieza el código.
     */
    private const ZONA_MUDA = 10;

    public function __construct(private readonly DNS1D $generador) {}

    /**
     * SVG del código de barras, listo para incrustar en el HTML.
     *
     * La librería devuelve el SVG con prólogo XML y DOCTYPE, que no se pueden
     * meter en medio de un documento HTML: se recortan y queda solo el <svg>.
     *
     * Y hace falta una segunda corrección, menos visible pero peor: ese <svg>
     * trae `width`/`height` en píxeles y NINGÚN `viewBox`. Un SVG sin viewBox
     * no tiene proporción intrínseca, así que el `max-width: 100%` de la hoja
     * de etiquetas no lo escala: le recorta el lienzo. El código de barras de
     * una unidad mide ~222 px y en la etiqueta pequeña caben ~174 px, de modo
     * que se imprimía con el último quinto CORTADO —dígito de control y patrón
     * de parada incluidos—. Un Code128 así no lo lee ningún lector: es la razón
     * de que la etiqueta impresa no se reconociera al vender.
     *
     * Se reescribe la cabecera con un viewBox que incluye las zonas mudas y sin
     * medidas en píxeles: el tamaño lo pone el CSS en milímetros y el dibujo se
     * escala en vez de cortarse.
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

        if ($inicio === false) {
            return '';
        }

        $svg = substr($svg, $inicio);

        $fin = strpos($svg, '>');

        if ($fin === false || ! preg_match('/width="(\d+(?:\.\d+)?)"/', $svg, $ancho)
            || ! preg_match('/height="(\d+(?:\.\d+)?)"/', $svg, $alto)) {
            // Formato inesperado de la librería: mejor devolverlo tal cual que
            // romper la hoja entera.
            return $svg;
        }

        $muda = self::ZONA_MUDA * $config['ancho'];
        $viewBox = sprintf(
            '%s 0 %s %s',
            -$muda,
            (float) $ancho[1] + 2 * $muda,
            (float) $alto[1]
        );

        // `preserveAspectRatio="none"`: la altura la fija la etiqueta y las
        // barras pueden estirarse en vertical sin perder información —un código
        // de barras solo codifica anchos—. Lo que no puede es recortarse.
        // `shape-rendering="crispEdges"` evita que el suavizado del navegador
        // difumine los bordes de las barras finas al escalar.
        $cabecera = sprintf(
            '<svg viewBox="%s" preserveAspectRatio="none" shape-rendering="crispEdges" '
                .'version="1.1" xmlns="http://www.w3.org/2000/svg">',
            $viewBox
        );

        return $cabecera.substr($svg, $fin + 1);
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
