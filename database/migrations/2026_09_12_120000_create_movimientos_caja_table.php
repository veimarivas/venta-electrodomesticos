<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Movimientos de caja del turno: ingresos y retiros de efectivo.
 *
 * El arqueo sabía sumar lo cobrado, pero no lo que sale del cajón para pagar
 * un flete o entra como ingreso extraordinario: esos movimientos se anotaban a
 * mano en las notas del cierre y el arqueo quedaba descuadrado sin explicación.
 *
 * Tabla de SOLO ESCRITURA, como el kardex: un movimiento de caja que se puede
 * editar deja de servir para cuadrar. Por eso lleva `created_at` y no
 * `updated_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();

            // El movimiento pertenece al turno. Borrar una caja no es un flujo
            // de la aplicación, pero cascadeOnDelete evita dejar huérfanos.
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();

            // Quién lo registró. nullOnDelete: es un registro de auditoría.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('tipo', ['ingreso', 'retiro']);

            $table->decimal('monto', 12, 2);

            /** Para qué fue: «flete a Santa Cruz», «pago al proveedor». */
            $table->string('motivo');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['caja_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_caja');
    }
};
