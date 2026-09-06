<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompraResource;
use App\Http\Resources\PagoCompraResource;
use App\Http\Resources\UnidadResource;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\PagoCompra;
use App\Models\Producto;
use App\Support\GeneradorCodigoCompra;
use App\Support\ProrrateoDeGastos;
use App\Support\RecepcionDeCompra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            ->withSum('pagos as total_pagado', 'monto')
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
            'pagos.user',
        ]);

        $compra->loadCount(['detalles', 'unidades'])->loadSum('pagos as total_pagado', 'monto');

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
     * Registra una compra nueva en estado `pendiente`.
     *
     * El detalle por producto tiene que cuadrar EXACTAMENTE con el total: si la
     * suma de las líneas no coincide, queda un costo que nadie carga y el
     * inventario deja de valer lo que realmente costó. La compra nace sin
     * unidades: se generan al recepcionar, cuando se verifica la mercadería.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('compras.crear') ?? false, 403);

        $datos = $request->validate([
            'proveedor_id' => ['required', 'integer', Rule::exists('proveedores', 'id')->whereNull('deleted_at')],
            'numero_factura' => ['nullable', 'string', 'max:60'],
            'fecha_compra' => ['required', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'total' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'lineas' => ['required', 'array', 'min:1', 'max:50'],
            'lineas.*.producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->whereNull('deleted_at')],
            'lineas.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'lineas.*.costo_total' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ]);

        // Cuadre al centavo: mismas reglas que el panel.
        $totalCentavos = ProrrateoDeGastos::aCentavos($datos['total']);
        $asignadoCentavos = array_sum(array_map(
            fn (array $linea): int => ProrrateoDeGastos::aCentavos($linea['costo_total']),
            $datos['lineas']
        ));

        if ($asignadoCentavos !== $totalCentavos) {
            return response()->json([
                'message' => 'El detalle por producto debe sumar exactamente el total de la compra.',
                'errors' => ['lineas' => ['La suma de los productos no cuadra con el total.']],
            ], 422);
        }

        // Un producto no puede repetirse en dos líneas: el prorrateo y el
        // conteo de unidades se vuelven ambiguos.
        $productoIds = array_column($datos['lineas'], 'producto_id');

        if (count($productoIds) !== count(array_unique($productoIds))) {
            return response()->json([
                'message' => 'Un producto no puede repetirse en dos líneas de la misma compra.',
                'errors' => ['lineas' => ['Productos repetidos.']],
            ], 422);
        }

        $productos = Producto::whereIn('id', $productoIds)->get()->keyBy('id');

        $compra = DB::transaction(function () use ($request, $datos, $productos, $totalCentavos): Compra {
            $compra = app(GeneradorCodigoCompra::class)->crearCon([
                'proveedor_id' => (int) $datos['proveedor_id'],
                'numero_factura' => trim($datos['numero_factura'] ?? '') !== ''
                    ? trim($datos['numero_factura'])
                    : null,
                'fecha_compra' => $datos['fecha_compra'],
                'notas' => trim($datos['notas'] ?? '') !== '' ? trim($datos['notas']) : null,
                'user_id' => $request->user()->id,
                'subtotal' => ProrrateoDeGastos::aDecimal($totalCentavos),
                'total' => ProrrateoDeGastos::aDecimal($totalCentavos),
                'descuento' => '0.00',
                'impuesto' => '0.00',
                'flete' => '0.00',
                'otros_gastos' => '0.00',
                'estado' => 'pendiente',
            ]);

            foreach ($datos['lineas'] as $linea) {
                $producto = $productos[$linea['producto_id']];
                $cantidad = (int) $linea['cantidad'];
                $pagado = ProrrateoDeGastos::aCentavos($linea['costo_total']);

                CompraDetalle::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $producto->id,
                    'cantidad' => $cantidad,
                    // Promedio, solo de referencia: el reparto exacto al
                    // centavo lo hace RecepcionDeCompra sobre cada unidad.
                    'costo_unitario' => ProrrateoDeGastos::aDecimal(intdiv($pagado, $cantidad)),
                    'subtotal' => ProrrateoDeGastos::aDecimal($pagado),
                    'precio_venta' => $producto->precio_venta,
                ]);
            }

            return $compra->fresh();
        });

        $compra->load([
            'proveedor',
            'user',
            'detalles' => fn ($d) => $d->with('producto')->withCount('unidades')->orderBy('id'),
            'pagos.user',
        ]);
        $compra->loadCount(['detalles', 'unidades'])->loadSum('pagos as total_pagado', 'monto');

        return (new CompraResource($compra))
            ->conDetalle()
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Quita una compra que todavía no se recepcionó.
     *
     * Una compra recepcionada no se borra: sus unidades ya están en el almacén
     * o vendidas, y quedarían sin origen.
     */
    public function destroy(Request $request, Compra $compra): JsonResponse
    {
        abort_unless($request->user()?->can('compras.eliminar') ?? false, 403);

        if (! $compra->puede_recepcionarse) {
            return response()->json([
                'message' => 'Una compra recepcionada o anulada no se puede eliminar.',
            ], 422);
        }

        $compra->delete();

        return response()->json(['mensaje' => 'Compra eliminada.']);
    }

    /**
     * Recepciona una compra: verifica cada línea y genera las unidades.
     *
     * La compra debe estar en estado `pendiente` (o un `borrador` viejo) y
     * tener al menos una línea. La recepción es atómica: o se verifica TODO —
     * seriales de los productos que los llevan y confirmación de los demás— y
     * se genera el lote entero, o no se crea nada.
     *
     * Body:
     *   lineas: [
     *     { linea_id: 1, seriales: ["S1", "S2"] },   // producto con serial
     *     { linea_id: 2, verificada: true },          // sin serial
     *   ]
     */
    public function recepcionar(Request $request, Compra $compra): JsonResponse
    {
        abort_unless($request->user()?->can('compras.crear') ?? false, 403);

        if (! $compra->puede_recepcionarse) {
            return response()->json([
                'message' => 'Solo se puede recepcionar una compra pendiente.',
            ], 422);
        }

        $datos = $request->validate([
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.linea_id' => ['required', 'integer', Rule::exists('compra_detalles', 'id')],
            'lineas.*.seriales' => ['nullable', 'array'],
            'lineas.*.seriales.*' => ['string', 'max:100'],
            'lineas.*.verificada' => ['nullable', 'boolean'],
        ]);

        // Solo líneas de ESTA compra: el componente es invocable y no debe
        // poder tocar líneas de otra.
        $idsDeLaCompra = $compra->detalles()->pluck('id')->all();

        $verificacion = [];

        foreach ($datos['lineas'] as $linea) {
            if (! in_array($linea['linea_id'], $idsDeLaCompra, true)) {
                return response()->json([
                    'message' => 'Una de las líneas no pertenece a esta compra.',
                ], 422);
            }

            $verificacion[$linea['linea_id']] = $linea['verificada'] ?? false
                ? ['verificada' => true]
                : ['seriales' => $linea['seriales'] ?? []];
        }

        try {
            $generadas = app(RecepcionDeCompra::class)->recepcionar($compra->fresh(), $verificacion);

            $compra->refresh()->load([
                'proveedor',
                'user',
                'detalles' => fn ($d) => $d->with('producto')->withCount('unidades'),
                'pagos.user',
            ]);
            $compra->loadCount(['detalles', 'unidades'])->loadSum('pagos as total_pagado', 'monto');

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

    // ---- Pagos al proveedor ----------------------------------------------

    /**
     * Respaldo de pago: cada compra se paga en varios plazos y cada pago lleva
     * su boucher. Se listan ordenados del más reciente al más antiguo.
     */
    public function pagos(Request $request, Compra $compra): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('compras.ver') ?? false, 403);

        $pagos = $compra->pagos()
            ->with('user')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return PagoCompraResource::collection($pagos);
    }

    /**
     * Registra un pago al proveedor con su boucher. El monto puede ser parcial:
     * varios pagos completan el total de la compra.
     */
    public function guardarPago(Request $request, Compra $compra): JsonResponse
    {
        abort_unless($request->user()?->can('compras.crear') ?? false, 403);

        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'fecha' => ['required', 'date'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        $pago = $compra->pagos()->create([
            'user_id' => $request->user()->id,
            'monto' => $datos['monto'],
            'fecha' => $datos['fecha'],
            'imagen' => $request->hasFile('imagen')
                ? $request->file('imagen')->store('comprobantes-compra', 'public')
                : null,
            'notas' => trim($datos['notas'] ?? '') !== '' ? trim($datos['notas']) : null,
        ]);

        return (new PagoCompraResource($pago->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Quita un pago mal registrado. Borra también su boucher: es un respaldo
     * de ese pago concreto y no debe quedar huérfano.
     */
    public function borrarPago(Request $request, Compra $compra, PagoCompra $pago): JsonResponse
    {
        abort_unless($request->user()?->can('compras.crear') ?? false, 403);

        if ($pago->compra_id !== $compra->id) {
            return response()->json(['message' => 'El pago no pertenece a esta compra.'], 422);
        }

        if ($pago->imagen) {
            Storage::disk('public')->delete($pago->imagen);
        }

        $pago->delete();

        return response()->json(['message' => 'Pago eliminado.']);
    }
}
