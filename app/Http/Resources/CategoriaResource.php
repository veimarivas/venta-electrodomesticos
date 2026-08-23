<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Categoría del catálogo para la app.
 *
 * Viaja plana con su `padre_id` y su `nivel`: el árbol se dibuja en el
 * teléfono con una sangría, y mandarlo anidado obligaría a la app a recorrer
 * una estructura recursiva para algo que se ve como una lista.
 *
 * @mixin \App\Models\Categoria
 */
class CategoriaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'padre_id' => $this->padre_id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'descripcion' => $this->descripcion,
            // Profundidad y conteo de rama los calcula el controlador al
            // aplanar el árbol y los deja puestos en el modelo.
            'nivel' => (int) $this->nivel,
            'activo' => (bool) $this->activo,
            'imagen_url' => $this->imagen
                ? Storage::disk('public')->url($this->imagen)
                : null,
            // Productos colgados directamente de esta categoría.
            'productos' => (int) $this->productos_count,
            // Y los de toda su rama: un padre con el catálogo repartido entre
            // sus hijas parecería vacío si solo se contaran los directos.
            'productos_rama' => (int) $this->productos_rama,
            'subcategorias' => (int) $this->hijos_count,
        ];
    }
}
