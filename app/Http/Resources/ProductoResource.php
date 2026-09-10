<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Producto del catálogo para la app.
 *
 * El listado y la ficha usan el mismo recurso: lo que solo tiene sentido con
 * el producto abierto (descripción, especificaciones, unidades físicas) se
 * añade con `whenLoaded`/`when`, así una lista de 20 productos no arrastra
 * media base de datos.
 *
 * @mixin \App\Models\Producto
 */
class ProductoResource extends JsonResource
{
    /**
     * ¿Se está pintando la ficha completa?
     *
     * Es una propiedad del recurso y no un atributo del modelo a propósito:
     * con `Model::shouldBeStrict()` activo, leer un atributo que no existe
     * lanza excepción en vez de devolver null.
     */
    public bool $detalle = false;

    /** Ficha completa: descripción, especificaciones y unidades físicas. */
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
        $disponibles = (int) $this->disponibles;

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'modelo' => $this->modelo,
            'categoria' => $this->categoria?->nombre,
            'categoria_id' => $this->categoria_id,
            'marca' => $this->marca?->nombre,
            'marca_id' => $this->marca_id,
            'precio_venta' => (float) $this->precio_venta,
            'descuento_maximo' => (float) $this->descuento_maximo,
            'stock_minimo' => (int) $this->stock_minimo,
            'meses_garantia' => (int) $this->meses_garantia,
            'activo' => (bool) $this->activo,
            'tiene_serial' => (bool) $this->tiene_serial,
            'imagen_url' => $this->imagen
                ? Storage::disk('public')->url($this->imagen)
                : null,

            // Unidades en estado `en_stock`: lo que de verdad se puede vender.
            'disponibles' => $disponibles,
            'agotado' => $disponibles === 0,
            'bajo_minimo' => $this->stock_minimo > 0 && $disponibles < $this->stock_minimo,

            // Cuántos aparatos de este modelo se han vendido. Va siempre: en el
            // inventario es lo que se ve junto al stock, y en el catálogo no
            // estorba (0 en un producto que nunca salió).
            'vendidos' => (int) $this->vendidas,

            // ---- Solo en la ficha ------------------------------------------
            'descripcion' => $this->when($this->detalle, fn () => $this->descripcion),
            'especificaciones' => $this->when(
                $this->detalle,
                fn (): array => $this->especificaciones
                    ->map(fn ($e): array => [
                        'clave' => $e->clave,
                        // `valor` null es la bandera de distintivo sin valor
                        // («Bluetooth»); la app la pinta como vacía.
                        'valor' => $e->valor ?? '',
                    ])
                    ->values()
                    ->all()
            ),
            'unidades' => UnidadResource::collection($this->whenLoaded('unidades')),
        ];
    }
}
