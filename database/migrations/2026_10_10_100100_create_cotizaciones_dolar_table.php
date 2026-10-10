<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotizaciones del dólar: la oficial del BCB y la del mercado paralelo.
 *
 * Se guarda una fila cuando el valor cambia (o al empezar un día nuevo), no en
 * cada consulta: alcanza para la historia de los últimos días y para seguir
 * enseñando el último valor conocido si la fuente no responde.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones_dolar', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // oficial | paralelo
            $table->decimal('compra', 10, 4);
            $table->decimal('venta', 10, 4);
            $table->string('fuente', 60);
            $table->dateTime('publicado_en')->nullable();
            $table->timestamps();

            $table->index(['tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones_dolar');
    }
};
