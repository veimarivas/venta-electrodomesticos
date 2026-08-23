<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Reportes;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Indicadores para la pantalla principal de la app.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly Reportes $reportes) {}

    /**
     * Resuelve ?rango=hoy|semana|mes|anio a un par de fechas.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function rango(Request $request): array
    {
        return match ($request->query('rango', 'hoy')) {
            'semana' => [now()->startOfWeek(), now()->endOfWeek()],
            'mes' => [now()->startOfMonth(), now()->endOfMonth()],
            'anio' => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    /**
     * Totales del rango con su comparativo contra el período anterior.
     *
     * Costos y ganancia solo viajan a quien puede verlos: la app la puede
     * tener un vendedor, y el margen de la tienda no es dato suyo.
     */
    public function resumen(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        $datos = $this->reportes->comparativo($desde, $hasta);

        if (! $request->user()->can('reportes.ver_costos')) {
            unset($datos['actual']['ganancia'], $datos['actual']['margen']);
            unset($datos['anterior']['ganancia'], $datos['anterior']['margen']);
            unset($datos['variacion']['ganancia']);
        }

        return response()->json([
            'rango' => $request->query('rango', 'hoy'),
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            ...$datos,
        ]);
    }

    /** Serie diaria para la gráfica de la app. */
    public function grafica(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        $verCostos = $request->user()->can('reportes.ver_costos');

        return response()->json([
            'serie' => $this->reportes->porDia($desde, $hasta)
                ->map(fn (array $dia): array => [
                    'fecha' => $dia['fecha'],
                    'etiqueta' => $dia['etiqueta'],
                    'ventas' => $dia['ventas'],
                    'ingreso' => $dia['ingreso'],
                    ...($verCostos ? ['ganancia' => $dia['ganancia']] : []),
                ])
                ->values(),
        ]);
    }

    public function topProductos(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        $verCostos = $request->user()->can('reportes.ver_costos');

        return response()->json([
            'productos' => $this->reportes->topProductos($desde, $hasta, 10)
                ->map(fn ($p): array => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'sku' => $p->sku,
                    'unidades' => (int) $p->unidades,
                    'ingreso' => (float) $p->ingreso,
                    ...($verCostos ? ['ganancia' => (float) $p->ganancia] : []),
                ])
                ->values(),
        ]);
    }
}
