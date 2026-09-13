<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reserva temporal de la unidad mientras está en un carrito del POS.
 *
 * Al agregar un aparato al carrito queda en estado `reservado` para que otra
 * caja no lo venda. La reserva vence (`reservado_hasta`): si el carrito se
 * abandona, el aparato vuelve solo al stock en vez de quedarse bloqueado para
 * siempre.
 *
 * Se guarda `reservado_por` para liberar solo las reservas de quien las hizo y
 * para que el vendedor pueda cobrar el aparato que él mismo apartó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $tabla): void {
            $tabla->foreignId('reservado_por')->nullable()->after('estado')
                ->constrained('users')->nullOnDelete();

            $tabla->timestamp('reservado_hasta')->nullable()->after('reservado_por');

            // El barrido de reservas vencidas consulta por estado y fecha.
            $tabla->index(['estado', 'reservado_hasta']);
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $tabla): void {
            $tabla->dropIndex(['estado', 'reservado_hasta']);
            $tabla->dropConstrainedForeignId('reservado_por');
            $tabla->dropColumn('reservado_hasta');
        });
    }
};
