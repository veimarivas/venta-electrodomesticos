<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tope de rebaja que el mostrador puede aplicar a este producto.
     *
     * Se guarda en Bs (monto fijo), no en porcentaje: la tienda negocia
     * "hasta 50 Bs menos", no "hasta un 8%". Por defecto 0 — sin autorización
     * expresa en la ficha del producto, el cajero cobra el precio de lista.
     */
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Dinero como decimal:2, nunca float (ver §9).
            $table->decimal('descuento_maximo', 12, 2)->default(0)->after('precio_venta');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('descuento_maximo');
        });
    }
};
