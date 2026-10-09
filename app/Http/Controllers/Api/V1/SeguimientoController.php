<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ResumenDiario;
use App\Support\SeguimientoDeVendedores;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * El seguimiento del administrador desde el teléfono (`reportes.seguimiento`):
 * el resumen del día y las ventas por vendedor. Los cálculos son los mismos
 * del panel.
 */
class SeguimientoController extends Controller
{
    public function resumenDiario(Request $request): JsonResponse
    {
        $datos = $request->validate(['fecha' => ['nullable', 'date', 'before_or_equal:today']]);

        return response()->json([
            'data' => app(ResumenDiario::class)->del(Carbon::parse($datos['fecha'] ?? now())),
        ]);
    }

    public function vendedores(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
            'vendedor_id' => ['nullable', 'integer'],
        ]);

        $desde = Carbon::parse($datos['desde'] ?? now());
        $hasta = Carbon::parse($datos['hasta'] ?? now());

        if ($hasta->lt($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return response()->json([
            'data' => app(SeguimientoDeVendedores::class)->entre($desde, $hasta, $datos['vendedor_id'] ?? null),
            'meta' => ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()],
        ]);
    }
}
