<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las columnas compra_id y compra_detalle_id se crearon sin clave foránea
     * porque la tabla de compras todavía no existía. Ahora que existe, se
     * añaden las restricciones que faltaban.
     *
     * nullOnDelete en ambas: si alguna vez se borra una compra, las unidades
     * físicas siguen existiendo en el almacén; solo pierden su origen.
     */
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->foreign('compra_id')->references('id')->on('compras')->nullOnDelete();
            $table->foreign('compra_detalle_id')->references('id')->on('compra_detalles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropForeign(['compra_id']);
            $table->dropForeign(['compra_detalle_id']);
        });
    }
};
