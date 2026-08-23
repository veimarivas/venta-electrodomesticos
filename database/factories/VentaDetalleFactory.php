<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\Unidad;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VentaDetalle>
 */
class VentaDetalleFactory extends Factory
{
    protected $model = VentaDetalle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'venta_id' => Venta::factory(),
            'unidad_id' => Unidad::factory(),
            'unidad_vendida_id' => null,
            'producto_id' => Producto::factory(),
            'precio_unitario' => 1500,
            'costo_unitario' => 1000,
            'descuento' => 0,
            'ganancia' => 500,
        ];
    }
}
