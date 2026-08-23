<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Cargo de la tienda, con cuánta gente lo ocupa.
 *
 * El conteo de bajas va aparte del de activos: un cargo con diez fichas de las
 * que ocho son antiguos trabajadores no está ocupado por diez personas.
 *
 * @mixin \App\Models\Cargo
 */
class CargoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'trabajadores' => (int) $this->trabajadores_count,
            'activos' => (int) $this->activos,
            'dados_de_baja' => (int) $this->trabajadores_count - (int) $this->activos,
        ];
    }
}
