<?php

namespace App\Http\Resources;

use App\Support\ProrrateoDeGastos;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un crédito para la app: quién debe, cuánto y qué vence.
 *
 * @mixin \App\Models\Credito
 */
class CreditoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $proxima = $this->relationLoaded('cuotas') ? $this->proximaCuota() : null;

        return [
            'id' => $this->id,
            'estado' => $this->estado,
            'estado_texto' => \App\Models\Credito::ESTADOS[$this->estado] ?? $this->estado,
            // Calculada aquí y no en el móvil: la mora depende de la fecha de
            // hoy, y decidir quién está vencido no es cosa del cliente.
            'esta_en_mora' => $this->relationLoaded('cuotas') ? $this->esta_en_mora : null,

            'cliente' => $this->cliente?->persona?->nombre_completo ?? 'Sin nombre',
            'cliente_codigo' => $this->cliente?->codigo,
            'telefono' => $this->cliente?->persona?->celular,
            'venta_id' => $this->venta_id,
            'venta_codigo' => $this->venta?->codigo,

            'cuota_inicial' => (float) $this->cuota_inicial,
            'total_financiado' => (float) $this->total_financiado,
            'numero_cuotas' => $this->numero_cuotas,
            'primer_vencimiento' => $this->primer_vencimiento?->toDateString(),
            'saldo' => $this->saldoParaLaApp(),

            'proxima_cuota' => $proxima === null ? null : [
                'id' => $proxima->id,
                'numero' => $proxima->numero,
                'vence_en' => $proxima->vence_en?->toDateString(),
                'falta' => (float) $proxima->falta,
                'esta_vencida' => $proxima->esta_vencida,
            ],

            'notas' => $this->notas,

            'cuotas' => CuotaResource::collection($this->whenLoaded('cuotas')),
            'pagos' => PagoCreditoResource::collection($this->whenLoaded('pagos')),
        ];
    }

    /**
     * El saldo, venga de donde venga.
     *
     * En el listado llega sumado en SQL (`withSum`), porque cargar las cuotas
     * de toda la cartera para restar dos columnas no cabe en memoria. En la
     * ficha se calcula sobre las cuotas ya cargadas. Las dos vías dan lo
     * mismo; lo que cambia es lo que cuesta.
     */
    private function saldoParaLaApp(): float
    {
        // Se mira en los atributos crudos y no con `$this->comprometido`: con
        // `Model::shouldBeStrict()`, leer una columna que la consulta no trajo
        // lanza excepción en vez de devolver null.
        $atributos = $this->resource->getAttributes();

        if (isset($atributos['comprometido'], $atributos['cobrado'])) {
            return round((float) $atributos['comprometido'] - (float) $atributos['cobrado'], 2);
        }

        if ($this->relationLoaded('cuotas')) {
            return (float) ProrrateoDeGastos::aDecimal($this->saldoEnCentavos());
        }

        return 0.0;
    }
}
