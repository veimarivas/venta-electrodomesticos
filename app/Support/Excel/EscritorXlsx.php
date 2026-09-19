<?php

namespace App\Support\Excel;

use RuntimeException;
use ZipArchive;

/**
 * Escribe un libro `.xlsx` con PHP nativo, sin depender de una librería.
 *
 * Un `.xlsx` es un ZIP con XML dentro. Para una plantilla de importación no
 * hace falta nada de lo que trae `phpoffice/phpspreadsheet` (fórmulas, estilos,
 * gráficos): solo celdas con texto o números. Armarlo aquí evita sumar una
 * dependencia de decenas de MB y mantiene el proyecto instalable sin red.
 *
 * Los textos van como *inline strings* y no en `sharedStrings.xml`: es válido
 * según el estándar y ahorra una tabla que, para celdas que no se repiten, no
 * aportaba nada.
 */
class EscritorXlsx
{
    /** Excel corta una celda a 32 767 caracteres. */
    private const MAX_CARACTERES = 32767;

    /**
     * Genera el contenido binario del libro.
     *
     * @param  array<int, array{nombre: string, filas: array<int, array<int, scalar|null>>}>  $hojas
     *         Cada hoja lleva un nombre (máx. 31 caracteres) y sus filas.
     */
    public function generar(array $hojas): string
    {
        if ($hojas === []) {
            throw new RuntimeException('Un libro necesita al menos una hoja.');
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('La extensión zip de PHP no está disponible.');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');

        if ($ruta === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal.');
        }

        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo xlsx para escritura.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes(count($hojas)));
        $zip->addFromString('_rels/.rels', $this->relsRaiz());
        $zip->addFromString('xl/workbook.xml', $this->workbook($hojas));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relsWorkbook(count($hojas)));
        $zip->addFromString('xl/styles.xml', $this->estilos());

        foreach (array_values($hojas) as $indice => $hoja) {
            $zip->addFromString(
                'xl/worksheets/sheet'.($indice + 1).'.xml',
                $this->hoja($hoja['filas'] ?? []),
            );
        }

        $zip->close();

        $contenido = file_get_contents($ruta);
        @unlink($ruta);

        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el xlsx generado.');
        }

        return $contenido;
    }

    private function contentTypes(int $hojas): string
    {
        $worksheets = '';

        for ($i = 1; $i <= $hojas; $i++) {
            $worksheets .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml"'
                .' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$worksheets
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function relsRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    /**
     * @param  array<int, array{nombre: string, filas: array<int, array<int, scalar|null>>}>  $hojas
     */
    private function workbook(array $hojas): string
    {
        $sheets = '';

        foreach (array_values($hojas) as $indice => $hoja) {
            $sheets .= '<sheet name="'.$this->escapar($this->nombreHoja($hoja['nombre'] ?? 'Hoja')).'"'
                .' sheetId="'.($indice + 1).'" r:id="rId'.($indice + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets>'
            .'</workbook>';
    }

    private function relsWorkbook(int $hojas): string
    {
        $rels = '';

        for ($i = 1; $i <= $hojas; $i++) {
            $rels .= '<Relationship Id="rId'.$i.'"'
                .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
                .' Target="worksheets/sheet'.$i.'.xml"/>';
        }

        $rels .= '<Relationship Id="rId'.($hojas + 1).'"'
            .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"'
            .' Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$rels
            .'</Relationships>';
    }

    private function estilos(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="1"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    /**
     * @param  array<int, array<int, scalar|null>>  $filas
     */
    private function hoja(array $filas): string
    {
        $xml = '';

        foreach (array_values($filas) as $numeroFila => $fila) {
            $n = $numeroFila + 1;
            $celdas = '';

            // La clave de la fila es su columna: así una fila con un hueco en
            // medio no desplaza las celdas de la derecha.
            foreach ($fila as $columna => $valor) {
                if ($valor === null || $valor === '') {
                    continue;
                }

                $referencia = $this->letraColumna((int) $columna).$n;
                $celdas .= $this->celda($referencia, $valor);
            }

            $xml .= '<row r="'.$n.'">'.$celdas.'</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$xml.'</sheetData>'
            .'</worksheet>';
    }

    private function celda(string $referencia, mixed $valor): string
    {
        // Los números se guardan como números: así Excel los ordena y los suma.
        // Un valor que llegue como cadena numérica («12,50») se deja como texto
        // a propósito para no adivinar el separador decimal.
        if (is_int($valor) || is_float($valor)) {
            return '<c r="'.$referencia.'"><v>'.$this->numero($valor).'</v></c>';
        }

        $texto = $this->escapar(mb_substr((string) $valor, 0, self::MAX_CARACTERES));

        return '<c r="'.$referencia.'" t="inlineStr"><is><t xml:space="preserve">'.$texto.'</t></is></c>';
    }

    private function numero(int|float $valor): string
    {
        if (is_int($valor)) {
            return (string) $valor;
        }

        $texto = rtrim(rtrim(sprintf('%.10F', $valor), '0'), '.');

        return $texto === '' || $texto === '-' ? '0' : $texto;
    }

    private function nombreHoja(string $nombre): string
    {
        // Excel prohíbe : \ / ? * [ ] y limita el nombre a 31 caracteres.
        $nombre = str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $nombre);
        $nombre = trim(mb_substr($nombre, 0, 31));

        return $nombre === '' ? 'Hoja' : $nombre;
    }

    private function escapar(string $texto): string
    {
        // Fuera los caracteres de control que XML no admite (salvo tab, salto de
        // línea y retorno); de lo contrario Excel se queja de un archivo corrupto.
        $texto = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto);

        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function letraColumna(int $indice): string
    {
        $letra = '';
        $indice++;

        while ($indice > 0) {
            $resto = ($indice - 1) % 26;
            $letra = chr(65 + $resto).$letra;
            $indice = intdiv($indice - 1, 26);
        }

        return $letra;
    }
}
