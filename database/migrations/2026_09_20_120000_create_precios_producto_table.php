<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de precios de venta por producto y fecha.
     *
     * El precio de un producto puede moverse —el proveedor sube, la competencia
     * baja—, así que se fija al empezar la jornada y queda registrado. El
     * **último registrado es el que se ofrece en el punto de venta**; el que
     * trae cada unidad queda solo como respaldo. Una fila por producto y fecha:
     * volver a guardar el mismo día corrige la del día, no acumula.
     */
    public function up(): void
    {
        Schema::create('precios_producto', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->date('fecha');
            $table->decimal('precio_venta', 12, 2);

            // Costo con el que se comparó al fijar el precio: sirve para
            // entender por qué se puso ese número, no para calcular nada.
            $table->decimal('costo_referencia', 12, 2)->nullable();

            $table->string('notas', 255)->nullable();
            $table->timestamps();

            $table->unique(['producto_id', 'fecha']);
            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_producto');
    }
};
