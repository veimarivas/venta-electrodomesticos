<?php

namespace Database\Seeders;

use App\Models\Marca;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductoSeeder extends Seeder
{
    /**
     * Productos de ejemplo. Idempotente: usa firstOrCreate por slug, así que
     * se puede correr varias veces sin duplicar. Requiere que las categorías
     * y marcas existan (Corren antes en DatabaseSeeder).
     */
    private const PRODUCTOS = [
        ['nombre' => 'Smart TV 55" 4K', 'sku' => 'TVSAM55', 'modelo' => 'UN55CU8000', 'categoria' => 'televisores', 'marca' => 'samsung', 'precio' => 4299.00],
        ['nombre' => 'Barra de sonido 2.1', 'sku' => 'BARSON21', 'modelo' => 'HW-B650', 'categoria' => 'audio', 'marca' => 'samsung', 'precio' => 1899.00],
        ['nombre' => 'Audífonos Bluetooth', 'sku' => 'AUDXMAIR', 'modelo' => 'Redmi Buds 4', 'categoria' => 'audio', 'marca' => 'xiaomi', 'precio' => 349.00],
        ['nombre' => 'Laptop 15.6" Ryzen 5', 'sku' => 'LAPHP15', 'modelo' => '15-fc0003la', 'categoria' => 'computacion', 'marca' => null, 'precio' => 5299.00],
        ['nombre' => 'Refrigerador 12 pies', 'sku' => 'REFWH12', 'modelo' => 'WRM12', 'categoria' => 'refrigeracion', 'marca' => 'whirlpool', 'precio' => 3599.00],
        ['nombre' => 'Lavadora 18 kg', 'sku' => 'LAVDA18', 'modelo' => 'WM18', 'categoria' => 'lavado', 'marca' => 'daewoo', 'precio' => 2799.00],
        ['nombre' => 'Cable HDMI 2.1 2m', 'sku' => 'CABHDMI21', 'modelo' => 'HDMI21-2M', 'categoria' => 'accesorios', 'marca' => null, 'precio' => 89.00],
    ];

    public function run(): void
    {
        foreach (self::PRODUCTOS as $datos) {
            $categoria = Categoria::where('slug', $datos['categoria'])->first();
            $marca = $datos['marca'] ? Marca::where('slug', $datos['marca'])->first() : null;

            Producto::firstOrCreate(
                ['slug' => Str::slug($datos['nombre'])],
                [
                    'nombre' => $datos['nombre'],
                    'sku' => $datos['sku'],
                    'categoria_id' => $categoria?->id,
                    'marca_id' => $marca?->id,
                    'modelo' => $datos['modelo'],
                    'precio_venta' => $datos['precio'],
                    // Margen de negociación del mostrador: un 5 % del precio.
                    // Sin él, el POS no dejaría rebajar ni un centavo.
                    'descuento_maximo' => round($datos['precio'] * 0.05, 2),
                    'stock_minimo' => 3,
                    'meses_garantia' => 12,
                    'activo' => true,
                ]
            );
        }
    }
}
