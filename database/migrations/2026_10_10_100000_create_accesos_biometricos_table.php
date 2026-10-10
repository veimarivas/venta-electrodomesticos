<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teléfonos registrados para entrar con huella o rostro.
 *
 * El teléfono guarda una llave al azar (en su Keystore) y aquí queda solo su
 * hash: la contraseña ya no se guarda en ningún teléfono. Al verificar la
 * huella, el teléfono manda la llave y recibe un token nuevo.
 *
 * Un teléfono pertenece a una sola persona a la vez (`dispositivo_id` único):
 * la biometría de Android no distingue quién de los dedos registrados la
 * desbloquea, así que dos cuentas en el mismo teléfono serían la misma puerta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accesos_biometricos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('dispositivo_id', 64)->unique();
            $table->string('nombre', 120)->nullable();
            $table->char('llave_hash', 64);
            $table->dateTime('ultimo_uso_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accesos_biometricos');
    }
};
