<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Un pago hecho al proveedor: su monto y el boucher que lo respalda.
 *
 * @mixin \App\Models\PagoCompra
 */
class PagoCompraResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'monto' => (float) $this->monto,
            'imagen_url' => $this->imagen
                ? Storage::disk('public')->url($this->imagen)
                : null,
            'fecha' => $this->fecha?->toDateString(),
            'notas' => $this->notas,
            'registrado_por' => $this->whenLoaded('user', fn (): ?string => $this->user?->name),
        ];
    }
}