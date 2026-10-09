<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gastos de la tienda que no son compras de mercadería: la comida del
     * personal, un flete, el internet, insumos.
     *
     * Se guarda **cómo se pagó** —en la tienda casi todo se paga por QR— y
     * **para quién** (un vendedor, un administrador), que es lo que pide el
     * resumen del día. Un gasto en efectivo pagado con el dinero del cajón
     * queda atado al turno (`caja_id`) y sale del esperado del arqueo, como un
     * retiro: si no, el cierre daría un faltante que no es tal.
     */
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('concepto', 160);
            $table->string('categoria', 30);
            $table->decimal('monto', 12, 2);
            $table->string('metodo_pago', 20);
            // Para quién fue el gasto: la comida de un vendedor, el pasaje del
            // administrador. Nulo = gasto de la tienda en general.
            $table->foreignId('beneficiario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();
            $table->string('comprobante')->nullable();
            $table->string('notas', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fecha', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
