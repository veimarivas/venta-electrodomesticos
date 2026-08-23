<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\Compra;
use App\Models\CompraDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompraDetalle>
 */
class CompraDetalleFactory extends Factory
{
    protected $model = CompraDetalle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cantidad = fake()->numberBetween(1, 5);
        $costo = fake()->randomFloat(2, 100, 3000);

        return [
            'compra_id' => Compra::factory(),
            'producto_id' => Producto::factory(),
            'cantidad' => $cantidad,
            'costo_unitario' => $costo,
            'subtotal' => round($costo * $cantidad, 2),
            'costo_real_unitario' => 0,
            'precio_venta' => round($costo * 1.35, 2),
        ];
    }
}
