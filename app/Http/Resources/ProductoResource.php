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
            'imagen_url' => $this->imagen
                ? Storage::disk('public')->url($this->imagen)
                : null,

            // Unidades en estado `en_stock`: lo que de verdad se puede vender.
            'disponibles' => $disponibles,
            'agotado' => $disponibles === 0,
            'bajo_minimo' => $this->stock_minimo > 0 && $disponibles < $this->stock_minimo,

            // ---- Solo en la ficha ------------------------------------------
            'descripcion' => $this->when($this->detalle, fn () => $this->descripcion),
            'especificaciones' => $this->when(
                $this->detalle,
                fn (): array => $this->especificacionesComoFilas()
            ),
            'unidades' => UnidadResource::collection($this->whenLoaded('unidades')),
        ];
    }

    /**
     * Las especificaciones se guardan como objeto JSON (`{"Pantalla": "55\""}`)
     * y viajan como lista de pares: en la app se pintan en orden, y un mapa de
     * JSON no garantiza ninguno.
     *
     * @return array<int, array{clave: string, valor: string}>
     */
    private function especificacionesComoFilas(): array
    {
        $filas = [];

        foreach ((array) $this->especificaciones as $clave => $valor) {
            $filas[] = [
                'clave' => (string) $clave,
                // `true` es la bandera que guarda el panel para una
                // característica sin valor («Bluetooth»).
                'valor' => $valor === true ? '' : (string) $valor,
            ];
        }

        return $filas;
    }
}
