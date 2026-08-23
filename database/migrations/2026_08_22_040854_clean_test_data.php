<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        // Tablas a limpiar (se preservan: users, personas, trabajadores, cargos,
        // roles, permissions, role_has_permissions, model_has_roles, model_has_permissions)
        $tables = [
            'venta_detalles',
            'ventas',
            'qrs_cobro',
            'movimientos_inventario',
            'compra_detalles',
            'compras',
            'clientes',
            'productos',
            'categorias',
            'marcas',
            'unidades',
            'proveedores',
            'dispositivos',
            'passkeys',
            'notifications',
            'personal_access_tokens',
            // Sistema / caché
            'cache',
            'cache_locks',
            'sessions',
            'jobs',
            'job_batches',
            'failed_jobs',
            'password_reset_tokens',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(): void
    {
        // No se puede revertir: los datos de prueba se perdieron.
    }
};
