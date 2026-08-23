<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Teléfono registrado. El token NO se devuelve entero: es la credencial de
 * envío del push y ya la tiene quien lo registró.
 *
 * @mixin \App\Models\Dispositivo
 */
class DispositivoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token_parcial' => \Illuminate\Support\Str::limit($this->token, 12),
            'plataforma' => $this->plataforma,
            'nombre_dispositivo' => $this->nombre_dispositivo,
            'ultimo_uso_en' => $this->ultimo_uso_en?->toIso8601String(),
        ];
    }
}
