<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Días de trabajo del mes y contraseña nueva obligatoria.
 *
 * - `dias_de_trabajo`: los días que a cada vendedor le toca trabajar, mes a
 *   mes. Con ellos el historial de asistencia cuenta las faltas (día
 *   programado sin entrada) y no solo lo trabajado. Uno por persona y día.
 * - `users.debe_cambiar_password`: la cuenta recién creada o reiniciada entra
 *   con el carnet, que cualquiera que lo conozca podría usar; al entrar se le
 *   pide una contraseña propia y la marca se apaga.
 * - Permiso `asistencia.programar` (supervisor; el administrador por
 *   `Gate::before()`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dias_de_trabajo', function (Blueprint $tabla): void {
            $tabla->id();
            $tabla->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $tabla->date('fecha');
            // Quién lo programó, para saber de dónde salió el calendario.
            $tabla->foreignId('programado_por')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamps();

            $tabla->unique(['user_id', 'fecha']);
            $tabla->index('fecha');
        });

        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->boolean('debe_cambiar_password')->default(false)->after('is_active');
        });

        Permission::findOrCreate('asistencia.programar', 'web');
        Role::query()->where('name', 'supervisor')->first()?->givePermissionTo('asistencia.programar');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('dias_de_trabajo');

        Schema::table('users', function (Blueprint $tabla): void {
            $tabla->dropColumn('debe_cambiar_password');
        });

        Permission::query()->where('name', 'asistencia.programar')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
