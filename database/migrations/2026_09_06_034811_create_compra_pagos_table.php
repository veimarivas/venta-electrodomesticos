<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Respaldo de un pago al proveedor: cada compra se puede pagar en varios
     * plazos, y cada pago se respalda con su boucher (la imagen) y su monto.
     */
    public function up(): void
    {
        Schema::create('compra_pagos', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete: un proveedor con pagos registrados no se borra.
            $table->foreignId('compra_id')->constrained('compras')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->decimal('monto', 12, 2);
            $table->string('imagen', 255)->nullable();
            $table->date('fecha');
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index(['compra_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_pagos');
    }
};