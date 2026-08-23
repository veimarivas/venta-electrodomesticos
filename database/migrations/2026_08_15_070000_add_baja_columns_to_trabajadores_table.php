<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dar de baja a un trabajador no puede borrar su ficha: las ventas, las
     * compras y el kardex que vengan después seguirán apuntando a él. La baja
     * pasa a ser un estado con fecha, y el registro permanece siempre.
     */
    public function up(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->date('fecha_baja')->nullable()->after('fecha_ingreso');
            $table->string('motivo_baja', 255)->nullable()->after('fecha_baja');

            // El listado filtra por estado constantemente.
            $table->index('fecha_baja');
        });
    }

    public function down(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->dropIndex(['fecha_baja']);
            $table->dropColumn(['fecha_baja', 'motivo_baja']);
        });
    }
};
