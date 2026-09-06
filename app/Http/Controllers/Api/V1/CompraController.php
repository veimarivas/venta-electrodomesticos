<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompraResource;
use App\Http\Resources\UnidadResource;
use App\Models\Compra;
use App\Support\RecepcionDeCompra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

/**
 * Consulta y recepción de órdenes de compra desde la app.
 *
 * La recepción genera las unidades físicas del almacén y congela sus costos.
 * Originalmente era solo lectura, pero se abrió para que el mostrador pueda
 * recepcionar con el teléfono —la mercadería está delante, se cuenta caja
 * por caja— y no tener que volver al panel.
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

    /**
     * Recepciona una compra: genera las unidades físicas del almacén.
     *
     * La compra debe estar en estado `borrador` y tener al menos una línea.
     * La recepción es atómica: o se genera todo el lote o no se crea nada.
     *
     * La app confirma antes de enviar: una compra recepcionada congela sus
     * costos y no se puede deshacer sin anularla.
     */
    public function recepcionar(Request $request, Compra $compra): JsonResponse
    {
        abort_unless($request->user()?->can('compras.crear') ?? false, 403);

        if (! $compra->es_borrador) {
            return response()->json([
                'message' => 'Solo se puede recepcionar una compra en estado borrador.',
            ], 422);
        }

        try {
            $generadas = app(RecepcionDeCompra::class)->recepcionar($compra->fresh());

            $compra->refresh()->load([
                'proveedor',
                'user',
                'detalles' => fn ($d) => $d->with('producto')->withCount('unidades'),
            ]);
            $compra->loadCount(['detalles', 'unidades']);

            return response()->json([
                'message' => "Compra recepcionada. Se generaron {$generadas} unidades.",
                'data' => (new CompraResource($compra))->conDetalle()->resolve($request),
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
