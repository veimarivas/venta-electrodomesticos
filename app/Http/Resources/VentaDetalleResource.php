<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una línea de venta: el aparato concreto que salió por caja.
 *
 * @mixin \App\Models\VentaDetalle
 */
class VentaDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $verCostos = $request->user()?->can('reportes.ver_costos') ?? false;

        return [
            'id' => $this->id,
            'producto' => $this->producto?->nombre,
            'codigo_interno' => $this->unidad?->codigo_interno,
            'serial' => $this->unidad?->serial,
            'precio_unitario' => (float) $this->precio_unitario,
            'descuento' => (float) $this->descuento,
            'importe' => (float) $this->precio_unitario - (float) $this->descuento,

            $this->mergeWhen($verCostos, fn (): array => [
                'costo_unitario' => (float) $this->costo_unitario,
                'ganancia' => (float) $this->ganancia,
            ]),
        ];
    }
}
