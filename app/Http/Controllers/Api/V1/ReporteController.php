<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\Unidad;
use App\Models\VentaDetalle;
use App\Support\ProrrateoDeGastos;
use App\Support\Reportes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reportes de rentabilidad e inventario para la app.
 */
class ReporteController extends Controller
{
    public function __construct(private readonly Reportes $reportes) {}

    /**
     * Rentabilidad de una compra: cuánto se invirtió y cuánto se ha recuperado.
     *
     * Mismo criterio que la pantalla web: el ingreso sale de venta_detalles
     * (lo realmente cobrado) y las ventas anuladas no cuentan.
     */
    public function rentabilidadDeCompra(Request $request, Compra $compra): JsonResponse
    {
        abort_unless($request->user()->can('reportes.ver_costos'), 403);

        $unidades = Unidad::where('compra_id', $compra->id)->get();
        $vendidas = $unidades->where('estado', 'vendido');
        $enStock = $unidades->where('estado', 'en_stock');

        $centavos = fn ($valor): int => ProrrateoDeGastos::aCentavos($valor);

        $lineas = VentaDetalle::query()
            ->whereIn('unidad_id', $vendidas->pluck('id'))
            ->whereHas('venta', fn ($v) => $v->where('estado', 'completada'))
            ->get();

        $inversion = $centavos($compra->total);
        $ingreso = (int) $lineas->sum(fn ($l) => $centavos($l->precio_unitario) - $centavos($l->descuento));
        $costoVendidas = (int) $lineas->sum(fn ($l) => $centavos($l->costo_unitario));
        $potencial = (int) $enStock->sum(fn ($u) => $centavos($u->precio_venta) - $centavos($u->costo_unitario));

        return response()->json([
            'compra' => [
                'id' => $compra->id,
                'codigo' => $compra->codigo,
                'fecha' => $compra->fecha_compra?->toDateString(),
                'proveedor' => $compra->proveedor?->nombre,
            ],
            'inversion' => (float) ProrrateoDeGastos::aDecimal($inversion),
            'unidades' => $unidades->count(),
            'vendidas' => $vendidas->count(),
            'en_stock' => $enStock->count(),
            'ingreso' => (float) ProrrateoDeGastos::aDecimal($ingreso),
            'ganancia' => (float) ProrrateoDeGastos::aDecimal($ingreso - $costoVendidas),
            'potencial' => (float) ProrrateoDeGastos::aDecimal($potencial),
            'recuperado' => $inversion > 0 ? round($ingreso / $inversion * 100, 1) : 0.0,
            'margen' => $ingreso > 0 ? round(($ingreso - $costoVendidas) / $ingreso * 100, 1) : 0.0,
        ]);
    }

    /** Productos por debajo (o justo en) su stock mínimo. */
    public function stockBajo(Request $request): JsonResponse
    {
        return response()->json([
            'productos' => $this->reportes->stockBajoMinimo()
                ->map(fn ($p): array => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'marca' => $p->marca?->nombre,
                    'disponibles' => (int) $p->disponibles,
                    'stock_minimo' => (int) $p->stock_minimo,
                    'agotado' => (int) $p->disponibles === 0,
                ])
                ->values(),
        ]);
    }

    /** Rentabilidad por proveedor, para la pantalla de análisis de la app. */
    public function rentabilidadPorProveedor(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('reportes.ver_costos'), 403);

        return response()->json([
            'proveedores' => $this->reportes->rentabilidadPorProveedor()
                ->map(fn ($p): array => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'compras' => (int) $p->compras,
                    'invertido' => (float) $p->invertido,
                    'unidades' => (int) $p->unidades,
                    'vendidas' => (int) $p->vendidas,
                    'ingreso' => (float) $p->ingreso,
                    'ganancia' => (float) $p->ganancia,
                    'recuperado' => (float) $p->recuperado,
                ])
                ->values(),
        ]);
    }
}
