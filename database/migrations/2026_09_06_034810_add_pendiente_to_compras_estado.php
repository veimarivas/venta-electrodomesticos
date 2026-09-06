<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nueva compra nace en «pendiente»: la mercadería se compró pero todavía no
     * se verificó, y sus unidades NO entran al stock hasta recepcionarla.
     * `borrador` se conserva para los registros viejos que no se recepcionaron.
     */
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->enum('estado', ['borrador', 'pendiente', 'recepcionada', 'anulada'])
                ->default('pendiente')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->enum('estado', ['borrador', 'recepcionada', 'anulada'])
                ->default('borrador')
                ->change();
        });
    }
};