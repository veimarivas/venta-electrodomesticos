<?php

namespace Database\Factories;

use App\Models\Gasto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gasto>
 */
class GastoFactory extends Factory
{
    protected $model = Gasto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fecha' => now()->toDateString(),
            'concepto' => fake()->randomElement(['Almuerzo', 'Flete', 'Internet', 'Limpieza']),
            'categoria' => fake()->randomElement(array_keys(Gasto::CATEGORIAS)),
            'monto' => fake()->randomFloat(2, 10, 300),
            'metodo_pago' => 'qr',
        ];
    }
}
