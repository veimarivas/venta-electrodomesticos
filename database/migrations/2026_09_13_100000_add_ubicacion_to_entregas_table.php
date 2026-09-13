<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ubicación de Google Maps para la entrega.
 *
 * Se guarda un **enlace**, no latitud/longitud: el vendedor comparte el punto
 * desde el teléfono (o lo pega de Maps) y el repartidor lo abre con un toque.
 * Guardar coordenadas obligaría a una clave de Google y a un selector de mapa
 * dentro del POS, y este dato se captura con el cliente delante.
 *
 * La dirección escrita sigue siendo la fuente; esto es un apoyo para llegar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entregas', function (Blueprint $tabla): void {
            $tabla->string('ubicacion_url', 500)->nullable()->after('referencia');
        });
    }

    public function down(): void
    {
        Schema::table('entregas', function (Blueprint $tabla): void {
            $tabla->dropColumn('ubicacion_url');
        });
    }
};
