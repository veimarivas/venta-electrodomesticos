<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntregaResource;
use App\Models\Entrega;
use App\Models\EntregaDetalle;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Support\ProgramacionDeEntregas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

/**
 * Entregas desde el teléfono.
 *
 * Esta es la mitad que faltaba: quien reparte lleva el móvil, no el panel. Por
 * eso aquí **sí** se puede escribir —despachar, confirmar, marcar un fallo—,
 * al revés que en las ventas, que la app solo consulta.
 *
 * Programar también se hace desde el móvil: en el mostrador el cliente acaba
 * de pagar y la dirección se acuerda igual de bien con el teléfono en la mano.
 */
class EntregaController extends Controller
{
    private function servicio(): ProgramacionDeEntregas
    {
        return app(ProgramacionDeEntregas::class);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'estado' => ['nullable', 'in:'.implode(',', array_keys(Entrega::ESTADOS))],
            'filtro' => ['nullable', 'in:abiertas,hoy,atrasadas'],
            // Quien va en el camión quiere ver **lo suyo** sin filtrar a mano.
            'mias' => ['nullable', 'boolean'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $entregas = Entrega::query()
            ->with([
                'venta',
                'cliente.persona',
                'repartidor',
                'detalles.ventaDetalle.producto',
                'detalles.ventaDetalle.unidad',
            ])
            ->buscar($datos['buscar'] ?? null)
            ->when(isset($datos['estado']), fn ($q) => $q->where('estado', $datos['estado']))
            ->when(($datos['filtro'] ?? null) === 'abiertas', fn ($q) => $q->abiertas())
            ->when(($datos['filtro'] ?? null) === 'hoy', fn ($q) => $q->deHoy())
            ->when(($datos['filtro'] ?? null) === 'atrasadas', fn ($q) => $q->atrasadas())
            ->when($datos['mias'] ?? false, fn ($q) => $q->where('repartidor_id', $request->user()->id))
            // Mismo orden que el tablero del panel: lo que tiene fecha manda y
            // lo más antiguo primero.
            ->orderByRaw('programada_para IS NULL')
            ->orderBy('programada_para')
            ->orderByDesc('id')
            ->paginate($datos['por_pagina'] ?? 20);

        return EntregaResource::collection($entregas);
    }

    public function show(Entrega $entrega): EntregaResource
    {
        return new EntregaResource($this->cargada($entrega));
    }

    /**
     * Quién puede llevar una entrega: usuarios activos con permiso de
     * gestionar entregas. Alimenta el selector al programar desde el móvil.
     */
    public function repartidores(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('entregas.ver'), 403);

        $repartidores = \App\Models\User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn ($u): bool => $u->can('entregas.gestionar'))
            ->map(fn ($u): array => ['id' => $u->id, 'nombre' => $u->name])
            ->values();

