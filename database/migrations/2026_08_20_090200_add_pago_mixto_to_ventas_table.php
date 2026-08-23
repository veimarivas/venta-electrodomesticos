<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cómo se cobró la venta, no solo con qué.
     *
     * Hasta ahora `metodo_pago` era una etiqueta suelta. Con el pago mixto
     * (parte en efectivo, parte por QR) hay que guardar el reparto: sin él, la
     * caja del día no cuadra contra el extracto del banco. `monto_efectivo` y
     * `monto_qr` se llenan siempre, también en los métodos puros, para que
     * cualquier reporte sume una sola columna sin condicionales.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // QR que se mostró al cliente. restrictOnDelete + softDeletes en
            // qrs_cobro: el respaldo no puede quedar apuntando a la nada.
            $table->foreignId('qr_cobro_id')->nullable()->after('metodo_pago')
                ->constrained('qrs_cobro')->restrictOnDelete();

            // Dinero como decimal:2, nunca float (ver §9).
            $table->decimal('monto_efectivo', 12, 2)->default(0)->after('qr_cobro_id');
            $table->decimal('monto_qr', 12, 2)->default(0)->after('monto_efectivo');

            // Captura del comprobante del banco, subida al cobrar.
            $table->string('comprobante_qr', 255)->nullable()->after('monto_qr');
        });

        // El enum se amplía a mano: Laravel no sabe alterar un ENUM sin
        // doctrine/dbal, y cambiarlo por string obligaría a reescribir las
        // validaciones que hoy se apoyan en él.
        DB::statement(
            "ALTER TABLE ventas MODIFY metodo_pago ".
            "ENUM('efectivo','tarjeta','transferencia','qr','mixto') NOT NULL DEFAULT 'efectivo'"
        );

        // Las ventas ya registradas se cobraron enteras por su método: se les
        // reparte el total para que las columnas nuevas no queden en cero y
        // descuadren el arqueo del histórico.
        DB::table('ventas')->where('metodo_pago', 'qr')->update(['monto_qr' => DB::raw('total')]);
        DB::table('ventas')->where('metodo_pago', '!=', 'qr')->update(['monto_efectivo' => DB::raw('total')]);
    }

    public function down(): void
    {
        DB::table('ventas')->where('metodo_pago', 'mixto')->update(['metodo_pago' => 'efectivo']);

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('qr_cobro_id');
            $table->dropColumn(['monto_efectivo', 'monto_qr', 'comprobante_qr']);
        });

        DB::statement(
            "ALTER TABLE ventas MODIFY metodo_pago ".
            "ENUM('efectivo','tarjeta','transferencia','qr') NOT NULL DEFAULT 'efectivo'"
        );
    }
};
