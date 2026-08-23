<?php

namespace Database\Factories;

use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->unique()->company(),
            'nit' => (string) fake()->unique()->numberBetween(1000000, 9999999999),
            'contacto' => fake()->name(),
            'telefono' => (string) fake()->numberBetween(60000000, 79999999),
            'correo' => fake()->unique()->companyEmail(),
            'direccion' => fake()->address(),
            'notas' => null,
            'activo' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }
}
