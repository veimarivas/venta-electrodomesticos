<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VentaResource;
use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Consulta del histórico de ventas desde la app.
 *
 * Solo lectura: registrar y anular ventas se hace en el mostrador, con el
 * aparato delante. Exponer esas operaciones por API sin un flujo pensado para
 * el móvil invitaría a errores caros.
 */
class VentaController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'vendedor_id' => ['nullable', 'integer', 'exists:users,id'],
            'estado' => ['nullable', 'in:completada,anulada'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $ventas = Venta::query()
            ->with(['cliente.persona', 'user'])
            ->withCount('detalles')
            ->buscar($datos['buscar'] ?? null)
            ->when(isset($datos['desde']), fn ($q) => $q->whereDate('vendida_en', '>=', $datos['desde']))
            ->when(isset($datos['hasta']), fn ($q) => $q->whereDate('vendida_en', '<=', $datos['hasta']))
            ->when(isset($datos['vendedor_id']), fn ($q) => $q->where('user_id', $datos['vendedor_id']))
            ->when(isset($datos['estado']), fn ($q) => $q->where('estado', $datos['estado']))
            ->orderByDesc('vendida_en')
            // Desempate estable: sin él dos ventas del mismo segundo pueden
            // saltar de página y aparecer duplicadas.
            ->orderByDesc('id')
            ->paginate($datos['por_pagina'] ?? 20);

        return VentaResource::collection($ventas);
    }

    public function show(Request $request, Venta $venta): VentaResource
    {
        return new VentaResource(
            $venta->load(['detalles.unidad', 'detalles.producto', 'cliente.persona', 'user'])
        );
    }
}
