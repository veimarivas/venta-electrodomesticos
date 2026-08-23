<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompraResource;
use App\Http\Resources\UnidadResource;
use App\Models\Compra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consulta de órdenes de compra desde la app.
 *
 * Solo lectura, y aquí con más motivo que en el resto: **recepcionar una compra
 * genera las unidades físicas del almacén** y congela sus costos. Es una
 * operación que se hace con la mercadería delante, contando cajas y anotando
 * seriales; disparar eso desde un teléfono, sin la mercadería a la vista,
 * dejaría el inventario diciendo que hay aparatos que nadie ha recibido.
 */
class CompraController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
            'estado' => ['nullable', 'in:borrador,recepcionada,anulada'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $compras = Compra::query()
            ->with(['proveedor', 'user'])
            ->withCount(['detalles', 'unidades'])
            ->buscar($datos['buscar'] ?? null)
            ->when(isset($datos['proveedor_id']), fn ($q) => $q->where('proveedor_id', $datos['proveedor_id']))
            ->when(isset($datos['estado']), fn ($q) => $q->where('estado', $datos['estado']))
            ->when(isset($datos['desde']), fn ($q) => $q->whereDate('fecha_compra', '>=', $datos['desde']))
            ->when(isset($datos['hasta']), fn ($q) => $q->whereDate('fecha_compra', '<=', $datos['hasta']))
            ->orderByDesc('fecha_compra')
            // Desempate estable: sin él dos compras del mismo día pueden
            // saltar de página y aparecer duplicadas.
            ->orderByDesc('id')
            ->paginate($datos['por_pagina'] ?? 20);

        return CompraResource::collection($compras);
    }

    public function show(Request $request, Compra $compra): CompraResource
    {
        $compra->load([
            'proveedor',
            'user',
            'detalles' => fn ($d) => $d->with('producto')
                ->withCount('unidades')
                ->orderBy('id'),
        ]);

        $compra->loadCount(['detalles', 'unidades']);

        return (new CompraResource($compra))->conDetalle();
    }

    /**
     * Aparatos que entraron al almacén con esta compra.
     *
     * Van en su propia ruta y no dentro de la ficha: una compra de cien
     * unidades haría una respuesta enorme para una pantalla que solo las
     * enseña si se piden.
     */
    public function unidades(Request $request, Compra $compra): JsonResponse
    {
        abort_unless($request->user()?->can('unidades.ver') ?? false, 403);

        $unidades = $compra->unidades()
            ->with('producto')
            ->orderBy('codigo_interno')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => UnidadResource::collection($unidades)->resolve($request),
            'meta' => [
                'total' => $compra->unidades()->count(),
                'en_stock' => $compra->unidades()->disponibles()->count(),
            ],
        ]);
    }
}
