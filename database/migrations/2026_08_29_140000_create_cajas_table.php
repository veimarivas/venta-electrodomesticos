<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arqueo de caja: cuadrar el efectivo del turno contra lo que dicen las ventas.
 *
 * El punto de venta cobra en efectivo desde el primer día y nadie cuadraba al
 * cerrar. Sin arqueo, un faltante aparece semanas después mezclado con todo lo
 * demás y ya no se puede atribuir a un día ni a un turno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $tabla): void {
            $tabla->id();

            // Quién la abrió. El cierre puede hacerlo otra persona —un
            // supervisor cuadrando el turno de alguien que ya se fue—, por eso
            // son dos columnas.
            $tabla->foreignId('abierta_por')->constrained('users');
            $tabla->foreignId('cerrada_por')->nullable()->constrained('users');

            $tabla->timestamp('abierta_en');
            $tabla->timestamp('cerrada_en')->nullable();

            /** Con cuánto empieza el turno: el cambio que se deja en el cajón. */
            $tabla->decimal('monto_inicial', 12, 2)->default(0);

            /**
             * Lo que se contó de verdad al cerrar. Nulo mientras está abierta.
             *
             * Se guarda aparte de lo esperado a propósito: la gracia del arqueo
             * es comparar dos números que se calcularon por caminos distintos.
             */
            $tabla->decimal('monto_declarado', 12, 2)->nullable();

            /** Lo que debería haber según las ventas. Se congela al cerrar. */
            $tabla->decimal('monto_esperado', 12, 2)->nullable();

            /**
             * Declarado − esperado. Positivo sobra, negativo falta.
             *
             * Se guarda calculado y no se deduce al leer: si mañana se anula
             * una venta del turno, el arqueo tiene que seguir diciendo lo que
             * se vio esa noche.
             */
            $tabla->decimal('diferencia', 12, 2)->nullable();

            $tabla->string('estado', 20)->default('abierta');
            $tabla->text('notas')->nullable();

            $tabla->timestamps();

            // Buscar la caja abierta es la consulta más frecuente: pasa en cada
            // venta.
            $tabla->index(['estado', 'abierta_por']);
            $tabla->index('abierta_en');
        });

        Schema::table('ventas', function (Blueprint $tabla): void {
            // Nulo a propósito: hay ventas anteriores al arqueo, y el sistema
            // debe seguir vendiendo aunque nadie haya abierto caja. Un cierre
            // avisa de las ventas en efectivo que se quedaron sueltas en su
            // horario en vez de sumarlas por su cuenta.
            $tabla->foreignId('caja_id')->nullable()->after('user_id')
                ->constrained('cajas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $tabla): void {
            $tabla->dropConstrainedForeignId('caja_id');
        });

        Schema::dropIfExists('cajas');
    }
};
