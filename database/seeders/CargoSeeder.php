<?php

namespace Database\Seeders;

use App\Models\Cargo;
use Illuminate\Database\Seeder;

class CargoSeeder extends Seeder
{
    /**
     * Cargos habituales en una tienda de electrónica del hogar.
     */
    private const CARGOS = [
        'Administrador',
        'Supervisor de ventas',
        'Vendedor',
        'Cajero',
        'Encargado de almacén',
        'Técnico de instalación',
        'Chofer de reparto',
    ];

    public function run(): void
    {
        foreach (self::CARGOS as $nombre) {
            Cargo::firstOrCreate(['nombre' => $nombre]);
        }
    }
}
