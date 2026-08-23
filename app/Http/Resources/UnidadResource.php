<?php

namespace App\Http\Resources;

use App\Models\Unidad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Unidad física dentro de la ficha de un producto.
 *
 * El costo NO viaja: la ficha del catálogo la consulta cualquiera con
 * `productos.ver`, y el precio de compra no es información de mostrador. Quien
 * necesite márgenes los tiene en los reportes, tras `reportes.ver_costos`.
 *
 * @mixin \App\Models\Unidad
 */
class UnidadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Solo donde la unidad se lista fuera de su producto (las de una
            // compra); dentro de la ficha del producto sería repetirlo en
            // cada fila.
            'producto' => $this->whenLoaded('producto', fn () => $this->producto?->nombre),
            'codigo_interno' => $this->codigo_interno,
            'serial' => $this->serial,
            'estado' => $this->estado,
            'estado_texto' => Unidad::ESTADOS[$this->estado] ?? $this->estado,
            'precio_venta' => (float) $this->precio_venta,
            'ubicacion' => $this->ubicacion,
            'garantia_hasta' => $this->garantia_hasta?->toDateString(),
            'ingresado_en' => $this->ingresado_en?->toIso8601String(),
        ];
    }
}
