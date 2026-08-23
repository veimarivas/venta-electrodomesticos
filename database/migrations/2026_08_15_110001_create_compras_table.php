<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();

            // restrictOnDelete: un proveedor con compras registradas no se
            // borra, porque dejaría el histórico de costos sin origen.
            $table->foreignId('proveedor_id')->constrained('proveedores')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->string('codigo', 30)->unique();
            $table->string('numero_factura', 60)->nullable();
            $table->date('fecha_compra');

            // Todo el dinero en decimal(12,2): nunca float.
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('impuesto', 12, 2)->default(0);
            $table->decimal('flete', 12, 2)->default(0);
            $table->decimal('otros_gastos', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->char('moneda', 3)->default('BOB');
            $table->decimal('tipo_cambio', 12, 6)->default(1);

            // borrador: se puede editar. recepcionada: ya generó unidades, se congela.
            $table->enum('estado', ['borrador', 'recepcionada', 'anulada'])->default('borrador');
            $table->timestamp('recepcionada_en')->nullable();

            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'fecha_compra']);
            $table->index('proveedor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
