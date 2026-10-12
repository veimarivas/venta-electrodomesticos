<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguimiento por tienda: de qué tienda salió cada venta, cada cobro de cuota
 * y cada gasto, y una caja por tienda.
 *
 * El **stock no se separa**: las dos tiendas venden del mismo depósito. Solo el
 * dinero y el seguimiento llevan tienda.
 *
 * `users.tienda_id` es la tienda que la persona eligió para trabajar cuando no
 * marcó asistencia (el administrador, por ejemplo). Se guarda en el usuario y
 * no en el teléfono: vale igual en el panel y en la app.
 *
 * Todo nulo: lo anterior a esta migración no tiene tienda hasta que el
 * administrador marca la principal (ver `TiendaActual::asignarHistorico`).
 */
return new class extends Migration
{
    private const TABLAS = ['ventas', 'cajas', 'pagos_credito', 'gastos', 'users'];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->foreignId('tienda_id')->nullable()->after('id')->constrained('tiendas')->nullOnDelete();

                if ($tabla === 'ventas') {
                    $table->index(['tienda_id', 'vendida_en']);
                }

                if ($tabla === 'cajas') {
                    $table->index(['tienda_id', 'estado']);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if ($tabla === 'ventas') {
                    $table->dropIndex(['tienda_id', 'vendida_en']);
                }

                if ($tabla === 'cajas') {
                    $table->dropIndex(['tienda_id', 'estado']);
                }

                $table->dropConstrainedForeignId('tienda_id');
            });
        }
    }
};
