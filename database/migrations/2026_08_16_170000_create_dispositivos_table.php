<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Teléfonos registrados para las notificaciones push (FCM).
     *
     * El token es único a nivel de tabla, no por usuario: Firebase lo emite por
     * instalación de la app, así que si un teléfono cambia de dueño el token
     * debe migrar de fila, no duplicarse. `updateOrCreate` sobre esa clave lo
     * resuelve solo.
     */
    public function up(): void
    {
        Schema::create('dispositivos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('token', 255)->unique();
            $table->enum('plataforma', ['android', 'ios'])->default('android');
            $table->string('nombre_dispositivo', 120)->nullable();
            $table->dateTime('ultimo_uso_en')->nullable();

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos');
    }
};
