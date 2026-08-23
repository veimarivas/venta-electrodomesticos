<?php

namespace Database\Seeders;

use App\Models\Marca;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarcaSeeder extends Seeder
{
    /**
     * Marcas habituales en una tienda de electrónica del hogar.
     */
    private const MARCAS = [
        'Samsung', 'LG', 'Sony', 'Panasonic', 'Xiaomi',
        'TCL', 'Hisense', 'Whirlpool', 'Daewoo', 'Bosch',
    ];

    public function run(): void
    {
        foreach (self::MARCAS as $nombre) {
            Marca::firstOrCreate(
                ['slug' => Str::slug($nombre)],
                ['nombre' => $nombre, 'activa' => true]
            );
        }
    }
}
