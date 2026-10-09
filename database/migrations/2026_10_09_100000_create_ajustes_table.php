<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajustes de la tienda que el administrador cambia sin tocar el `.env`.
     *
     * Clave y valor, sin columnas por ajuste: hoy solo está «la caja es
     * obligatoria para vender», y un ajuste nuevo no debería pedir una
     * migración. Lo que no está guardado vale lo que diga su valor por defecto
     * en `App\Support\Ajustes`.
     */
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->text('valor')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes');
    }
};
