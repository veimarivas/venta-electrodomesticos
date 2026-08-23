<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Venta para el listado de la app.
 *
 * Los importes van como float, no como cadena decimal: la app los necesita
 * para graficar, y formatearlos es cosa de la interfaz.
 *
 * Costo y ganancia solo viajan si quien pregunta puede ver costos. Un vendedor
 * con la app instalada no debe conocer el margen de la tienda.
 *
 * @mixin \App\Models\Venta
 */
class VentaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $verCostos = $request->user()?->can('reportes.ver_costos') ?? false;

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'vendida_en' => $this->vendida_en?->toIso8601String(),
            'estado' => $this->estado,
            'metodo_pago' => $this->metodo_pago,
            // Reparto del cobro: con el pago mixto, el método por sí solo ya no
            // dice cuánto entró por caja y cuánto por el banco.
            'monto_efectivo' => (float) $this->monto_efectivo,
            'monto_qr' => (float) $this->monto_qr,
            'subtotal' => (float) $this->subtotal,
            'descuento' => (float) $this->descuento,
            'total' => (float) $this->total,
            // Ni whenCounted con valor por defecto ni $this->detalles->count()
            // a secas: el segundo se evalúa siempre y dispara una consulta por
            // fila —o revienta con lazy loading deshabilitado. Se lee el conteo
            // si vino, y si no, la relación solo cuando ya está cargada.
            'unidades' => $this->detalles_count
                ?? ($this->relationLoaded('detalles') ? $this->detalles->count() : null),
            'cliente' => $this->cliente?->persona?->nombre_completo ?? 'Público general',
            'vendedor' => $this->user?->name,
            'notas' => $this->notas,
            'anulada_en' => $this->anulada_en?->toIso8601String(),
            'motivo_anulacion' => $this->motivo_anulacion,

            $this->mergeWhen($verCostos, fn (): array => [
                'costo_total' => (float) $this->costo_total,
                'ganancia' => (float) $this->ganancia,
            ]),

            'detalles' => VentaDetalleResource::collection($this->whenLoaded('detalles')),
        ];
    }
}
