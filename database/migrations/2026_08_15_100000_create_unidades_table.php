<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            // Los índices de compra llegan en la fase de compras; por ahora
            // son solo columnas (sin FK) para no depender de tablas inexistentes.
            $table->unsignedBigInteger('compra_detalle_id')->nullable();
            $table->unsignedBigInteger('compra_id')->nullable();
            $table->string('serial', 100)->nullable()->unique();
            $table->string('codigo_interno', 40)->unique();
            $table->decimal('costo_unitario', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->enum('estado', [
                'en_stock', 'reservado', 'vendido', 'devuelto', 'danado', 'garantia', 'perdido',
            ])->default('en_stock');
            $table->string('ubicacion', 120)->nullable();
            $table->date('garantia_hasta')->nullable();
            $table->dateTime('ingresado_en')->useCurrent();
            $table->dateTime('vendido_en')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['producto_id', 'estado']);
            $table->index(['estado', 'vendido_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
