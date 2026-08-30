<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CreditoResource;
use App\Models\Credito;
use App\Models\Cuota;
use App\Models\PagoCredito;
use App\Support\CobroDeCuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

/**
 * La cartera desde el teléfono.
 *
 * Escribe, como las entregas y el POS, y por la misma razón: cobrar una cuota
 * pasa en el mostrador o en la puerta del cliente, no delante del panel.
 *
 * Lo que no está aquí es **abrir** un crédito: eso ocurre al cobrar la venta,
 * y la venta a plazos se arma en el punto de venta con el plan entero delante.
 */
class CreditoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $datos = $request->validate([
            'filtro' => ['nullable', 'in:vigentes,mora,proximos,pagados,todos'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'por_pagina' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filtro = $datos['filtro'] ?? 'vigentes';

        $creditos = Credito::query()
            ->with(['cliente.persona', 'venta', 'cuotas'])
            // El saldo se suma en SQL: cargar las cuotas de toda la cartera
            // para restar dos columnas no cabe en memoria.
            ->withSum('cuotas as comprometido', 'monto')
            ->withSum('cuotas as cobrado', 'monto_pagado')
            ->buscar($datos['buscar'] ?? null)
            ->when($filtro === 'vigentes', fn ($q) => $q->vigentes())
            ->when($filtro === 'mora', fn ($q) => $q->enMora())
            ->when($filtro === 'proximos', fn ($q) => $q->porVencer())
            ->when($filtro === 'pagados', fn ($q) => $q->where('estado', 'pagado'))
            // Mismo orden que el panel: primero lo vivo y, dentro, el
            // vencimiento más antiguo sin saldar.
            ->orderByRaw("FIELD(estado, 'vigente', 'pagado', 'anulado')")
            ->orderBy(
                Cuota::query()->selectRaw('MIN(vence_en)')
                    ->whereColumn('cuotas.credito_id', 'creditos.id')
                    ->pendientes()
            )
            ->orderByDesc('id')
            ->paginate($datos['por_pagina'] ?? 20);

        return CreditoResource::collection($creditos);
    }

    public function show(Credito $credito): CreditoResource
    {
        return new CreditoResource($this->cargado($credito));
    }

    /**
     * Recibe dinero contra el crédito.
     *
     * No se elige la cuota: el servicio imputa de la más antigua a la más
     * nueva. Dejar elegir desde el móvil permitiría saldar la de diciembre
     * dejando viva la de agosto, y la mora dejaría de significar nada.
     */
    public function cobrar(Request $request, Credito $credito): CreditoResource|JsonResponse
    {
        $datos = $request->validate([
            'monto' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'metodo_pago' => ['required', 'in:'.implode(',', array_keys(PagoCredito::METODOS_PAGO))],
            'comprobante_qr' => ['nullable', 'string', 'max:255'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(CobroDeCuota::class)->cobrar(
                $credito,
                $datos['monto'],
                [
                    'metodo_pago' => $datos['metodo_pago'],
                    'comprobante_qr' => $datos['comprobante_qr'] ?? null,
                    'notas' => $datos['notas'] ?? null,
                ],
                $request->user()->id,
            );
        } catch (RuntimeException $e) {
            // 422 y no 500: el mensaje del servicio ya está en español —«el
            // pago supera el saldo»— y es lo que hay que enseñarle al cajero.
            // Un 500 la app lo pintaría como «no hay conexión».
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Se devuelve el crédito entero y no el pago: tras cobrar, lo que la
        // pantalla necesita repintar es el saldo y el estado de las cuotas.
        return new CreditoResource($this->cargado($credito->refresh()));
    }

    private function cargado(Credito $credito): Credito
    {
        return $credito->load([
            'cliente.persona',
            'venta',
            'cuotas',
            'pagos.cuota',
            'pagos.user',
        ]);
    }
}
