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
    /** ¿Se pide la ficha completa (kardex, compra, venta) o solo la fila? */
    private bool $detalle = false;

    /**
     * Ficha completa del aparato: la pantalla que se abre al escanearlo.
     *
     * Va aparte y no siempre porque un listado de doscientas unidades con su
     * kardex dentro sería una respuesta enorme para pintar una tabla.
     */
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
        $fila = [
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

        if (! $this->detalle) {
            return $fila;
        }

        $venta = $this->ventaDetalle?->venta;

        return [
            ...$fila,
            'producto_id' => $this->producto_id,
            'sku' => $this->producto?->sku,
            'marca' => $this->producto?->marca?->nombre,
            'categoria' => $this->producto?->categoria?->nombre,
            'notas' => $this->notas,
            'vendido_en' => $this->vendido_en?->toIso8601String(),
            // El costo es el único dato con permiso propio: quien mira el
            // inventario desde el mostrador ve qué hay y dónde está, no cuánto
            // margen deja.
            'costo_unitario' => $this->when(
                $request->user()?->can('reportes.ver_costos') ?? false,
                fn () => (float) $this->costo_unitario
            ),
            'compra' => $this->compra === null ? null : [
                'id' => $this->compra->id,
                'codigo' => $this->compra->codigo,
                'proveedor' => $this->compra->proveedor?->nombre,
                'fecha' => $this->compra->fecha_compra?->toDateString(),
            ],
            // Si el aparato salió, por dónde salió. Es lo primero que se
            // pregunta cuando alguien lo busca y no está en la estantería.
            'venta' => $venta === null ? null : [
                'id' => $venta->id,
                'codigo' => $venta->codigo,
                'estado' => $venta->estado,
                'fecha' => $venta->vendida_en?->toIso8601String(),
            ],
            'kardex' => MovimientoResource::collection(
                $this->whenLoaded('movimientos')
            ),
        ];
    }
}
