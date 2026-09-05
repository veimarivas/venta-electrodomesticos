<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Venta;
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

    /**
     * Cuánto vendió cada vendedor en el período.
     *
     * La **ganancia solo viaja a quien puede ver costos**: es el margen de la
     * tienda, y con el ingreso y el número de ventas ya se ve el rendimiento
     * de cada uno.
     */
    public function porVendedor(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        $verCostos = $request->user()->can('reportes.ver_costos');

        $filas = $this->reportes->porVendedor($desde, $hasta)->map(fn ($fila): array => [
            'id' => (int) $fila->id,
            'vendedor' => $fila->name,
            'ventas' => (int) $fila->ventas,
            'ingreso' => (float) $fila->ingreso,
            ...($verCostos ? ['ganancia' => (float) $fila->ganancia] : []),
        ]);

        return response()->json([
            'rango' => $request->query('rango', 'hoy'),
            'data' => $filas->values(),
        ]);
    }

    /**
     * Reparto del ingreso por método de pago: cuánto entró en caja y cuánto
     * por QR.
     *
     * Se devuelve la etiqueta ya traducida porque el histórico incluye métodos
     * retirados (`tarjeta`, `transferencia`) que la app no conoce: dejar que
     * los traduzca ella obligaría a mantener la lista en dos sitios.
     */
    public function porMetodoPago(Request $request): JsonResponse
    {
        [$desde, $hasta] = $this->rango($request);

        $filas = $this->reportes->porMetodoPago($desde, $hasta)->map(fn ($fila): array => [
            'metodo' => $fila->metodo_pago,
            'etiqueta' => Venta::METODOS_PAGO[$fila->metodo_pago] ?? $fila->metodo_pago,
            'ventas' => (int) $fila->ventas,
            'ingreso' => (float) $fila->ingreso,
        ]);

        return response()->json([
            'rango' => $request->query('rango', 'hoy'),
            'data' => $filas->values(),
        ]);
    }

    /**
     * Cuánto vale lo que hay en la estantería ahora mismo.
     *
     * No depende del período: es una foto del inventario, no un acumulado.
     * **El costo y el potencial de ganancia solo viajan con `ver_costos`**; el
     * número de unidades y el valor de venta los puede ver cualquiera con
     * acceso a reportes.
     */
    public function inventario(Request $request): JsonResponse
    {
        $datos = $this->reportes->inventarioEnStock();

        if (! $request->user()->can('reportes.ver_costos')) {
            unset($datos['costo'], $datos['potencial']);
        }

        return response()->json($datos);
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
                    'unidades' => (int) $p->unidades,
                    'ingreso' => (float) $p->ingreso,
                    ...($verCostos ? ['ganancia' => (float) $p->ganancia] : []),
                ])
                ->values(),
        ]);
    }
}
