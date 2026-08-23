<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una fila = una unidad física vendida.
     *
     * No hay columna `cantidad`: el inventario está serializado, así que
     * vender tres televisores son tres filas, cada una con su aparato.
     */
    public function up(): void
    {
        Schema::create('venta_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();

            // Qué aparato se vendió. NO es único: una unidad devuelta tras
            // anular vuelve al stock y se puede volver a vender, así que puede
            // aparecer en varias líneas a lo largo de su vida.
            $table->foreignId('unidad_id')->constrained('unidades')->restrictOnDelete();

            // Guardia de la doble venta, a nivel de BASE DE DATOS.
            //
            // Copia de unidad_id mientras la venta está completada, y NULL
            // cuando se anula. En MySQL los NULL no chocan entre sí, así que
            // el índice único impide que un aparato esté en dos ventas VIVAS a
            // la vez, pero deja revenderlo si la anterior se anuló.
            //
            // No basta con comprobarlo en PHP: dos cajeros escaneando el mismo
            // aparato a la vez pasarían la comprobación, y solo el índice
            // único frena la segunda venta.
            $table->unsignedBigInteger('unidad_vendida_id')->nullable()->unique();

            // Denormalizado: los reportes por producto no tienen que pasar por
            // unidades para saber qué se vendió.
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();

            $table->decimal('precio_unitario', 12, 2);
            // Copiado de unidades.costo_unitario en el momento de la venta: si
            // mañana cambia el costo, la ganancia histórica no debe moverse.
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('ganancia', 12, 2)->default(0);

            $table->timestamps();

            $table->index('producto_id');
            $table->index('unidad_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
    }
};
