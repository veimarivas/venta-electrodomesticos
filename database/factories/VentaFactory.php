<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venta>
 */
class VentaFactory extends Factory
{
    protected $model = Venta::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cliente_id' => null,
            'user_id' => User::factory(),
            'codigo' => 'VTA-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'vendida_en' => now(),
            'subtotal' => 0,
            'descuento' => 0,
            'total' => 0,
            'costo_total' => 0,
            'ganancia' => 0,
            'metodo_pago' => 'efectivo',
            'estado' => 'completada',
        ];
    }

    public function anulada(): static
    {
        return $this->state(fn (): array => [
            'estado' => 'anulada',
            'anulada_en' => now(),
            'motivo_anulacion' => 'Anulada en pruebas',
        ]);
    }
}
