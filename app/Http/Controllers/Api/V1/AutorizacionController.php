<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
    /** El vendedor pide autorización para vender por debajo del mínimo. */
    public function solicitar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'unidad_id' => ['required', 'integer', Rule::exists('unidades', 'id')->whereNull('deleted_at')],
            'precio' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ]);

        $unidad = Unidad::with('producto')->find($datos['unidad_id']);

        if ($unidad === null) {
            return response()->json(['message' => 'Ese aparato ya no existe.'], 404);
        }

        try {
            $solicitud = app(AutorizacionDeDescuento::class)->solicitar(
                $unidad,
                $datos['precio'],
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->representar($solicitud)], 201);
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
