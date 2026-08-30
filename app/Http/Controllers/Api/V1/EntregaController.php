<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EntregaResource;
use App\Models\Entrega;
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
 * Lo que no se puede desde el móvil es **programar** una entrega: hace falta
 * elegir aparatos de una venta y teclear una dirección, y eso se hace en el
 * mostrador con el cliente delante.
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
