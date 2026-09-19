<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ImportadorCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Carga masiva del catálogo desde la app.
 *
 * Descarga la plantilla y recibe el Excel relleno. Las reglas y la resolución
 * de la jerarquía viven en `App\Support\ImportadorCatalogo`, el mismo servicio
 * que usa el panel, para que un archivo importado por el teléfono no acabe con
 * criterios distintos a uno importado por la web.
 */
class CatalogoImportController extends Controller
{
    public function __construct(private readonly ImportadorCatalogo $importador) {}

    /**
     * Plantilla `.xlsx` con las hojas de categorías y productos.
     */
    public function plantilla(): StreamedResponse
    {
        $contenido = $this->importador->plantilla();

        return response()->streamDownload(
            fn () => print $contenido,
            'plantilla-catalogo.xlsx',
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }

    public function importar(Request $request): JsonResponse
    {
        // Se valida por extensión y no por MIME: un .xlsx es un ZIP y el
        // detector de contenido puede clasificarlo como `application/zip`.
        $request->validate([
            'archivo' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv'],
        ], [
            'archivo.required' => 'Adjunta el archivo de Excel.',
            'archivo.max' => 'El archivo no puede pesar más de 5 MB.',
            'archivo.extensions' => 'El archivo debe ser Excel (.xlsx) o CSV.',
        ]);

        $resumen = $this->importador->importar($request->file('archivo')->getRealPath());

        return response()->json([
            'mensaje' => $this->mensaje($resumen),
            'data' => $resumen,
        ]);
    }

    /**
     * @param  array<string, mixed>  $resumen
     */
    private function mensaje(array $resumen): string
    {
        $partes = [];

        if ($resumen['categorias_creadas'] > 0 || $resumen['categorias_actualizadas'] > 0) {
            $partes[] = "Categorías: {$resumen['categorias_creadas']} creadas, "
                ."{$resumen['categorias_actualizadas']} actualizadas";
        }

        if ($resumen['productos_creados'] > 0 || $resumen['productos_actualizados'] > 0) {
            $partes[] = "Productos: {$resumen['productos_creados']} creados, "
                ."{$resumen['productos_actualizados']} actualizados";
        }

        if ($resumen['marcas_creadas'] > 0) {
            $partes[] = "{$resumen['marcas_creadas']} marca(s) nuevas";
        }

        $mensaje = $partes === []
            ? 'No se encontraron filas para importar.'
            : 'Importación terminada. '.implode('. ', $partes).'.';

        if ($resumen['errores'] !== []) {
            $mensaje .= ' '.count($resumen['errores']).' fila(s) con problemas.';
        }

        return $mensaje;
    }
}
