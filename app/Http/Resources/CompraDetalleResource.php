<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una línea de la compra: N unidades del mismo producto a un mismo costo.
 *
 * `costo_unitario` es lo que se le pagó al proveedor por pieza;
 * `costo_real_unitario` es lo que de verdad cuesta esa pieza una vez repartidos
 * el flete y los demás gastos. El margen se calcula contra el segundo: usar el
 * primero infla la ganancia en exactamente lo que costó traer la mercadería.
 *
 * @mixin \App\Models\CompraDetalle
 */
class CompraDetalleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $costoReal = (float) $this->costo_real_unitario;
        $precio = (float) $this->precio_venta;

        return [
            'id' => $this->id,
            'producto' => $this->producto?->nombre,
            'producto_id' => $this->producto_id,
            'sku' => $this->producto?->sku,
            'cantidad' => (int) $this->cantidad,
            'costo_unitario' => (float) $this->costo_unitario,
            'costo_real_unitario' => $costoReal,
            'subtotal' => (float) $this->subtotal,
            'precio_venta' => $precio,

            // Margen por pieza con el costo ya prorrateado. Null mientras la
            // compra es un borrador: hasta recepcionar no hay costo real.
            'margen_unitario' => $costoReal > 0 ? round($precio - $costoReal, 2) : null,

            // Unidades físicas que generó esta línea.
            'unidades' => (int) $this->unidades_count,
        ];
    }
}
