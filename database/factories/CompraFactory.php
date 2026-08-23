<?php

namespace Database\Factories;

use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Compra>
 */
class CompraFactory extends Factory
{
    protected $model = Compra::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'proveedor_id' => Proveedor::factory(),
            'user_id' => User::factory(),
            'codigo' => 'COM-'.now()->format('Y').'-'.fake()->unique()->numerify('####'),
            'numero_factura' => fake()->numerify('F-#####'),
            'fecha_compra' => now()->toDateString(),
            'subtotal' => 0,
            'descuento' => 0,
            'impuesto' => 0,
            'flete' => 0,
            'otros_gastos' => 0,
            'total' => 0,
            'moneda' => 'BOB',
            'tipo_cambio' => 1,
            'estado' => 'borrador',
            'recepcionada_en' => null,
            'notas' => null,
        ];
    }

    public function recepcionada(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => 'recepcionada',
            'recepcionada_en' => now(),
        ]);
    }
}
