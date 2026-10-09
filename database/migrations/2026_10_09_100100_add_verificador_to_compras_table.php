<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quién verifica la mercadería de una compra.
     *
     * La compra la registra el administrador, pero la caja la abre quien está
     * en el almacén. Asignarla le deja ver **esa** compra y verificarla sin
     * darle acceso a las demás ni a sus costos.
     */
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->foreignId('verificador_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('asignada_en')->nullable()->after('verificador_id');

            $table->index(['verificador_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropIndex(['verificador_id', 'estado']);
            $table->dropConstrainedForeignId('verificador_id');
            $table->dropColumn('asignada_en');
        });
    }
};
