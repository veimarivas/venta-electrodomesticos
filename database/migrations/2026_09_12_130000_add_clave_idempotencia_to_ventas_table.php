<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clave de idempotencia del cobro.
 *
 * El teléfono la manda al cobrar. Si la respuesta se pierde por un corte de
 * red, el cajero reintenta con la MISMA clave y el servidor devuelve la venta
 * que ya existía en vez de registrarla dos veces. El índice único es la
 * garantía: la comprobación en PHP sola no frena dos peticiones simultáneas.
 *
 * Es nullable a propósito: las ventas que no vienen del teléfono (el panel, los
 * seeders) no la llevan, y MySQL admite muchos NULL en un índice único.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('clave_idempotencia', 64)->nullable()->unique()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique(['clave_idempotencia']);
            $table->dropColumn('clave_idempotencia');
        });
    }
};
