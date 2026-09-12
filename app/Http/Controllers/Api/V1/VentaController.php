<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\VentaResource;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Support\RegistroDeVenta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

/**
 * Consulta del histórico de ventas desde la app.
 *
 * Registrar ventas se hace en el mostrador (POS). Anular y recibir el recibo
 * sí están aquí porque son operaciones que el admin puede hacer desde el
 * teléfono cuando no está en la tienda.
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
            ->with(['cliente.persona', 'user', 'detalles.producto.marca'])
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
            $venta->load(['detalles.unidad', 'detalles.producto.marca', 'cliente.persona', 'user'])
        );
    }

    /**
     * Anular una venta desde el teléfono.
     *
     * Requiere `ventas.anular`. Devuelve las unidades al stock, actualiza el
     * kardex y cancela créditos/entregas pendientes. El motivo es obligatorio.
     */
    public function anular(Request $request, Venta $venta): JsonResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:4', 'max:500'],
        ]);

        if ($venta->esta_anulada) {
            return response()->json([
                'message' => 'Esta venta ya estaba anulada.',
            ], 422);
        }

        try {
            $devueltas = app(RegistroDeVenta::class)->anular($venta, $datos['motivo']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Venta anulada: {$devueltas} aparato(s) vuelve(n) al stock.",
            'data' => new VentaResource(
                $venta->fresh()->load(['detalles.unidad', 'detalles.producto.marca', 'cliente.persona', 'user'])
            ),
        ]);
    }

    /**
     * Devolver UN aparato de la venta sin anularla, desde el teléfono.
     *
     * Va con el permiso de anular: devolver una línea es una acción más pequeña
     * que anular la venta entera, así que quien puede lo más puede lo menos. La
     * lógica es la misma del panel (`RegistroDeVenta::devolver`): el aparato
     * vuelve al stock, la venta recalcula sus importes y, si se devolvieron
     * todos, queda anulada.
     */
    public function devolver(Request $request, Venta $venta): JsonResponse
    {
        $datos = $request->validate([
            'venta_detalle_id' => ['required', 'integer'],
            'motivo' => ['required', 'string', 'min:4', 'max:255'],
        ]);

        if ($venta->esta_anulada) {
            return response()->json([
                'message' => 'La venta está anulada: sus aparatos ya volvieron al stock.',
            ], 422);
        }

        $detalle = VentaDetalle::query()
            ->where('venta_id', $venta->id)
            ->with(['venta', 'unidad'])
            ->find($datos['venta_detalle_id']);

        if ($detalle === null) {
            return response()->json([
                'message' => 'Esa línea no pertenece a la venta.',
            ], 422);
        }

        try {
            app(RegistroDeVenta::class)->devolver($detalle, $datos['motivo']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Aparato devuelto y de vuelta en el stock.',
            'data' => new VentaResource(
                $venta->fresh()->load(['detalles.unidad', 'detalles.producto.marca', 'cliente.persona', 'user'])
            ),
        ]);
    }

    /**
     * Recibo de una venta en PDF.
     *
     * Devuelve el PDF como base64 para que la app pueda mostrarlo o guardarlo.
     * El recibo se genera al vuelo —la venta es inmutable—.
     */
    public function recibo(Venta $venta): Response
    {
        $venta->load([
            'detalles.unidad',
            'detalles.producto',
            'cliente.persona',
            'user',
            'qrCobro',
        ]);

        $pdf = Pdf::loadView('backend.ventas.recibo', [
            'venta' => $venta,
            'metodosPago' => Venta::METODOS_PAGO,
            'tienda' => config('app.name'),
        ])->setPaper([0, 0, 226.77, $this->alto($venta)]);

        $contenido = $pdf->output();

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Recibo-'.($venta->codigo ?? 'venta').'.pdf"',
            'Content-Length' => strlen($contenido),
        ]);
    }

    /**
     * Alto del ticket, estimado a partir de lo que va a imprimirse.
     */
    private function alto(Venta $venta): float
    {
        $base = 400;
        $porLinea = 46;
        $extras = 0;

        if ($venta->metodo_pago === 'mixto') {
            $extras += 30;
        }

        if ($venta->esta_anulada) {
            $extras += 50;
        }

        if (filled($venta->notas)) {
            $extras += 20 + (ceil(mb_strlen($venta->notas) / 38) * 12);
        }

        return $base + ($venta->detalles->count() * $porLinea) + $extras;
    }
}
