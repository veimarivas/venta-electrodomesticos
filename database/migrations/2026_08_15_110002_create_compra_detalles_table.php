<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_detalles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();

            $table->unsignedInteger('cantidad');
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('subtotal', 12, 2);

            // costo_unitario + la parte proporcional de flete y otros gastos.
            // Es el costo que se copia a cada unidad física al recepcionar.
            $table->decimal('costo_real_unitario', 12, 2)->default(0);

            // Precio de venta con el que saldrán las unidades de esta línea.
            $table->decimal('precio_venta', 12, 2)->default(0);

            $table->timestamps();

            // Un producto no debe repetirse en dos líneas de la misma compra:
            // el prorrateo y el conteo de unidades se vuelven ambiguos.
            $table->unique(['compra_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
    }
};
