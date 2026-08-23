<?php

namespace Database\Factories;

use App\Models\Cargo;
use App\Models\Persona;
use App\Models\Trabajador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Trabajador>
 */
class TrabajadorFactory extends Factory
{
    protected $model = Trabajador::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'persona_id' => Persona::factory(),
            'cargo_id' => Cargo::factory(),
            'codigo' => 'TRB-'.fake()->unique()->numerify('####'),
            'fecha_ingreso' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
        ];
    }
}
