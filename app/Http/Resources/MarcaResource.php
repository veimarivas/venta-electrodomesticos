<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Marca del catálogo para la app.
 *
 * @mixin \App\Models\Marca
 */
class MarcaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'slug' => $this->slug,
            'activa' => (bool) $this->activa,
            'logo_url' => $this->logo_ruta
                ? Storage::disk('public')->url($this->logo_ruta)
                : null,
            'productos' => (int) $this->productos_count,
            // Unidades en stock de toda la marca: es lo que se pregunta en el
            // almacén («¿queda algo de Samsung?»), no cuántos modelos hay.
            'disponibles' => (int) $this->disponibles,
        ];
    }
}
