<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategoriaSeeder extends Seeder
{
    /**
     * Árbol de ejemplo. Idempotente: usa firstOrCreate por slug, así que se
     * puede correr varias veces sin duplicar categorías.
     */
    private const ARBOL = [
        'Electrónica' => [
            'descripcion' => 'Dispositivos electrónicos de consumo y computación.',
            'hijos' => [
                'Audio' => 'Equipos de sonido, parlantes y accesorios.',
                'Televisores' => 'Pantallas y smart TV de todas las marcas.',
                'Computación' => 'Computadoras, laptops y componentes.',
            ],
        ],
        'Línea blanca' => [
            'descripcion' => 'Electrodomésticos grandes para el hogar.',
            'hijos' => [
                'Refrigeración' => 'Refrigeradores y congeladores.',
                'Lavado' => 'Lavadoras, secadoras y centros de lavado.',
            ],
        ],
        'Accesorios' => [
            'descripcion' => 'Cables, cargadores y complementos.',
            'hijos' => [],
        ],
    ];

    public function run(): void
    {
        $posicionRaiz = 0;

        foreach (self::ARBOL as $nombre => $datos) {
            $padre = Categoria::firstOrCreate(
                ['slug' => Str::slug($nombre)],
                [
                    'nombre' => $nombre,
                    'descripcion' => $datos['descripcion'],
                    'posicion' => $posicionRaiz++,
                    'activo' => true,
                ]
            );

            $posicion = 0;

            foreach ($datos['hijos'] as $hijo => $descripcion) {
                Categoria::firstOrCreate(
                    ['slug' => Str::slug($hijo)],
                    [
                        'padre_id' => $padre->id,
                        'nombre' => $hijo,
                        'descripcion' => $descripcion,
                        'posicion' => $posicion++,
                        'activo' => true,
                    ]
                );
            }
        }
    }
}
