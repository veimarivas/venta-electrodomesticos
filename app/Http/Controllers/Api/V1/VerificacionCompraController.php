<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\User;
use App\Notifications\CompraAsignadaPush;
use App\Support\RecepcionDeCompra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Compras asignadas para verificar, desde el teléfono.
 *
 * El vendedor no tiene Compras: con `compras.verificar` ve **solo las que le
 * asignaron**, con productos y cantidades pero **sin costos ni pagos**, y
 * registra lo que llegó (por tandas, como en el panel). Quien administra
 * compras (`compras.crear`) puede usar las mismas rutas sobre cualquiera.
 *
 * La asignación la hace el administrador (`compras.editar`) y le llega un
 * aviso al asignado.
 */
class VerificacionCompraController extends Controller
{
    /** Las compras del usuario: primero las pendientes, luego las últimas verificadas. */
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $pendientes = Compra::query()
            ->asignadasA($userId)
            ->whereIn('estado', ['borrador', 'pendiente'])
            ->with('proveedor')
            ->withCount(['detalles', 'unidades'])
            ->withSum('detalles', 'cantidad')
            ->orderBy('asignada_en')
            ->get();

        $verificadas = Compra::query()
            ->asignadasA($userId)
            ->where('estado', 'recepcionada')
            ->with('proveedor')
            ->withCount(['detalles', 'unidades'])
            ->withSum('detalles', 'cantidad')
            ->latest('recepcionada_en')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'pendientes' => $pendientes->map(fn (Compra $c) => $this->resumen($c))->values(),
                'verificadas' => $verificadas->map(fn (Compra $c) => $this->resumen($c))->values(),
            ],
        ]);
    }

    /** La compra tal como la necesita quien cuenta cajas: sin costos. */
    public function show(Request $request, Compra $compra): JsonResponse
    {
        $this->puedeVerificar($request, $compra);

        return response()->json(['data' => $this->ficha($compra)]);
    }

    /**
     * Registra lo que llegó. Body como el de `/compras/{compra}/recepcionar`:
     *   lineas: [{ linea_id, seriales: [...] } | { linea_id, cantidad_verificada }]
     */
    public function recepcionar(Request $request, Compra $compra): JsonResponse
    {
        $this->puedeVerificar($request, $compra);

        if (! $compra->puede_recepcionarse) {
            return response()->json(['message' => 'Esta compra ya se recepcionó o se anuló.'], 422);
        }

        $datos = $request->validate([
            'lineas' => ['required', 'array', 'min:1', 'max:50'],
            'lineas.*.linea_id' => ['required', 'integer'],
            'lineas.*.seriales' => ['nullable', 'array'],
            'lineas.*.seriales.*' => ['nullable', 'string', 'max:100'],
            'lineas.*.cantidad_verificada' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $ids = $compra->detalles()->pluck('id')->all();
        $verificacion = [];

        foreach ($datos['lineas'] as $linea) {
            if (! in_array((int) $linea['linea_id'], $ids, true)) {
                return response()->json(['message' => 'Una de las líneas no pertenece a esta compra.'], 422);
            }

            $verificacion[(int) $linea['linea_id']] = array_key_exists('cantidad_verificada', $linea)
                ? ['cantidad_verificada' => (int) $linea['cantidad_verificada']]
                : ['seriales' => array_values(array_filter($linea['seriales'] ?? [], fn ($s) => $s !== null))];
        }

        try {
            $generadas = app(RecepcionDeCompra::class)->recepcionar($compra->fresh(), $verificacion);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $compra->refresh();

        return response()->json([
            'message' => $compra->esta_recepcionada
                ? "Listo: {$generadas} aparatos entraron al stock y la compra quedó recepcionada."
                : "{$generadas} aparatos entraron al stock.",
            'data' => $this->ficha($compra),
        ]);
    }

    /** Quiénes pueden recibir la asignación (`compras.editar`). */
    public function verificadores(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('compras.editar') ?? false, 403);

        return response()->json([
            'data' => User::permission('compras.verificar')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'nombre' => $u->name])
                ->values(),
        ]);
    }

    /** Asigna (o quita, con `verificador_id` nulo) la verificación (`compras.editar`). */
    public function asignar(Request $request, Compra $compra): JsonResponse
    {
        abort_unless($request->user()?->can('compras.editar') ?? false, 403);

        $datos = $request->validate([
            'verificador_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ]);

        if (! $compra->puede_recepcionarse) {
            return response()->json(['message' => 'Esta compra ya no tiene nada que verificar.'], 422);
        }

        $usuario = isset($datos['verificador_id']) ? User::find($datos['verificador_id']) : null;

        if ($usuario !== null && ! $usuario->can('compras.verificar')) {
            return response()->json([
                'message' => 'Esa cuenta no tiene el permiso de verificar compras.',
                'errors' => ['verificador_id' => ['Elige a alguien que pueda verificar compras.']],
            ], 422);
        }

        $compra->update([
            'verificador_id' => $usuario?->id,
            'asignada_en' => $usuario ? now() : null,
        ]);

        if ($usuario !== null) {
            rescue(fn () => $usuario->notify(new CompraAsignadaPush($compra->load('proveedor'))), report: false);
        }

        return response()->json([
            'message' => $usuario ? "{$usuario->name} ya puede verificar esta compra." : 'Se quitó la asignación.',
            'data' => [
                'verificador_id' => $compra->verificador_id,
                'verificador' => $usuario?->name,
                'asignada_en' => $compra->asignada_en?->toIso8601String(),
            ],
        ]);
    }

    private function puedeVerificar(Request $request, Compra $compra): void
    {
        $usuario = $request->user();

        abort_unless($compra->esVerificadaPor($usuario) || ($usuario?->can('compras.crear') ?? false), 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(Compra $compra): array
    {
        $pedidas = (int) ($compra->detalles_sum_cantidad ?? 0);

        return [
            'id' => $compra->id,
            'codigo' => $compra->codigo,
            'proveedor' => $compra->proveedor?->nombre,
            'fecha_compra' => $compra->fecha_compra?->toDateString(),
            'estado' => $compra->estado,
            'productos' => (int) $compra->detalles_count,
            'pedidas' => $pedidas,
            'recibidas' => (int) $compra->unidades_count,
            'faltan' => max($pedidas - (int) $compra->unidades_count, 0),
            'asignada_en' => $compra->asignada_en?->toIso8601String(),
            'recepcionada_en' => $compra->recepcionada_en?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ficha(Compra $compra): array
    {
        $compra->loadMissing(['proveedor', 'verificador']);

        $lineas = $compra->detalles()->with('producto')->withCount('unidades')->orderBy('id')->get();

        return [
            'id' => $compra->id,
            'codigo' => $compra->codigo,
            'proveedor' => $compra->proveedor?->nombre,
            'numero_factura' => $compra->numero_factura,
            'fecha_compra' => $compra->fecha_compra?->toDateString(),
            'estado' => $compra->estado,
            'estado_texto' => Compra::ESTADOS[$compra->estado] ?? $compra->estado,
            'puede_recepcionarse' => $compra->puede_recepcionarse,
            'verificador' => $compra->verificador?->name,
            'notas' => $compra->notas,
            // Productos y cantidades. Ni costos ni precios: para contar cajas
            // no hacen falta.
            'lineas' => $lineas->map(fn (CompraDetalle $l): array => [
                'id' => $l->id,
                'producto' => $l->producto?->nombre,
                'producto_id' => $l->producto_id,
                'tiene_serial' => (bool) ($l->producto?->tiene_serial ?? true),
                'cantidad' => (int) $l->cantidad,
                'recibidas' => (int) $l->unidades_count,
                'faltan' => max((int) $l->cantidad - (int) $l->unidades_count, 0),
            ])->values(),
        ];
    }
}
