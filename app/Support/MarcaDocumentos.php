<?php

namespace App\Support;

use App\Models\Tienda;

/**
 * La marca de los documentos del cliente: logo, nombre, lema y contacto.
 *
 * Todos los PDF (recibo, estado de cuenta, orden de servicio, asistencia) la
 * toman de aquí para verse de una sola pieza. El logo va **embebido** en el
 * PDF (data URI): DomPDF no tiene que ir a buscar archivos y el documento se
 * ve igual en el panel, en el teléfono y reenviado por WhatsApp.
 */
class MarcaDocumentos
{
    /**
     * El logo de documentos: el de la marca sin la fila de iconos, en JPG sobre
     * blanco y a 420 px. Con el PNG transparente cada PDF pesaba ~1 MB (DomPDF
     * guarda el canal alfa aparte); así ronda los 40 KB y se manda bien por
     * WhatsApp.
     */
    private const LOGO = 'assets/images/marca-documento.jpg';

    private static ?string $logo = null;

    /**
     * @return array{nombre: string, lema: ?string, logo: ?string, direccion: ?string, sucursal: ?string, telefono: ?string, nit: ?string}
     */
    public static function datos(?Tienda $tienda = null): array
    {
        return [
            'nombre' => (string) config('app.nombre_comercial', config('app.name')),
            'lema' => self::texto(config('app.lema')),
            'logo' => self::logo(),
            // La dirección de la tienda del documento, si la hay; si no, la
            // general de la empresa.
            'direccion' => self::texto($tienda?->direccion) ?? self::texto(config('app.tienda_direccion')),
            'sucursal' => $tienda?->nombre,
            'telefono' => self::texto(config('app.tienda_telefono')),
            'nit' => self::texto(config('app.tienda_nit')),
        ];
    }

    /** El logo como data URI, o null si el archivo no está (no rompe el PDF). */
    public static function logo(): ?string
    {
        if (self::$logo !== null) {
            return self::$logo === '' ? null : self::$logo;
        }

        $ruta = public_path(self::LOGO);

        self::$logo = is_file($ruta)
            ? 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents($ruta))
            : '';

        return self::$logo === '' ? null : self::$logo;
    }

    private static function texto(mixed $valor): ?string
    {
        $valor = is_string($valor) ? trim($valor) : '';

        return $valor === '' ? null : $valor;
    }
}
