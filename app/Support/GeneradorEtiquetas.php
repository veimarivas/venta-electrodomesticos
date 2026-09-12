<?php

namespace App\Support;

use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;

/**
 * Genera los códigos de las etiquetas de inventario.
 *
 * El código interno ({producto}-{AAMM}-{correlativo}) lleva letras, números y
 * guiones, así que no valen EAN ni UPC. Se emite un **QR**: la cámara de un
 * teléfono lo lee a cualquier orientación y a mucha menos resolución que un
 * Code128 del mismo largo, que era lo que no se lograba leer. El Code128 queda
 * disponible por si algún lector láser de mano lo necesita.
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

    /**
     * Zona de silencio del QR, en módulos. La norma pide 4: sin ella la cámara
     * no distingue dónde empieza la matriz.
     */
    private const ZONA_MUDA_QR = 4;

    public function __construct(
        private readonly DNS1D $generador,
        private readonly DNS2D $qr,
    ) {}

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
     * SVG del QR de la etiqueta, listo para incrustar en el HTML.
     *
     * Es el código que se usa hoy: el teléfono lo lee en cualquier orientación
     * y con mucha menos resolución que el Code128 del mismo dato.
     *
     * La librería devuelve el QR con prólogo XML, medidas en píxeles y sin
     * `viewBox` —igual que el Code128—, y sin la zona de silencio que exige la
     * norma. Se reescribe la cabecera con un `viewBox` cuadrado que incluye
     * esos 4 módulos de margen, y SIN `preserveAspectRatio="none"`: deformar un
     * QR lo vuelve ilegible, así que el contenedor lo mantiene cuadrado.
     */
    public function cuadroQr(string $codigo, string $tamano = 'mediana'): string
    {
        $config = self::TAMANOS[$tamano] ?? self::TAMANOS['mediana'];

        // Módulo del QR en píxeles del SVG crudo: se mantiene pequeño porque
        // el tamaño final lo pone la etiqueta en milímetros.
        $modulo = max(2, $config['ancho'] * 2);

        $svg = $this->qr->getBarcodeSVG($codigo, 'QRCODE', $modulo, $modulo, 'black');

        $inicio = strpos($svg, '<svg');

        if ($inicio === false) {
            return '';
        }

        $svg = substr($svg, $inicio);
        $fin = strpos($svg, '>');

        if ($fin === false
            || ! preg_match('/width="(\d+(?:\.\d+)?)"/', $svg, $ancho)
            || ! preg_match('/height="(\d+(?:\.\d+)?)"/', $svg, $alto)) {
            return $svg;
        }

        $anchoPx = (float) $ancho[1];
        $altoPx = (float) $alto[1];
        $muda = $modulo * self::ZONA_MUDA_QR;

        $viewBox = sprintf(
            '%s %s %s %s',
            -$muda,
            -$muda,
            $anchoPx + 2 * $muda,
            $altoPx + 2 * $muda
        );

        $cabecera = sprintf(
            '<svg viewBox="%s" preserveAspectRatio="xMidYMid meet" shape-rendering="crispEdges" '
                .'version="1.1" xmlns="http://www.w3.org/2000/svg">',
            $viewBox
        );

        return $cabecera.substr($svg, $fin + 1);
    }

    /**
     * QR en PNG, como data URI, para incrustarlo en un PDF.
     *
     * DomPDF no dibuja bien el SVG del QR; en PNG sale nítido. Se genera con un
     * módulo grande (8 px) y fondo blanco para que, al escalarlo en la etiqueta,
     * las barras no se vean pixeladas. La zona de silencio la aporta el margen
     * del contenedor en la vista.
     */
    public function cuadroQrPng(string $codigo, int $modulo = 8): string
    {
        $base64 = $this->qr->getBarcodePNG($codigo, 'QRCODE', $modulo, $modulo, [0, 0, 0], [255, 255, 255]);

        if ($base64 === false) {
            return '';
        }

        return 'data:image/png;base64,'.$base64;
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
