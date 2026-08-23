<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id();

            // Relación 1 a 1 con users. Es nullable porque una persona puede
            // estar registrada sin tener acceso al sistema (por ejemplo, un
            // trabajador que no usa el panel). El índice único garantiza que
            // una cuenta de usuario pertenezca a una sola persona.
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('carnet', 20)->unique();
            $table->string('nombres', 100);
            $table->string('apellido_paterno', 60);
            $table->string('apellido_materno', 60)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('correo', 150)->nullable()->unique();
            $table->date('fecha_nacimiento')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // El listado busca y ordena por apellidos.
            $table->index(['apellido_paterno', 'apellido_materno']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
