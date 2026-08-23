<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kardex: la historia de cada unidad física.
     *
     * Es una tabla de SOLO ESCRITURA — se agregan filas, nunca se editan ni se
     * borran. Por eso lleva `created_at` y no `updated_at`: un movimiento que
     * se puede modificar deja de servir como auditoría.
     *
     * `cantidad` es siempre 1 porque el inventario está serializado: cada fila
     * de `unidades` es un aparato concreto. La columna se conserva igualmente
     * para que los reportes puedan sumar sin casos especiales.
     */
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();

            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();

            $table->enum('tipo', [
                'entrada', 'salida', 'ajuste', 'devolucion', 'dano', 'traspaso',
            ]);

            // Estado antes y después. En un inventario serializado lo que se
            // mueve no es una cantidad sino el estado del aparato, así que el
            // kardex sería ilegible sin estas dos columnas.
            $table->string('estado_anterior', 20)->nullable();
            $table->string('estado_nuevo', 20);

            // De dónde viene el movimiento: una compra, una venta, un ajuste
            // manual… Es polimórfico y nullable porque no todo movimiento
            // tiene un documento detrás.
            $table->nullableMorphs('origen');

            // Quién lo hizo. nullOnDelete: si algún día se borra el usuario,
            // el movimiento sigue existiendo — es un registro de auditoría.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedInteger('cantidad')->default(1);
            $table->text('notas')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['unidad_id', 'created_at']);
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
