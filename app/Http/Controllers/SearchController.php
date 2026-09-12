<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\Venta;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Buscador global del topbar: productos, seriales y ventas.
 *
 * Busca solo en los módulos que el usuario puede ver. Un resultado que
 * lleva a una pantalla prohibida sería peor que no encontrarlo: revelaría
 * que existe.
 */
class SearchController extends Controller
{
    /** Cuántos resultados se muestran por grupo antes de pedir afinar. */
    private const POR_GRUPO = 8;

    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));

        return view('backend.search.index', [
            'title' => 'Resultados de búsqueda',
            'breadcrumbs' => ['Inicio' => route('dashboard'), 'Búsqueda' => null],
            'query' => $query,
            'results' => $query === '' ? [] : $this->buscar($request, $query),
        ]);
    }

    /**
     * Salto desde un resultado al listado de unidades del producto.
     *
     * El producto viaja por sesión, igual que al entrar desde categorías:
     * el listado se abre ya filtrado sin exponer ningún id en la URL.
     */
    public function producto(Request $request, Producto $producto): RedirectResponse
    {
        abort_unless($request->user()?->can('unidades.ver'), 403);

        session()->put('producto_activo', $producto->id);

        return redirect()->route('inventario.unidades.index');
    }

    /**
     * @return array<string, array{titulo: string, icono: string, items: array<int, array<string, mixed>>}>
     */
    private function buscar(Request $request, string $query): array
    {
        $usuario = $request->user();
        $grupos = [];

        if ($usuario?->can('productos.ver')) {
            $grupos['productos'] = [
                'titulo' => 'Productos',
                'icono' => 'ri-shopping-bag-3-line',
                'items' => $this->productos($query, $usuario->can('unidades.ver')),
            ];
        }

        if ($usuario?->can('unidades.ver')) {
            $grupos['unidades'] = [
                'titulo' => 'Aparatos por serial',
                'icono' => 'ri-barcode-line',
                'items' => $this->unidades($query, $usuario->can('ventas.ver')),
            ];
        }

        if ($usuario?->can('ventas.ver')) {
            $grupos['ventas'] = [
                'titulo' => 'Ventas',
                'icono' => 'ri-shopping-cart-2-line',
                'items' => $this->ventas($query),
            ];
        }

        if ($usuario?->can('clientes.ver')) {
            $grupos['clientes'] = [
                'titulo' => 'Clientes',
                'icono' => 'ri-user-3-line',
                'items' => $this->clientes($query),
            ];
        }

        if ($usuario?->can('compras.ver')) {
            $grupos['compras'] = [
                'titulo' => 'Compras',
                'icono' => 'ri-shopping-basket-2-line',
                'items' => $this->compras($query),
            ];
        }

        // Un grupo vacío no aporta nada: se cae y así la página solo muestra
        // lo que de verdad encontró.
        return array_filter($grupos, fn (array $grupo) => $grupo['items'] !== []);
    }

    /**
     * Sugerencias mientras se escribe, para el desplegable del topbar.
     *
     * Devuelve la misma forma que la página de resultados, pero pensada para
     * pintarse al vuelo. Con menos de dos letras no busca: cada tecla
     * dispararía una consulta a todos los módulos.
     */
    public function sugerencias(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => array_values($this->buscar($request, $query)),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function productos(string $query, bool $puedeVerUnidades): array
    {
        return Producto::query()
            ->with(['categoria', 'marca'])
            ->withCount(['unidades as en_stock_count' => fn ($q) => $q->where('estado', 'en_stock')])
            ->buscar($query)
            ->orderBy('nombre')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(fn (Producto $producto) => [
                'titulo' => $producto->nombre,
                'detalle' => implode(' · ', array_filter([
                    $producto->marca?->nombre,
                    $producto->categoria?->nombre,
                ])),
                'nota' => $producto->en_stock_count.' en stock',
                'url' => $puedeVerUnidades ? route('search.producto', $producto) : null,
                'accion' => 'Ver sus aparatos',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function unidades(string $query, bool $puedeVerVentas): array
    {
        return Unidad::query()
            ->with(['producto', 'ventaDetalle.venta'])
            ->buscar($query)
            ->orderByDesc('id')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(function (Unidad $unidad) use ($puedeVerVentas) {
                // Un aparato vendido se consulta por su venta; uno en stock,
                // por el listado de unidades de su producto.
                $venta = $unidad->ventaDetalle?->venta;
                $vendido = $venta !== null && $puedeVerVentas;

                return [
                    'titulo' => $unidad->serial ?: $unidad->codigo_interno,
                    'detalle' => implode(' · ', array_filter([
                        $unidad->producto?->nombre,
                        $unidad->serial ? $unidad->codigo_interno : null,
                    ])),
                    'nota' => Unidad::ESTADOS[$unidad->estado] ?? $unidad->estado,
                    'url' => $vendido
                        ? route('ventas.show', $venta)
                        : ($unidad->producto ? route('search.producto', $unidad->producto) : null),
                    'accion' => $vendido ? 'Ver la venta '.$venta->codigo : 'Ver en inventario',
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ventas(string $query): array
    {
        return Venta::query()
            ->with('cliente.persona')
            ->buscar($query)
            ->orderByDesc('vendida_en')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(fn (Venta $venta) => [
                'titulo' => $venta->codigo,
                'detalle' => implode(' · ', array_filter([
                    $venta->cliente?->persona?->nombre_completo,
                    $venta->vendida_en?->format('d/m/Y H:i'),
                ])),
                'nota' => 'Bs '.number_format((float) $venta->total, 2, ',', '.')
                    .($venta->estado === 'anulada' ? ' · Anulada' : ''),
                'url' => route('ventas.show', $venta),
                'accion' => 'Ver la venta',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function clientes(string $query): array
    {
        return Cliente::query()
            ->with('persona')
            ->buscar($query)
            ->orderBy('codigo')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(fn (Cliente $cliente) => [
                'titulo' => $cliente->persona?->nombre_completo ?? $cliente->codigo,
                'detalle' => implode(' · ', array_filter([
                    $cliente->codigo,
                    $cliente->persona?->carnet,
                    $cliente->persona?->celular,
                ])),
                'nota' => null,
                // El código es único: se llega al listado ya filtrado por él,
                // que muestra exactamente a esa persona.
                'url' => route('clientes.index', ['buscar' => $cliente->codigo]),
                'accion' => 'Ver en clientes',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function compras(string $query): array
    {
        return Compra::query()
            ->with('proveedor')
            ->buscar($query)
            ->orderByDesc('fecha_compra')
            ->orderByDesc('id')
            ->limit(self::POR_GRUPO)
            ->get()
            ->map(fn (Compra $compra) => [
                'titulo' => $compra->codigo,
                'detalle' => implode(' · ', array_filter([
                    $compra->proveedor?->nombre,
                    $compra->fecha_compra?->format('d/m/Y'),
                    $compra->numero_factura ? 'Fact. '.$compra->numero_factura : null,
                ])),
                'nota' => 'Bs '.number_format((float) $compra->total, 2, ',', '.')
                    .' · '.(Compra::ESTADOS[$compra->estado] ?? $compra->estado),
                'url' => route('compras.show', $compra),
                'accion' => 'Ver la compra',
            ])
            ->all();
    }
}
