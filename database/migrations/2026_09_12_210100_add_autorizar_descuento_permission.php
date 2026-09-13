<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea el permiso para autorizar descuentos por debajo del mínimo.
 *
 * El rol `admin` no necesita que se le asigne: `Gate::before()` le concede
 * todo. El permiso existe para poder dárselo a otro rol desde «Roles y
 * permisos» —el supervisor, por ejemplo— sin tocar código. La migración es
 * idempotente y limpia la caché de permisos para que surta efecto en la misma
 * petición.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::findOrCreate('ventas.autorizar_descuento', 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::findByName('ventas.autorizar_descuento', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
