<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devolución de aparatos sueltos, sin anular la venta entera.
 *
 * Hasta ahora solo se podía deshacer una venta completa. Para devolver un
 * aparato de una venta de tres había que anularlo todo y volver a cobrar, lo
 * que ensucia los reportes y descuadra las comisiones.
 *
 * La devolución se marca **en la línea**, que es donde vive la verdad: cada
 * `venta_detalles` es un aparato concreto. En la cabecera solo se guarda el
 * acumulado devuelto, para no tener que sumar las líneas cada vez que se pinta
 * una venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_detalles', function (Blueprint $tabla): void {
            // Nulo = la línea sigue vendida. Es la única marca que decide si un
            // aparato cuenta en los totales de la venta.
            $tabla->timestamp('devuelto_en')->nullable()->after('ganancia');
            $tabla->string('motivo_devolucion', 255)->nullable()->after('devuelto_en');

            // Los listados filtran por «lo que sigue vendido» en cada venta.
            $tabla->index(['venta_id', 'devuelto_en']);
        });

        Schema::table('ventas', function (Blueprint $tabla): void {
            // Cuánto se ha devuelto de esta venta, a precio pactado.
            //
            // `total` pasa a ser el NETO —lo que la venta vale ahora— para que
            // los reportes sigan sumando sin tocar ni una consulta. El importe
            // original se reconstruye con `total + total_devuelto`, y por eso
            // se guarda: sin él, una devolución borraría el rastro de por
            // cuánto se vendió en su día.
            $tabla->decimal('total_devuelto', 12, 2)->default(0)->after('total');
            $tabla->timestamp('primera_devolucion_en')->nullable()->after('anulada_en');
        });
    }

    public function down(): void
    {
        Schema::table('venta_detalles', function (Blueprint $tabla): void {
            $tabla->dropIndex(['venta_id', 'devuelto_en']);
            $tabla->dropColumn(['devuelto_en', 'motivo_devolucion']);
        });

        Schema::table('ventas', function (Blueprint $tabla): void {
            $tabla->dropColumn(['total_devuelto', 'primera_devolucion_en']);
        });
    }
};
