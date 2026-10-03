<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\Venta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buscador global desde el teléfono.
 *
 * Es la versión API del buscador del topbar del panel: productos, aparatos,
 * ventas, clientes y compras. Cada grupo se consulta **solo si el usuario tiene
 * el permiso del módulo**; un resultado que lleva a una pantalla prohibida
 * revelaría que existe, que es peor que no encontrarlo.
 */
class BusquedaController extends Controller
{
    /** Cuántos resultados se muestran por grupo antes de pedir afinar. */
    private const POR_GRUPO = 8;

    public function index(Request $request): JsonResponse
    {
        $termino = trim((string) $request->query('termino', ''));

        // Con menos de dos letras cada tecla dispararía una consulta a todos
        // los módulos.
        if (mb_strlen($termino) < 2) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => array_values($this->buscar($request, $termino)),
        ]);
    }

    /**
     * @return array<int, array{clave: string, titulo: string, items: array<int, array<string, mixed>>}>
     */
    private function buscar(Request $request, string $termino): array
    {
        $usuario = $request->user();
        $grupos = [];

        if ($usuario?->can('productos.ver')) {
            $grupos[] = ['clave' => 'productos', 'titulo' => 'Productos', 'items' => $this->productos($termino)];
        }

        if ($usuario?->can('unidades.ver')) {
            $grupos[] = [
                'clave' => 'unidades',
                'titulo' => 'Aparatos',
                'items' => $this->unidades($termino, $usuario->can('ventas.ver')),
            ];
        }

        if ($usuario?->can('ventas.ver')) {
            $grupos[] = ['clave' => 'ventas', 'titulo' => 'Ventas', 'items' => $this->ventas($termino)];
        }

        if ($usuario?->can('clientes.ver')) {
            $grupos[] = ['clave' => 'clientes', 'titulo' => 'Clientes', 'items' => $this->clientes($termino)];
        }

        if ($usuario?->can('compras.ver')) {
            $grupos[] = ['clave' => 'compras', 'titulo' => 'Compras', 'items' => $this->compras($termino)];
        }

        // Un grupo vacío no aporta nada: se cae y solo se enseña lo que de
        // verdad encontró.
        return array_values(array_filter($grupos, fn (array $grupo): bool => $grupo['items'] !== []));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productos(string $termino): array
    {
        return Producto::query()
            ->with(['categoria', 'marca'])
            ->withCount(['unidades as en_stock_count' => fn ($q) => $q->where('estado', 'en_stock')])
            ->buscar($termino)
            ->orderBy('nombre')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(fn (Producto $producto): array => [
                'tipo' => 'producto',
                'id' => $producto->id,
                'titulo' => $producto->nombre,
                'detalle' => implode(' · ', array_filter([
                    $producto->marca?->nombre,
                    $producto->categoria?->nombre,
                ])),
                'nota' => $producto->en_stock_count.' en stock',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function unidades(string $termino, bool $puedeVerVentas): array
    {
        return Unidad::query()
            ->with(['producto', 'ventaDetalle.venta'])
            ->buscar($termino)
            ->orderByDesc('id')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(function (Unidad $unidad) use ($puedeVerVentas): array {
                // Un aparato vendido se consulta por su venta; uno en stock, por
                // su ficha. Sin permiso de ventas, el enlace a la venta no viaja.
                $venta = $unidad->ventaDetalle?->venta;
                $vendido = $venta !== null && $puedeVerVentas;

                return [
                    'tipo' => 'unidad',
                    'id' => $unidad->id,
                    'venta_id' => $vendido ? $venta->id : null,
                    'titulo' => $unidad->serial ?: $unidad->codigo_interno,
                    'detalle' => implode(' · ', array_filter([
                        $unidad->producto?->nombre,
                        $unidad->serial ? $unidad->codigo_interno : null,
                    ])),
                    'nota' => Unidad::ESTADOS[$unidad->estado] ?? $unidad->estado,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ventas(string $termino): array
    {
        return Venta::query()
            ->with('cliente.persona')
            ->buscar($termino)
            ->orderByDesc('vendida_en')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(fn (Venta $venta): array => [
                'tipo' => 'venta',
                'id' => $venta->id,
                'titulo' => $venta->codigo,
                'detalle' => implode(' · ', array_filter([
                    $venta->cliente?->persona?->nombre_completo,
                    $venta->vendida_en?->format('d/m/Y H:i'),
                ])),
                'nota' => 'Bs '.number_format((float) $venta->total, 2, ',', '.')
                    .($venta->esta_anulada ? ' · Anulada' : ''),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function clientes(string $termino): array
    {
        return Cliente::query()
            ->with('persona')
            ->buscar($termino)
            ->orderBy('codigo')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(fn (Cliente $cliente): array => [
                'tipo' => 'cliente',
                'id' => $cliente->id,
                'titulo' => $cliente->persona?->nombre_completo ?? $cliente->codigo,
                'detalle' => implode(' · ', array_filter([
                    $cliente->codigo,
                    $cliente->persona?->carnet,
                    $cliente->persona?->celular,
                ])),
                'nota' => null,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function compras(string $termino): array
    {
        return Compra::query()
            ->with('proveedor')
            ->buscar($termino)
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(fn (Compra $compra): array => [
                'tipo' => 'compra',
                'id' => $compra->id,
                'titulo' => $compra->codigo,
                'detalle' => implode(' · ', array_filter([
                    $compra->proveedor?->nombre,
                    $compra->fecha_compra?->format('d/m/Y'),
                    $compra->numero_factura ? 'Fact. '.$compra->numero_factura : null,
                ])),
                'nota' => 'Bs '.number_format((float) $compra->total, 2, ',', '.')
                    .' · '.(Compra::ESTADOS[$compra->estado] ?? $compra->estado),
            ])
            ->all();
    }
}
