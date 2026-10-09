<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permisos de esta ronda.
 *
 * - `compras.verificar`: ver y verificar **solo** las compras asignadas. Se le
 *   da al vendedor, que no tiene el resto de Compras.
 * - `gastos.*`, `reportes.seguimiento` (resumen del día y ventas por vendedor)
 *   y `ajustes.editar` (la caja obligatoria): de fábrica no los tiene ningún
 *   rol; el administrador los tiene por `Gate::before()`.
 *
 * Idempotente, como la del permiso de autorizar descuentos.
 */
return new class extends Migration
{
    private const PERMISOS = [
        'compras.verificar',
        'gastos.ver', 'gastos.crear', 'gastos.editar', 'gastos.eliminar',
        'reportes.seguimiento',
        'ajustes.editar',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        Role::query()->where('name', 'vendedor')->first()?->givePermissionTo('compras.verificar');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', self::PERMISOS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
