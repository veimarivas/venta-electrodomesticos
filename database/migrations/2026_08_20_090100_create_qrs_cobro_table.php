<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * QR de cobro de la tienda.
     *
     * Los QR bancarios caducan: el banco emite una imagen con fecha límite y
     * pasada esa fecha el cliente no puede pagar con ella. Por eso la fecha no
     * es un dato informativo sino la condición para que el POS lo ofrezca.
     *
     * No se borran nunca de verdad (softDeletes): las ventas cobradas por QR
     * apuntan al que se mostró, y el histórico tiene que poder reconstruirse.
     */
    public function up(): void
    {
        Schema::create('qrs_cobro', function (Blueprint $table) {
            $table->id();

            $table->string('nombre', 100);
            $table->string('banco', 100)->nullable();
            $table->string('titular', 150)->nullable();
            $table->string('imagen', 255);
            $table->date('fecha_limite');
            $table->boolean('activo')->default(true);
            $table->text('notas')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // El POS pregunta siempre por lo mismo: activos y sin caducar.
            $table->index(['activo', 'fecha_limite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qrs_cobro');
    }
};
