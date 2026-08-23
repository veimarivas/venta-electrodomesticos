<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabajadores', function (Blueprint $table) {
            $table->id();

            // 1 a 1 con personas: el índice único impide que una misma
            // persona quede registrada dos veces como trabajador.
            $table->foreignId('persona_id')->unique()->constrained('personas')->cascadeOnDelete();

            // 1 a N desde cargos: un cargo tiene muchos trabajadores.
            // restrictOnDelete evita borrar un cargo que esté en uso.
            $table->foreignId('cargo_id')->constrained('cargos')->restrictOnDelete();

            $table->string('codigo', 30)->unique();
            $table->date('fecha_ingreso');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajadores');
    }
};
