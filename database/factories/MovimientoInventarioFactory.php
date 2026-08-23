<?php

namespace Database\Factories;

use App\Models\MovimientoInventario;
use App\Models\Unidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MovimientoInventario>
 */
class MovimientoInventarioFactory extends Factory
{
    protected $model = MovimientoInventario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'unidad_id' => Unidad::factory(),
            'tipo' => 'entrada',
            'estado_anterior' => null,
            'estado_nuevo' => 'en_stock',
            'user_id' => null,
            'cantidad' => 1,
            'notas' => null,
        ];
    }
}
