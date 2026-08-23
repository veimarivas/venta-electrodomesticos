<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\Unidad;
use App\Models\Venta;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Consultas de los reportes de ventas y rentabilidad.
 *
 * Vive fuera del componente Livewire para que la misma lógica sirva luego a la
 * API de la app Flutter sin duplicarse.
 *
 * Regla que atraviesa todo el archivo: **las ventas anuladas no cuentan**. Una
 * anulada devolvió su mercadería al stock y su dinero al cliente; sumarla
 * inflaría todos los indicadores.
 */
class Reportes
{
    /**
     * Totales del período: cuántas ventas, cuánto se cobró y cuánto se ganó.
     *
     * @return array{ventas: int, unidades: int, ingreso: float, ganancia: float, ticket: float, margen: float}
     */
    public function resumen(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $totales = Venta::completadas()
            ->whereBetween('vendida_en', [$desde, $hasta])
            ->selectRaw('count(*) as ventas, sum(total) as ingreso, sum(ganancia) as ganancia')
            ->first();

        $unidades = Venta::completadas()
            ->whereBetween('vendida_en', [$desde, $hasta])
            ->withCount('detalles')
            ->get()
            ->sum('detalles_count');

        $cantidad = (int) $totales->ventas;
        $ingreso = (float) $totales->ingreso;
        $ganancia = (float) $totales->ganancia;

        return [
            'ventas' => $cantidad,
            'unidades' => $unidades,
            'ingreso' => $ingreso,
            'ganancia' => $ganancia,
            // Ticket promedio: sin ventas la división sería por cero.
            'ticket' => $cantidad > 0 ? round($ingreso / $cantidad, 2) : 0.0,
            'margen' => $ingreso > 0 ? round($ganancia / $ingreso * 100, 1) : 0.0,
        ];
    }

    /**
     * Resumen del período junto al del período anterior de la misma duración,
     * con la variación porcentual de cada métrica.
     *
     * Es lo que hace útil un indicador en el móvil: «Bs 4.000» no dice nada
     * sin saber si la semana pasada fueron 2.000 o 9.000.
     *
     * @return array{actual: array, anterior: array, variacion: array<string, float|null>}
     */
    public function comparativo(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $actual = $this->resumen($desde, $hasta);

        // El período anterior es igual de largo y termina justo antes.
        $duracion = $desde->diffInSeconds($hasta);
        $hastaAnterior = $desde->copy()->subSecond();
        $desdeAnterior = $hastaAnterior->copy()->subSeconds($duracion);

        $anterior = $this->resumen($desdeAnterior, $hastaAnterior);

        // Sin base con la que comparar, la variación es null, no 0 ni 100:
        // decir «+100 %» desde cero es inventar una tendencia.
        $variar = function (float $ahora, float $antes): ?float {
            if ($antes <= 0.0) {
                return null;
            }

            return round(($ahora - $antes) / $antes * 100, 1);
        };

        return [
            'actual' => $actual,
            'anterior' => $anterior,
            'variacion' => [
                'ingreso' => $variar($actual['ingreso'], $anterior['ingreso']),
                'ganancia' => $variar($actual['ganancia'], $anterior['ganancia']),
                'ventas' => $variar((float) $actual['ventas'], (float) $anterior['ventas']),
                'ticket' => $variar($actual['ticket'], $anterior['ticket']),
            ],
        ];
    }

    /**
     * Productos cuyo stock disponible cayó a su mínimo o por debajo.
     *
     * @return Collection<int, \App\Models\Producto>
     */
    public function stockBajoMinimo(int $limite = 50): Collection
    {
        return Producto::query()
            ->with('marca')
            ->where('activo', true)
            ->where('stock_minimo', '>', 0)
            ->withCount(['unidades as disponibles' => fn ($q) => $q->disponibles()])
            ->get()
            ->filter(fn (Producto $p): bool => $p->disponibles <= $p->stock_minimo)
            ->sortBy('disponibles')
            ->take($limite)
            ->values();
    }

    /**
     * Serie por día del período, para la gráfica. Se rellenan los días sin
     * ventas con ceros: una gráfica que salta del lunes al jueves miente
     * sobre el ritmo del negocio.
     *
     * @return Collection<int, array{fecha: string, etiqueta: string, ingreso: float, ganancia: float, ventas: int}>
     */
    public function porDia(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        $filas = Venta::completadas()
            ->whereBetween('vendida_en', [$desde, $hasta])
            ->selectRaw('DATE(vendida_en) as dia, count(*) as ventas, sum(total) as ingreso, sum(ganancia) as ganancia')
            ->groupBy('dia')
            ->get()
            ->keyBy('dia');

        $serie = collect();
        $cursor = $desde->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($hasta)) {
            $clave = $cursor->format('Y-m-d');
            $fila = $filas->get($clave);

            $serie->push([
                'fecha' => $clave,
                'etiqueta' => $cursor->format('d/m'),
                'ventas' => (int) ($fila->ventas ?? 0),
                'ingreso' => (float) ($fila->ingreso ?? 0),
                'ganancia' => (float) ($fila->ganancia ?? 0),
            ]);

            $cursor->addDay();
        }

