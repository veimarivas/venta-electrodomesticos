<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReparacionResource;
use App\Models\Reparacion;
use App\Models\Unidad;
use App\Support\ComprobantesDeCliente;
use App\Support\ServicioTecnico;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

/**
 * Reparaciones (servicio técnico) desde el teléfono.
 *
 * Consulta y recepción: el mostrador recibe aparatos y consulta el estado del
 * taller. Las acciones de taller (diagnosticar, esperar repuesto, marcar lista)
 * también están disponibles para quien tiene el permiso `reparaciones.atender`.
 */
class ReparacionController extends Controller
{
    private function servicio(): ServicioTecnico
    {
        return app(ServicioTecnico::class);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
            'filtro' => ['nullable', 'in:abiertas,atrasadas,en_taller,listas,cerradas,todas'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filtro = $datos['filtro'] ?? 'abiertas';

        $reparaciones = Reparacion::query()
            ->with(['unidad.producto', 'cliente.persona', 'tecnico', 'venta'])
            ->buscar($datos['buscar'] ?? null)
            ->when($filtro === 'abiertas', fn ($q) => $q->abiertas())
            ->when($filtro === 'atrasadas', fn ($q) => $q->atrasadas())
            ->when($filtro === 'en_taller', fn ($q) => $q->whereIn(
                'estado',
                ['recibida', 'en_reparacion', 'esperando_repuesto']
            ))
            ->when($filtro === 'listas', fn ($q) => $q->where('estado', 'lista'))
            ->when($filtro === 'cerradas', fn ($q) => $q->whereIn(
                'estado',
                Reparacion::ESTADOS_CERRADOS
            ))
            ->orderByRaw('prometida_para IS NULL')
            ->orderBy('prometida_para')
            ->orderByDesc('id')
            ->paginate($datos['por_pagina'] ?? 20);

        return ReparacionResource::collection($reparaciones);
    }

    public function show(Request $request, Reparacion $reparacion): ReparacionResource
    {
        $reparacion->load([
            'unidad.producto',
            'cliente.persona',
            'tecnico',
            'recibidaPor',
            'venta',
        ]);

        return (new ReparacionResource($reparacion))->conDetalle();
    }

    /**
     * Busca unidades por serial o código interno para la recepción.
     *
     * El taller trabaja sobre un aparato concreto, no sobre un modelo: se
     * busca por serial o código, no por nombre de producto.
     */
    public function buscarUnidad(Request $request): JsonResponse
    {
        $termino = trim((string) $request->query('termino', ''));

        if (mb_strlen($termino) < 2) {
            return response()->json(['data' => []]);
        }

        $unidades = Unidad::query()
            ->with('producto')
            ->where(fn ($q) => $q->where('serial', 'like', "%{$termino}%")
                ->orWhere('codigo_interno', 'like', "%{$termino}%"))
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return response()->json([
            'data' => $unidades->map(fn (Unidad $u) => [
                'id' => $u->id,
                'codigo_interno' => $u->codigo_interno,
                'serial' => $u->serial,
                'producto' => $u->producto?->nombre,
                'estado' => $u->estado,
                'estado_texto' => $u->estado_texto,
                'en_garantia' => $u->en_garantia,
                'garantia_hasta' => $u->garantia_hasta?->toDateString(),
            ]),
        ]);
    }

    /**
     * Recibe un aparato en el taller y abre una orden.
     *
     * La cobertura de garantía se congela al recibirla: si mañana alguien
     * cambia los meses de garantía del producto, esta orden no puede volverse
     * cobrable sola.
     */
    public function recibir(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('reparaciones.recibir') ?? false, 403);

        $datos = $request->validate([
            'unidad_id' => ['required', 'integer', 'exists:unidades,id'],
            'falla_reportada' => ['required', 'string', 'min:4', 'max:1000'],
            'prometida_para' => ['nullable', 'date', 'after_or_equal:today'],
            'costo' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $unidad = Unidad::with('producto')->find($datos['unidad_id']);

        try {
            $reparacion = $this->servicio()->recibir(
                $unidad,
                [
                    'falla_reportada' => $datos['falla_reportada'],
                    'prometida_para' => $datos['prometida_para'] ?? null,
                    'costo' => $datos['costo'] ?? 0,
                    'notas' => $datos['notas'] ?? null,
                ],
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Orden {$reparacion->codigo} abierta"
                .($reparacion->en_garantia ? ' — el aparato está en garantía.' : '.'),
            'data' => (new ReparacionResource($reparacion->fresh()->load([
                'unidad.producto', 'cliente.persona', 'tecnico',
            ])))->conDetalle()->resolve($request),
        ]);
    }

    /**
     * Diagnostica una orden: el técnico anota qué encontró.
     */
    public function diagnosticar(Request $request, Reparacion $reparacion): JsonResponse
    {
        abort_unless($request->user()?->can('reparaciones.atender') ?? false, 403);

        $datos = $request->validate([
            'diagnostico' => ['required', 'string', 'min:4', 'max:1000'],
            'costo' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        try {
            $this->servicio()->diagnosticar(
                $reparacion,
                $datos['diagnostico'],
                $request->user()->id,
                $datos['costo'] ?? null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Diagnóstico anotado.',
            'data' => (new ReparacionResource($reparacion->fresh()->load([
                'unidad.producto', 'tecnico',
            ])))->conDetalle()->resolve($request),
        ]);
    }

    /**
     * Marca una orden como lista para entregar.
     */
    public function marcarLista(Request $request, Reparacion $reparacion): JsonResponse
    {
        abort_unless($request->user()?->can('reparaciones.atender') ?? false, 403);

        $datos = $request->validate([
            'trabajo_realizado' => ['required', 'string', 'min:4', 'max:1000'],
        ]);

        try {
            $this->servicio()->marcarLista($reparacion, $datos['trabajo_realizado']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Lista para que la recojan.',
            'data' => (new ReparacionResource($reparacion->fresh()->load([
                'unidad.producto',
            ])))->conDetalle()->resolve($request),
        ]);
    }

    /**
     * Entrega el aparato al cliente.
     */
    public function entregar(Request $request, Reparacion $reparacion): JsonResponse
    {
        abort_unless(
            $request->user()?->can('reparaciones.recibir')
                || $request->user()?->can('reparaciones.atender'),
            false,
            403,
        );

        $datos = $request->validate([
            'entregada_a' => ['required', 'string', 'max:120'],
        ]);

        try {
            $this->servicio()->entregar($reparacion, $datos['entregada_a']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Aparato entregado al cliente.',
            'data' => (new ReparacionResource($reparacion->fresh()->load([
                'unidad.producto',
            ])))->conDetalle()->resolve($request),
        ]);
    }

    /**
     * Orden de servicio técnico en PDF, para dársela al cliente.
     *
     * Inline para que el visor del teléfono la abra directamente.
     */
    public function comprobante(Reparacion $reparacion): Response
    {
        $contenido = ComprobantesDeCliente::ordenDeReparacion($reparacion);

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Orden-'.$reparacion->codigo.'.pdf"',
            'Content-Length' => (string) strlen($contenido),
        ]);
    }
}
