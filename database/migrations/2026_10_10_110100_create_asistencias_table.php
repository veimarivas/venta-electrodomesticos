<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asistencia del personal: una fila por turno (entrada → salida).
 *
 * Se guarda dónde estaba el teléfono al marcar (coordenadas, distancia a la
 * tienda y precisión del GPS): si alguien discute un atraso, está el dato. Un
 * día puede tener más de un turno (salir a almorzar y volver).
 *
 * `minutos_atraso` se calcula al marcar la primera entrada del día contra la
 * hora de entrada de la tienda; null si la tienda no tiene hora o no es la
 * primera entrada del día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tienda_id')->constrained('tiendas')->restrictOnDelete();
            $table->date('fecha');

            $table->dateTime('entrada_en');
            $table->decimal('entrada_latitud', 10, 7);
            $table->decimal('entrada_longitud', 10, 7);
            $table->unsignedInteger('entrada_distancia');
            $table->unsignedSmallInteger('entrada_precision')->nullable();

            $table->dateTime('salida_en')->nullable();
            $table->decimal('salida_latitud', 10, 7)->nullable();
            $table->decimal('salida_longitud', 10, 7)->nullable();
            $table->unsignedInteger('salida_distancia')->nullable();
            $table->unsignedSmallInteger('salida_precision')->nullable();

            $table->unsignedSmallInteger('minutos_atraso')->nullable();

            // Una salida puesta por el administrador (el trabajador olvidó
            // marcarla) queda a la vista, con quién y por qué.
            $table->foreignId('corregida_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('notas', 255)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'fecha']);
            $table->index(['tienda_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
