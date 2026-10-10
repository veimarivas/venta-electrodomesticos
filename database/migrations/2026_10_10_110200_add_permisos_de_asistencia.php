<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos de tiendas y asistencia.
 *
 * - `asistencia.marcar`: marcar la propia entrada y salida y ver el propio
 *   historial. Vendedor y supervisor.
 * - `asistencia.ver`: el historial de todos y corregir una salida olvidada.
 *   Supervisor (y el administrador por `Gate::before()`).
 * - `tiendas.*`: dar de alta tiendas y fijar su ubicación. Solo administrador.
 *
 * Idempotente, como las anteriores.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'tiendas.ver', 'tiendas.crear', 'tiendas.editar', 'tiendas.eliminar',
        'asistencia.marcar', 'asistencia.ver',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        Role::query()->where('name', 'vendedor')->first()?->givePermissionTo('asistencia.marcar');
        Role::query()->where('name', 'supervisor')->first()?->givePermissionTo(['asistencia.marcar', 'asistencia.ver']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', self::PERMISOS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
