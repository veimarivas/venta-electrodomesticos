<?php

namespace Database\Factories;

use App\Models\QrCobro;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QrCobro>
 */
class QrCobroFactory extends Factory
{
    protected $model = QrCobro::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'QR '.fake()->unique()->numerify('###'),
            'banco' => fake()->randomElement(['Banco Unión', 'BNB', 'Banco Mercantil']),
            'titular' => fake()->name(),
            'imagen' => 'qrs-cobro/ejemplo.png',
            'fecha_limite' => now()->addMonth()->toDateString(),
            'activo' => true,
        ];
    }

    /** QR que ya caducó: el POS no debe ofrecerlo. */
    public function caducado(): static
    {
        return $this->state(fn (): array => ['fecha_limite' => now()->subDay()->toDateString()]);
    }
}