        return response()->json(['data' => $repartidores]);
    }

    /**
     * Aparatos de una venta que todavía se pueden repartir.
     *
     * Es lo que necesita la pantalla de programar del teléfono: qué queda por
     * llevar, qué ya está en una entrega viva y a quién llamar al llegar. La
     * venta se lee con `ventas.ver`; programar exige además `entregas.crear`,
     * igual que en el panel.
     */
    public function entregables(Request $request, Venta $venta): JsonResponse
    {
        $venta->loadMissing(['detalles.producto', 'detalles.unidad', 'cliente.persona']);

        $ids = $venta->detalles->pluck('id')->all();

        // La guardia del doble reparto: la línea está en una entrega viva si
        // `venta_detalle_activo_id` sigue puesto.
        $yaProgramadas = EntregaDetalle::query()
            ->whereNotNull('venta_detalle_activo_id')
            ->whereIn('venta_detalle_id', $ids)
            ->pluck('venta_detalle_id')
            ->all();

        $lineas = $venta->detalles->map(fn (VentaDetalle $detalle): array => [
            'venta_detalle_id' => $detalle->id,
            'producto' => $detalle->producto?->nombre,
            'codigo_interno' => $detalle->unidad?->codigo_interno,
            'serial' => $detalle->unidad?->serial,
            'devuelto' => $detalle->estaDevuelto(),
            'programado' => in_array($detalle->id, $yaProgramadas, true),
        ])->values()->all();

        return response()->json(['data' => [
            'venta_id' => $venta->id,
            'venta_codigo' => $venta->codigo,
            'esta_anulada' => $venta->esta_anulada,
            'cliente' => $venta->cliente?->persona?->nombre_completo ?? 'Público general',
            'telefono' => $venta->cliente?->persona?->celular,
            'lineas' => $lineas,
        ]]);
    }

    /**
     * Programa la entrega de unas líneas de una venta.
     *
     * La lógica es la del panel: `ProgramacionDeEntregas` valida que las líneas
     * sean de esta venta y que no estén ya repartidas.
     */
    public function programar(Request $request, Venta $venta): EntregaResource|JsonResponse
    {
        $datos = $request->validate([
            'venta_detalle_ids' => ['required', 'array', 'min:1'],
            'venta_detalle_ids.*' => ['integer'],
            'direccion' => ['required', 'string', 'max:255'],
            'referencia' => ['nullable', 'string', 'max:255'],
            'ubicacion_url' => ['nullable', 'string', 'max:500'],
            'telefono_contacto' => ['nullable', 'string', 'max:30'],
            'programada_para' => ['nullable', 'date', 'after_or_equal:today'],
            'con_instalacion' => ['nullable', 'boolean'],
            'repartidor_id' => ['nullable', 'integer', 'exists:users,id'],
            'notas' => ['nullable', 'string', 'max:500'],
        ], [
            'direccion.required' => 'Escribe la dirección de entrega.',
            'venta_detalle_ids.required' => 'Elige al menos un aparato para entregar.',
            'venta_detalle_ids.min' => 'Elige al menos un aparato para entregar.',
            'programada_para.after_or_equal' => 'La fecha de entrega no puede ser anterior a hoy.',
        ]);

        return $this->ejecutar(fn (): Entrega => $this->servicio()->programar(
            $venta,
            array_map('intval', $datos['venta_detalle_ids']),
            [
                'direccion' => $datos['direccion'],
                'referencia' => $datos['referencia'] ?? null,
                'ubicacion_url' => $datos['ubicacion_url'] ?? null,
                'telefono_contacto' => $datos['telefono_contacto'] ?? null,
                'programada_para' => $datos['programada_para'] ?? null,
                'con_instalacion' => (bool) ($datos['con_instalacion'] ?? false),
                'repartidor_id' => $datos['repartidor_id'] ?? null,
                'notas' => $datos['notas'] ?? null,
            ],
            $request->user()->id,
        ));
    }

    public function despachar(Request $request, Entrega $entrega): EntregaResource|JsonResponse
    {
        $datos = $request->validate([
            'repartidor_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // Sin repartidor en el cuerpo se asume quien llama: desde el móvil, el
        // que despacha es casi siempre el que se lo lleva.
        return $this->ejecutar(fn (): Entrega => $this->servicio()->despachar(
            $entrega,
            $datos['repartidor_id'] ?? $request->user()->id,
            $request->user()->id,
        ));
    }

    public function confirmar(Request $request, Entrega $entrega): EntregaResource|JsonResponse
    {
        $datos = $request->validate([
            'recibida_por' => ['required', 'string', 'max:120'],
            'instalada' => ['nullable', 'boolean'],
        ]);

        return $this->ejecutar(fn (): Entrega => $this->servicio()->confirmar(
            $entrega,
            $datos['recibida_por'],
            (bool) ($datos['instalada'] ?? false),
        ));
    }

    public function fallar(Request $request, Entrega $entrega): EntregaResource|JsonResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:1000'],
        ]);

        return $this->ejecutar(fn (): Entrega => $this->servicio()->fallar($entrega, $datos['motivo']));
    }

    public function reprogramar(Request $request, Entrega $entrega): EntregaResource|JsonResponse
    {
        $datos = $request->validate([
            'programada_para' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        return $this->ejecutar(fn (): Entrega => $this->servicio()->reprogramar(
            $entrega,
            $datos['programada_para'] ?? null,
        ));
    }

    /**
     * Corre la acción y traduce los errores de negocio a 422.
     *
     * `RuntimeException` es como el servicio distingue «no se puede hacer eso»
     * de un fallo técnico. Dejarla subir daría un 500 y la app diría que no
     * hay conexión cuando el problema es que la entrega ya se hizo.
     *
     * @param  callable(): Entrega  $accion
     */
    private function ejecutar(callable $accion): EntregaResource|JsonResponse
    {
        try {
            $entrega = $accion();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new EntregaResource($this->cargada($entrega));
    }

    private function cargada(Entrega $entrega): Entrega
    {
        return $entrega->load([
            'venta',
            'cliente.persona',
            'repartidor',
            'detalles.ventaDetalle.producto',
            'detalles.ventaDetalle.unidad',
        ]);
    }
}
