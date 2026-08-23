<?php

namespace Database\Factories;

use App\Models\Unidad;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unidad>
 */
class UnidadFactory extends Factory
{
    protected $model = Unidad::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'producto_id' => Producto::factory(),
            'serial' => fake()->unique()->bothify('SN-####-##'),
            'codigo_interno' => fake()->unique()->numerify('ITEM-########'),
            'costo_unitario' => fake()->randomFloat(2, 50, 3000),
            'precio_venta' => fake()->randomFloat(2, 100, 6000),
            'estado' => 'en_stock',
            'ubicacion' => fake()->randomElement([
                'Bodega A / Estante 1',
                'Bodega A / Estante 2',
                'Bodega B / Estante 1',
            ]),
            'ingresado_en' => fake()->dateTimeBetween('-1 year', 'now'),
            'vendido_en' => null,
            'notas' => fake()->optional()->sentence(),
        ];
    }

    public function deProducto(Producto $producto): static
    {
        return $this->state(fn (): array => ['producto_id' => $producto->id]);
    }

    public function vendido(): static
    {
        return $this->state(fn (): array => ['estado' => 'vendido', 'vendido_en' => now()]);
    }
}
