<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cabecera de la venta.
     *
     * Las ventas NUNCA se borran: se anulan (`estado = anulada` + fecha y
     * motivo), igual que la baja de trabajadores. El histórico y los reportes
     * tienen que seguir cuadrando, y las unidades vendidas apuntan aquí.
     */
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();

            // Nullable: la venta al público sin datos es lo habitual en tienda.
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->restrictOnDelete();

            // Quién vendió. restrictOnDelete: una venta sin vendedor dejaría
            // los reportes por usuario sin poder cuadrar.
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->string('codigo', 30)->unique();
            $table->dateTime('vendida_en');

            // Dinero como decimal:2, nunca float (ver §9).
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Se congelan al vender: si mañana cambia el costo del producto,
            // la ganancia histórica no debe moverse.
            $table->decimal('costo_total', 12, 2)->default(0);
            $table->decimal('ganancia', 12, 2)->default(0);

            $table->enum('metodo_pago', ['efectivo', 'tarjeta', 'transferencia', 'qr'])->default('efectivo');
            $table->enum('estado', ['completada', 'anulada'])->default('completada');

            $table->dateTime('anulada_en')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->text('notas')->nullable();

            $table->timestamps();

            $table->index('vendida_en');
            $table->index(['estado', 'vendida_en']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