        return $serie;
    }

    /**
     * Productos más vendidos del período, por unidades.
     *
     * @return Collection<int, object>
     */
    public function topProductos(CarbonInterface $desde, CarbonInterface $hasta, int $limite = 8): Collection
    {
        return Producto::query()
            ->select('productos.id', 'productos.nombre', 'productos.sku')
            ->join('venta_detalles', 'venta_detalles.producto_id', '=', 'productos.id')
            ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
            ->where('ventas.estado', 'completada')
            ->whereBetween('ventas.vendida_en', [$desde, $hasta])
            ->selectRaw('count(*) as unidades')
            ->selectRaw('sum(venta_detalles.precio_unitario - venta_detalles.descuento) as ingreso')
            ->selectRaw('sum(venta_detalles.ganancia) as ganancia')
            ->groupBy('productos.id', 'productos.nombre', 'productos.sku')
            ->orderByDesc('unidades')
            ->limit($limite)
            ->get();
    }

    /**
     * Ventas por vendedor: quién vendió cuánto en el período.
     *
     * @return Collection<int, object>
     */
    public function porVendedor(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        return Venta::completadas()
            ->whereBetween('vendida_en', [$desde, $hasta])
            ->join('users', 'users.id', '=', 'ventas.user_id')
            ->selectRaw('users.id, users.name, count(*) as ventas, sum(ventas.total) as ingreso, sum(ventas.ganancia) as ganancia')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('ingreso')
            ->get();
    }

    /**
     * Reparto del ingreso por método de pago, para saber cuánto entró en caja.
     *
     * @return Collection<int, object>
     */
    public function porMetodoPago(CarbonInterface $desde, CarbonInterface $hasta): Collection
    {
        return Venta::completadas()
            ->whereBetween('vendida_en', [$desde, $hasta])
            ->selectRaw('metodo_pago, count(*) as ventas, sum(total) as ingreso')
            ->groupBy('metodo_pago')
            ->orderByDesc('ingreso')
            ->get();
    }

    /**
     * Rentabilidad por proveedor: cuánto se le compró y cuánto se ha
     * recuperado vendiendo su mercadería.
     *
     * El ingreso sale de `venta_detalles` (lo realmente cobrado), no del
     * precio de lista de las unidades.
     *
     * @return Collection<int, object>
     */
    public function rentabilidadPorProveedor(): Collection
    {
        return \App\Models\Proveedor::query()
            ->select('proveedores.id', 'proveedores.nombre')
            ->leftJoin('compras', function ($join) {
                $join->on('compras.proveedor_id', '=', 'proveedores.id')
                    ->where('compras.estado', '=', 'recepcionada')
                    ->whereNull('compras.deleted_at');
            })
            ->selectRaw('coalesce(sum(distinct compras.total), 0) as invertido')
            ->selectRaw('count(distinct compras.id) as compras')
            ->groupBy('proveedores.id', 'proveedores.nombre')
            ->orderByDesc('invertido')
            ->get()
            ->map(function ($proveedor): object {
                // El ingreso se calcula aparte: mezclarlo en el mismo GROUP BY
                // multiplicaría las filas por el join de detalles y daría
                // totales inflados.
                $unidades = Unidad::query()
                    ->join('compras', 'compras.id', '=', 'unidades.compra_id')
                    ->where('compras.proveedor_id', $proveedor->id)
                    ->pluck('unidades.id');

                $lineas = \App\Models\VentaDetalle::query()
                    ->whereIn('unidad_id', $unidades)
                    ->whereHas('venta', fn ($v) => $v->where('estado', 'completada'))
                    ->selectRaw('count(*) as vendidas')
                    ->selectRaw('sum(precio_unitario - descuento) as ingreso')
                    ->selectRaw('sum(ganancia) as ganancia')
                    ->first();

                $proveedor->unidades = $unidades->count();
                $proveedor->vendidas = (int) ($lineas->vendidas ?? 0);
                $proveedor->ingreso = (float) ($lineas->ingreso ?? 0);
                $proveedor->ganancia = (float) ($lineas->ganancia ?? 0);
                $proveedor->recuperado = (float) $proveedor->invertido > 0
                    ? round($proveedor->ingreso / (float) $proveedor->invertido * 100, 1)
                    : 0.0;

                return $proveedor;
            });
    }

    /**
     * Valor del inventario que sigue en el almacén: cuánto costó y cuánto
     * dejaría si se vendiera a precio de lista.
     *
     * @return array{unidades: int, costo: float, valor: float, potencial: float}
     */
    public function inventarioEnStock(): array
    {
        $totales = Unidad::disponibles()
            ->selectRaw('count(*) as unidades, sum(costo_unitario) as costo, sum(precio_venta) as valor')
            ->first();

        $costo = (float) $totales->costo;
        $valor = (float) $totales->valor;

        return [
            'unidades' => (int) $totales->unidades,
            'costo' => $costo,
            'valor' => $valor,
            'potencial' => $valor - $costo,
        ];
    }
}
