<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ficha comercial del cliente. Los datos personales (carnet, nombres,
     * celular, dirección…) viven en `personas`: aquí solo va lo que hace a
     * alguien cliente de la tienda, igual que en `trabajadores`.
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();

            // 1 a 1 con personas: el índice único impide que una misma
            // persona quede registrada dos veces como cliente.
            $table->foreignId('persona_id')->unique()->constrained('personas')->cascadeOnDelete();

            $table->string('codigo', 30)->unique();

            $table->timestamps();
            // La ficha se archiva, no se borra: las ventas que se implementen
            // después seguirán apuntando a este cliente.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
