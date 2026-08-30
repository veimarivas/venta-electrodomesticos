<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una cuota del plan, para el estado de cuenta de la app.
 *
 * @mixin \App\Models\Cuota
 */
class CuotaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'vence_en' => $this->vence_en?->toDateString(),
            'monto' => (float) $this->monto,
            'monto_pagado' => (float) $this->monto_pagado,
            'falta' => (float) $this->falta,
            // Los tres estados van calculados del servidor: «vencida» depende
            // de la fecha de hoy, y la del teléfono puede estar mal.
            'esta_pagada' => $this->esta_pagada,
            'esta_vencida' => $this->esta_vencida,
            'etiqueta_estado' => $this->etiqueta_estado,
            'pagada_en' => $this->pagada_en?->toIso8601String(),
        ];
    }
}
