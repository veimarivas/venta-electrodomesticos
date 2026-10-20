<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\SolicitudDescuento;
use App\Models\Unidad;
use App\Support\AutorizacionDeDescuento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Autorizaciones de descuento desde el teléfono.
 *
 * El vendedor pide bajar del mínimo y el administrador resuelve; el mismo
 * servicio que usa el panel (`AutorizacionDeDescuento`) hace el trabajo, así
 * que las reglas no se duplican ni se separan entre web y app.
 */
class AutorizacionController extends Controller
{
    /**
     * El vendedor pide autorización para vender por debajo del mínimo.
     *
     * Dos formas: por aparato (`unidad_id`, la de siempre) o por producto
     * (`producto_id` + `cantidad`), que es la de la venta por cantidad: un solo
     * aviso al administrador por la línea entera, aunque los aparatos con
     * serial se escaneen recién al confirmar.
     */
    public function solicitar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'unidad_id' => ['required_without:producto_id', 'nullable', 'integer', Rule::exists('unidades', 'id')->whereNull('deleted_at')],
            'producto_id' => ['required_without:unidad_id', 'nullable', 'integer', Rule::exists('productos', 'id')->whereNull('deleted_at')],
            'cantidad' => ['nullable', 'integer', 'min:1', 'max:200'],
            'unidad_ids' => ['nullable', 'array', 'max:200'],
            'unidad_ids.*' => ['integer'],
            'precio' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ]);

        $servicio = app(AutorizacionDeDescuento::class);

        try {
            if (! empty($datos['producto_id'])) {
                $solicitud = $servicio->solicitarPorProducto(
                    Producto::findOrFail($datos['producto_id']),
                    (string) $datos['precio'],
                    (int) ($datos['cantidad'] ?? 1),
                    $request->user()->id,
                    $this->unidadesPropias($datos['unidad_ids'] ?? [], (int) $datos['producto_id'], (int) $request->user()->id),
                );
            } else {
                $unidad = Unidad::with('producto')->find($datos['unidad_id']);

                if ($unidad === null) {
                    return response()->json(['message' => 'Ese aparato ya no existe.'], 404);
                }

                $solicitud = $servicio->solicitar($unidad, (string) $datos['precio'], $request->user()->id);
            }
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->representar($solicitud)], 201);
    }

    /**
     * Precios que la app ofrece al vendedor para pedir: tres por debajo del
     * mínimo, todos por encima del costo. El costo no viaja.
     */
    public function sugerencias(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')->whereNull('deleted_at')],
            'unidad_ids' => ['nullable', 'array', 'max:200'],
            'unidad_ids.*' => ['integer'],
        ]);

        return response()->json([
            'data' => app(AutorizacionDeDescuento::class)->sugerencias(
                Producto::findOrFail($datos['producto_id']),
                $this->unidadesPropias($datos['unidad_ids'] ?? [], (int) $datos['producto_id'], (int) $request->user()->id),
            ),
        ]);
    }

    /** El vendedor deja de esperar: retira su solicitud pendiente. */
    public function cancelar(Request $request, SolicitudDescuento $solicitud): JsonResponse
    {
        try {
            app(AutorizacionDeDescuento::class)->cancelar($solicitud, (int) $request->user()->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['data' => $this->representar($solicitud->refresh())]);
    }

    /**
     * De las unidades que manda la app, solo las del producto que el propio
     * vendedor tiene apartadas: con ellas se toma el costo piso, y un id ajeno
     * no debe poder bajarlo.
     *
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function unidadesPropias(array $ids, int $productoId, int $userId): array
    {
        if ($ids === []) {
            return [];
        }

        return Unidad::query()
            ->whereIn('id', $ids)
            ->where('producto_id', $productoId)
            ->where(fn ($q) => $q->where('estado', 'en_stock')
                ->orWhere(fn ($r) => $r->where('estado', 'reservado')->where('reservado_por', $userId)))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** Estado de una solicitud, para que la app la sondee mientras espera. */
    public function estado(Request $request, SolicitudDescuento $solicitud): JsonResponse
    {
        abort_unless(
            (int) $solicitud->user_id === (int) $request->user()->id
                || $request->user()->can('ventas.autorizar_descuento'),
            403
        );

        return response()->json(['data' => $this->representar($solicitud)]);
    }

    /** Bandeja del administrador: lo que está esperando respuesta. */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('ventas.autorizar_descuento'), 403);

        $solicitudes = SolicitudDescuento::query()
            ->pendientes()
            ->with(['producto', 'unidad', 'solicitante'])
            ->orderBy('created_at')
            ->get();

        return response()->json(['data' => $solicitudes->map(fn (SolicitudDescuento $s): array => $this->representar($s))]);
    }

    /** Aprobar (con el precio pedido o uno sugerido) o rechazar. */
    public function resolver(Request $request, SolicitudDescuento $solicitud): JsonResponse
    {
        abort_unless($request->user()->can('ventas.autorizar_descuento'), 403);

        $datos = $request->validate([
            'aprobar' => ['required', 'boolean'],
            'precio' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $solicitud = app(AutorizacionDeDescuento::class)->resolver(
                $solicitud,
                (bool) $datos['aprobar'],
                $datos['precio'] ?? null,
                $datos['motivo'] ?? null,
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->representar($solicitud)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function representar(SolicitudDescuento $solicitud): array
    {
        $solicitud->loadMissing(['producto', 'unidad', 'solicitante', 'revisor']);

        $lista = (float) $solicitud->precio_lista;
        $tope = (float) $solicitud->descuento_maximo;

        return [
            'id' => $solicitud->id,
            'estado' => $solicitud->estado,
            'unidad_id' => $solicitud->unidad_id,
            'codigo_interno' => $solicitud->unidad?->codigo_interno,
            'serial' => $solicitud->unidad?->serial,
            'producto' => $solicitud->producto?->nombre,
            'producto_id' => $solicitud->producto_id,
            // Por producto: cuántas unidades cubre y cuántas ya se vendieron.
            'por_producto' => $solicitud->esPorProducto(),
            'cantidad' => (int) $solicitud->cantidad,
            'usadas' => (int) $solicitud->usadas,
            'precio_lista' => $lista,
            'descuento_maximo' => $tope,
            'precio_minimo' => round(max($lista - $tope, 0), 2),
            'costo_unitario' => (float) $solicitud->costo_unitario,
            'precio_solicitado' => (float) $solicitud->precio_solicitado,
            'precio_aprobado' => $solicitud->precio_aprobado !== null ? (float) $solicitud->precio_aprobado : null,
            'motivo' => $solicitud->motivo,
            'vendedor' => $solicitud->solicitante?->name,
            'resuelto_por' => $solicitud->revisor?->name,
            'creada_en' => $solicitud->created_at?->toIso8601String(),
        ];
    }
}
