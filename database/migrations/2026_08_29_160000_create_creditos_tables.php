<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Venta a crédito: el plan de cuotas y su cobranza.
 *
 * En electrodomésticos buena parte de lo que se vende se vende a plazos, y
 * hasta ahora el sistema solo entendía el pago completo en el momento: toda la
 * cartera vivía fuera, en un cuaderno. Es la diferencia entre un sistema que
 * registra lo que ya pasó y uno que dice a quién hay que llamar hoy.
 *
 * Tres tablas y ninguna columna de saldo:
 *
 *   · `creditos`      — el plan pactado con el cliente
 *   · `cuotas`        — cada vencimiento, con lo que lleva pagado
 *   · `pagos_credito` — cada imputación de dinero a una cuota
 *
 * El saldo **no se guarda**: es la suma de lo que falta en las cuotas. Una
 * columna de saldo se desincroniza el día que alguien corrige un pago a mano,
 * y a partir de ahí la cartera miente sin que nadie lo note.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creditos', function (Blueprint $tabla): void {
            $tabla->id();

            // Uno por venta. El índice único lo garantiza: dos planes sobre la
            // misma venta cobrarían dos veces el mismo aparato.
            $tabla->foreignId('venta_id')->unique()->constrained('ventas');

            /**
             * El deudor, repetido aquí a propósito.
             *
             * La venta ya sabe quién es, pero la cartera se consulta por
             * cliente («¿cuánto debe la señora Quispe?») y ese es el camino
             * corto. Además fija el deudor del día de la venta: es él quien
             * firmó, aunque después la venta cambie de manos.
             */
            $tabla->foreignId('cliente_id')->constrained('clientes');

            /** Lo que el cliente adelantó en el mostrador. Puede ser cero. */
            $tabla->decimal('cuota_inicial', 12, 2)->default(0);

            /**
             * Lo que se reparte en cuotas: total de la venta − inicial.
             *
             * Se guarda aunque parezca deducible, porque el total de la venta
             * **cambia** si después se devuelve un aparato. Deducirlo daría un
             * financiado distinto cada vez que alguien mira la ficha.
             */
            $tabla->decimal('total_financiado', 12, 2);

            $tabla->unsignedTinyInteger('numero_cuotas');

            /** Del primero salen los demás: mismo día de cada mes. */
            $tabla->date('primer_vencimiento');

            /**
             * `vigente` mientras se deba algo, `pagado` cuando no queda saldo,
             * `anulado` si la venta se anuló. Un crédito anulado no se cobra,
             * pero tampoco se borra: los pagos que ya entraron existieron.
             */
            $tabla->string('estado', 20)->default('vigente');

            $tabla->foreignId('creado_por')->constrained('users');
            $tabla->text('notas')->nullable();

            $tabla->timestamps();

            // La cartera se lista por cliente y se filtra por estado.
            $tabla->index(['estado', 'cliente_id']);
        });

        Schema::create('cuotas', function (Blueprint $tabla): void {
            $tabla->id();

            $tabla->foreignId('credito_id')->constrained('creditos')->cascadeOnDelete();

            /** 1, 2, 3… La inicial no es una cuota: se cobró con la venta. */
            $tabla->unsignedTinyInteger('numero');

            $tabla->date('vence_en');

            /**
             * Lo que toca pagar en esta cuota.
             *
             * No siempre es el mismo importe: los centavos que no dividen
             * exactos se cargan en las primeras cuotas, para que la suma dé el
             * financiado al céntimo.
             */
            $tabla->decimal('monto', 12, 2);

            $tabla->decimal('monto_pagado', 12, 2)->default(0);

            /** Cuándo se saldó. Nulo mientras deba algo. */
            $tabla->timestamp('pagada_en')->nullable();

            $tabla->timestamps();

            // No puede haber dos cuotas número 3 del mismo crédito.
            $tabla->unique(['credito_id', 'numero']);

            // «¿Qué vence esta semana?» es la consulta que justifica el módulo.
            $tabla->index(['vence_en', 'pagada_en']);
        });

        Schema::create('pagos_credito', function (Blueprint $tabla): void {
            $tabla->id();

            $tabla->foreignId('credito_id')->constrained('creditos');

            /**
             * Un pago siempre se imputa a una cuota concreta.
             *
             * Si el cliente entrega dinero que alcanza para cuota y media, se
             * guardan **dos filas** con el mismo `recibo`. Una sola fila con el
             * total dejaría sin respuesta la pregunta de qué cuota quedó
             * saldada, que es justo la que se discute en el mostrador.
             */
            $tabla->foreignId('cuota_id')->constrained('cuotas');

            /** Agrupa las filas de una misma entrega de dinero. */
            $tabla->string('recibo', 20)->index();

            /**
             * El turno en el que entró. Nulo si se cobró sin caja abierta —el
             * cierre lo enseña en vez de sumarlo por su cuenta.
             */
            $tabla->foreignId('caja_id')->nullable()->constrained('cajas')->nullOnDelete();

            $tabla->foreignId('user_id')->constrained('users');

            $tabla->decimal('monto', 12, 2);

            /** Solo lo que el mostrador acepta para cobrar una cuota. */
            $tabla->enum('metodo_pago', ['efectivo', 'qr', 'transferencia'])->default('efectivo');

            /** Respaldo del banco. Obligatorio si no fue en efectivo. */
            $tabla->string('comprobante_qr', 255)->nullable();

            $tabla->timestamp('pagado_en');
            $tabla->text('notas')->nullable();

            $tabla->timestamps();

            // El arqueo suma los pagos en efectivo del turno.
            $tabla->index(['caja_id', 'metodo_pago']);
            $tabla->index('pagado_en');
        });

        // El enum se amplía a mano: Laravel no sabe alterar un ENUM sin
        // doctrine/dbal. Mismo procedimiento que cuando entró el pago mixto.
        DB::statement(
            'ALTER TABLE ventas MODIFY metodo_pago '.
            "ENUM('efectivo','tarjeta','transferencia','qr','mixto','credito') NOT NULL DEFAULT 'efectivo'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_credito');
        Schema::dropIfExists('cuotas');
        Schema::dropIfExists('creditos');

        DB::table('ventas')->where('metodo_pago', 'credito')->update(['metodo_pago' => 'efectivo']);

        DB::statement(
            'ALTER TABLE ventas MODIFY metodo_pago '.
            "ENUM('efectivo','tarjeta','transferencia','qr','mixto') NOT NULL DEFAULT 'efectivo'"
        );
    }
};
