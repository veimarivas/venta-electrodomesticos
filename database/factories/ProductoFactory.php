<?php

namespace Database\Factories;

use App\Models\Marca;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->words(3, true);

        return [
            'categoria_id' => Categoria::factory(),
            'marca_id' => null,
            'sku' => fake()->unique()->bothify(strtoupper('????-####')),
            'nombre' => $nombre,
            'slug' => Str::slug($nombre),
            'modelo' => fake()->bothify('??-####'),
            'descripcion' => fake()->paragraph(),
            'especificaciones' => ['color' => fake()->safeColorName(), 'peso' => '3 kg'],
            'imagen' => null,
            'precio_venta' => fake()->randomFloat(2, 50, 5000),
            'stock_minimo' => fake()->numberBetween(0, 10),
            'meses_garantia' => fake()->numberBetween(6, 24),
            'activo' => true,
        ];
    }

    public function conMarca(?Marca $marca = null): static
    {
        return $this->state(fn (): array => ['marca_id' => $marca?->id ?? Marca::factory()]);
    }
}
