<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Invierte la dirección de la relación 1 a 1 con personas: la clave
     * foránea pasa de vivir en personas (user_id) a vivir en users (persona_id),
     * y deja de ser nullable. Así la base de datos garantiza que no puede
     * existir un usuario sin su ficha personal registrada.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('persona_id')->nullable()->after('id');
        });

        // Reutiliza los enlaces que ya existían en personas.user_id.
        DB::table('personas')->whereNotNull('user_id')->orderBy('id')->each(function ($persona) {
            DB::table('users')->where('id', $persona->user_id)->update(['persona_id' => $persona->id]);
        });

        // Los usuarios que ya existían sin ficha reciben una persona de respaldo,
        // para que la columna pueda pasar a NOT NULL sin reventar la migración.
        DB::table('users')->whereNull('persona_id')->orderBy('id')->each(function ($user) {
            $personaId = DB::table('personas')->insertGetId([
                'carnet' => 'USR'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'nombres' => $user->name,
                'apellido_paterno' => 'Pendiente',
                'apellido_materno' => null,
                'celular' => null,
                'direccion' => null,
                'correo' => null,
                'fecha_nacimiento' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('id', $user->id)->update(['persona_id' => $personaId]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('persona_id')->nullable(false)->change();
            $table->unique('persona_id');
            $table->foreign('persona_id')->references('id')->on('personas')->restrictOnDelete();
        });

        Schema::table('personas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
            $table->dropColumn('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->unique()->constrained()->nullOnDelete();
        });

        DB::table('users')->whereNotNull('persona_id')->orderBy('id')->each(function ($user) {
            DB::table('personas')->where('id', $user->persona_id)->update(['user_id' => $user->id]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['persona_id']);
            $table->dropUnique(['persona_id']);
            $table->dropColumn('persona_id');
        });
    }
};