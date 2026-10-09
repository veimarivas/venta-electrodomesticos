<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El precio de lista de cada aparato en el momento de venderlo.
     *
     * Desde que se puede cobrar por encima de la lista, la línea guarda lo
     * cobrado como `precio_unitario` y descuento cero, y con eso se pierde
     * contra qué se comparó. El seguimiento por vendedor necesita las dos
     * cosas: cuánto rebajó y cuánto cobró de más.
     *
     * Las líneas viejas se rellenan con su `precio_unitario`, que hasta ahora
     * era siempre la lista (no se podía cobrar por encima).
     */
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->decimal('precio_lista', 12, 2)->nullable()->after('precio_unitario');
        });

        DB::table('venta_detalles')->whereNull('precio_lista')->update([
            'precio_lista' => DB::raw('precio_unitario'),
        ]);
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $table) {
            $table->dropColumn('precio_lista');
        });
    }
};
