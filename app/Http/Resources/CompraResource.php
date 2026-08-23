<?php

namespace App\Http\Resources;

use App\Models\Compra;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Orden de compra.
 *
 * Los importes van desglosados y no solo el total: en una compra el total no
 * dice de dónde sale el costo de cada aparato. El flete y los otros gastos se
 * reparten entre las unidades (`gastos_prorrateables`) y el impuesto no, porque
 * en Bolivia suele ser recuperable — de ahí que sean campos distintos.
 *
 * @mixin \App\Models\Compra
 */
class CompraResource extends JsonResource
{
    /** ¿Ficha completa? Ver la nota de ProductoResource sobre shouldBeStrict. */
    public bool $detalle = false;

    public function conDetalle(): static
    {
        $this->detalle = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'numero_factura' => $this->numero_factura,
            'fecha_compra' => $this->fecha_compra?->toDateString(),
            'estado' => $this->estado,
            'estado_texto' => Compra::ESTADOS[$this->estado] ?? $this->estado,
            'es_borrador' => $this->es_borrador,
            'esta_recepcionada' => $this->esta_recepcionada,
            'recepcionada_en' => $this->recepcionada_en?->toIso8601String(),

            'proveedor' => $this->proveedor?->nombre,
            'proveedor_id' => $this->proveedor_id,
            'registrada_por' => $this->user?->name,

            // Desglose. `gastos_prorrateables` es el dato que explica por qué
            // el costo de una unidad no es el que figura en su línea.
            'subtotal' => (float) $this->subtotal,
            'descuento' => (float) $this->descuento,
            'impuesto' => (float) $this->impuesto,
            'flete' => (float) $this->flete,
            'otros_gastos' => (float) $this->otros_gastos,
            'gastos_prorrateables' => (float) $this->gastos_prorrateables,
            'total' => (float) $this->total,
            'moneda' => $this->moneda,
            'tipo_cambio' => (float) $this->tipo_cambio,

            'lineas' => (int) $this->detalles_count,
            // Unidades físicas que generó al recepcionarse. En un borrador es
            // 0 porque todavía no existen: se crean al recibir la mercadería.
            'unidades' => (int) $this->unidades_count,

            'notas' => $this->when($this->detalle, fn () => $this->notas),
            'detalles' => CompraDetalleResource::collection($this->whenLoaded('detalles')),
        ];
    }
}
