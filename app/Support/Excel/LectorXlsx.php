<?php

namespace App\Support\Excel;

use RuntimeException;
use SimpleXMLElement;
use Throwable;
use ZipArchive;

/**
 * Lee un `.xlsx` con PHP nativo, sin depender de una librería.
 *
 * Devuelve el contenido de **todas las hojas** con su nombre: la plantilla de
 * importación trae «Categorias» y «Productos», y cada una se lee como una lista
 * de filas de celdas de texto.
 *
 * No interpreta formatos enriquecidos: para una hoja de carga basta con texto.
 * Las celdas vacías quedan como cadena vacía y las filas sin ningún dato se
 * descartan.
 */
class LectorXlsx
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * @return array<string, array<int, array<int, string>>>  Nombre de hoja => filas.
     */
    public function leer(string $ruta): array
    {
        if (! is_file($ruta)) {
            throw new RuntimeException('No se encontró el archivo a leer.');
        }

        if ($this->esCsv($ruta)) {
            return ['CSV' => $this->leerCsv($ruta)];
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión zip de PHP no está disponible.');
        }

        $zip = new ZipArchive;

        if ($zip->open($ruta) !== true) {
            throw new RuntimeException('El archivo no es un Excel (.xlsx) válido.');
        }

        try {
            $compartidas = $this->cadenasCompartidas($zip);
            $hojas = $this->mapaDeHojas($zip);

            $resultado = [];

            foreach ($hojas as $nombre => $parte) {
                $contenido = $zip->getFromName($parte);

                if ($contenido === false) {
                    continue;
                }

                $resultado[$nombre] = $this->leerHoja($contenido, $compartidas);
            }
        } finally {
            $zip->close();
        }

        return $resultado;
    }

    // ---- Estructura del libro ------------------------------------------------

    /**
     * Cadena compartida por índice, tal como las referencia `t="s"`.
     *
     * @return array<int, string>
     */
    private function cadenasCompartidas(ZipArchive $zip): array
    {
        $contenido = $zip->getFromName('xl/sharedStrings.xml');

        if ($contenido === false) {
            return [];
        }

        $xml = $this->cargar($contenido);

        if ($xml === null) {
            return [];
        }

        $xml->registerXPathNamespace('m', self::NS);

        $cadenas = [];

        foreach ($xml->xpath('//m:si') ?: [] as $si) {
            $cadenas[] = $this->textoDe($si);
        }

        return $cadenas;
    }

    /**
     * Nombre de cada hoja y la parte del zip donde vive su XML.
     *
     * Se lee el orden real del libro (`workbook.xml`), no el nombre de archivo:
     * quien reordena las hojas en Excel no cambia `sheet1.xml` de sitio, y el
     * orden es lo que decide cuál es «Categorias» y cuál «Productos».
     *
     * @return array<string, string>
     */
    private function mapaDeHojas(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');

        if ($workbook === false) {
            throw new RuntimeException('El Excel no tiene hoja de cálculo.');
        }

        $xml = $this->cargar($workbook);

        if ($xml === null) {
            throw new RuntimeException('No se pudo leer la estructura del Excel.');
        }

        $xml->registerXPathNamespace('m', self::NS);
        $xml->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $rels = $this->relacionesHoja($zip);

        $hojas = [];

        foreach ($xml->xpath('//m:sheets/m:sheet') ?: [] as $sheet) {
            $atributos = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = (string) ($atributos['id'] ?? '');

            $destino = $rels[$rid] ?? null;

            if ($destino === null) {
                continue;
            }

            // Los atributos simples se leen con `attributes()`: el acceso por
            // corchetes falla cuando el nodo viene de un `children($ns)`.
            $nombre = (string) ($sheet->attributes()['name'] ?? '');

            $hojas[$nombre === '' ? 'Hoja'.count($hojas) : $nombre] = $this->normalizarParte($destino);
        }

        return $hojas;
    }

    /**
     * @return array<string, string>  rId => destino
     */
    private function relacionesHoja(ZipArchive $zip): array
    {
        $contenido = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($contenido === false) {
            return [];
        }

        $xml = $this->cargar($contenido);

        if ($xml === null) {
            return [];
        }

        $xml->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $rels = [];

        foreach ($xml->xpath('//p:Relationship') ?: [] as $rel) {
            $atributos = $rel->attributes();
            $id = (string) ($atributos['Id'] ?? '');
            $destino = (string) ($atributos['Target'] ?? '');

            if ($id !== '' && $destino !== '') {
                $rels[$id] = $destino;
            }
        }

        return $rels;
    }

    private function normalizarParte(string $destino): string
    {
        // Dentro del zip todo cuelga de la raíz del paquete; los destinos van
        // relativos a `xl/` y pueden empezar con barra.
        $destino = ltrim($destino, '/');

        if (str_starts_with($destino, 'xl/')) {
            return $destino;
        }

        return 'xl/'.$destino;
    }

    // ---- Lectura de una hoja -------------------------------------------------

    /**
     * @param  array<int, string>  $compartidas
     * @return array<int, array<int, string>>
     */
    private function leerHoja(string $contenido, array $compartidas): array
    {
        $xml = $this->cargar($contenido);

        if ($xml === null) {
            return [];
        }

        $xml->registerXPathNamespace('m', self::NS);

        $filas = [];

        foreach ($xml->xpath('//m:sheetData/m:row') ?: [] as $fila) {
            $celdas = [];
            $ultimaColumna = -1;

            foreach ($fila->children(self::NS) as $celda) {
                $referencia = (string) ($celda->attributes()['r'] ?? '');
                $columna = $referencia === '' ? $ultimaColumna + 1 : $this->indiceColumna($referencia);
                $ultimaColumna = $columna;

                $celdas[$columna] = $this->valorDe($celda, $compartidas);
            }

            if ($celdas === []) {
                continue;
            }

            // Rellena huecos para que las posiciones sean estables.
            $ancho = max(array_keys($celdas)) + 1;
            $completa = array_fill(0, $ancho, '');

            foreach ($celdas as $columna => $valor) {
                $completa[$columna] = $valor;
            }

            // Una fila en blanco (solo celdas vacías) no aporta nada.
            if (implode('', $completa) === '') {
                continue;
            }

            $filas[] = $completa;
        }

        return $filas;
    }

    /**
     * @param  array<int, string>  $compartidas
     */
    private function valorDe(SimpleXMLElement $celda, array $compartidas): string
    {
        $tipo = (string) ($celda->attributes()['t'] ?? '');

        if ($tipo === 's') {
            $indice = (int) ($celda->v ?? -1);

            return $compartidas[$indice] ?? '';
        }

        if ($tipo === 'inlineStr') {
            return $this->textoDe($celda);
        }

        if ($tipo === 'b') {
            return (string) $celda->v === '1' ? 'SI' : 'NO';
        }

        if (isset($celda->v)) {
            return trim((string) $celda->v);
        }

        // Celdas con `t="str"` (resultado de fórmula) o sin hijos.
        return trim($this->textoDe($celda));
    }

    /**
     * Concatena todos los `<t>` que cuelguen del nodo: cubre tanto el inline
     * string simple como el que va partido en varias `<r>` con formato.
     */
    private function textoDe(SimpleXMLElement $nodo): string
    {
        $nodo->registerXPathNamespace('m', self::NS);

        $partes = [];

        foreach ($nodo->xpath('.//m:t') ?: [] as $t) {
            $partes[] = (string) $t;
        }

        if ($partes !== []) {
            return implode('', $partes);
        }

        return trim((string) $nodo);
    }

    private function indiceColumna(string $referencia): int
    {
        // "B12" -> 1. Solo interesan las letras.
        if (! preg_match('/^([A-Z]+)/i', $referencia, $coincidencia)) {
            return 0;
        }

        $letras = strtoupper($coincidencia[1]);
        $indice = 0;

        for ($i = 0; $i < strlen($letras); $i++) {
            $indice = $indice * 26 + (ord($letras[$i]) - 64);
        }

        return $indice - 1;
    }

    private function cargar(string $contenido): ?SimpleXMLElement
    {
        $anterior = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($contenido);
        } catch (Throwable) {
            $xml = false;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }

        return $xml === false ? null : $xml;
    }

    // ---- CSV -----------------------------------------------------------------

    private function esCsv(string $ruta): bool
    {
        return strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) === 'csv';
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function leerCsv(string $ruta): array
    {
        $manejador = fopen($ruta, 'r');

        if ($manejador === false) {
            throw new RuntimeException('No se pudo abrir el CSV.');
        }

        $filas = [];

        try {
            // Quita el BOM que Excel pone al guardar como «CSV UTF-8».
            $primera = true;

            while (($fila = fgetcsv($manejador)) !== false) {
                if ($primera) {
                    if (isset($fila[0])) {
                        $fila[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $fila[0]);
                    }

                    $primera = false;
                }

                $filas[] = array_map(fn ($valor) => trim((string) $valor), $fila);
            }
        } finally {
            fclose($manejador);
        }

        return $filas;
    }
}
