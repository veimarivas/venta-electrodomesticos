<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las tiendas (sucursales) del negocio, con su ubicación.
 *
 * La ubicación y el radio deciden dónde se puede marcar asistencia: el
 * trabajador marca entrada o salida solo si el GPS del teléfono lo pone dentro
 * del radio de alguna tienda. 30 m de fábrica: el GPS de un celular dentro de
 * un local se equivoca fácilmente entre 10 y 30 m.
 *
 * `hora_entrada` y `tolerancia_minutos` sirven para marcar atrasos; sin hora,
 * la tienda no lleva control de atraso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiendas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('direccion', 255)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->unsignedSmallInteger('radio_metros')->default(30);
            $table->time('hora_entrada')->nullable();
            $table->unsignedSmallInteger('tolerancia_minutos')->default(10);
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendas');
    }
};
