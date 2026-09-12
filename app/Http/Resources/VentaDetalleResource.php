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
            // La unidad puede no estar cargada en el listado de ventas (solo se
            // pide el producto con su marca); en la ficha sí viaja. El closure
            // evita tocar la relación si no vino: accederla lanzaría un
            // LazyLoadingViolation.
            'codigo_interno' => $this->when(
                $this->relationLoaded('unidad'),
                fn () => $this->unidad?->codigo_interno
            ),
            'serial' => $this->when(
                $this->relationLoaded('unidad'),
                fn () => $this->unidad?->serial
            ),
            'precio_unitario' => (float) $this->precio_unitario,
            'descuento' => (float) $this->descuento,
            'importe' => (float) $this->precio_unitario - (float) $this->descuento,
            // La app necesita saber si el aparato ya volvió —y por qué— para
            // ofrecer (o no) el botón de devolver y marcarlo como devuelto.
            'devuelto' => $this->estaDevuelto(),
            'devuelto_en' => $this->devuelto_en?->toIso8601String(),
            'motivo_devolucion' => $this->motivo_devolucion,

            $this->mergeWhen($verCostos, fn (): array => [
                'costo_unitario' => (float) $this->costo_unitario,
                'ganancia' => (float) $this->ganancia,
            ]),
        ];
    }
}
