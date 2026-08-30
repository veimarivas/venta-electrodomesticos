<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Servicio técnico: qué se hizo con el aparato que volvió.
 *
 * El sistema sabía decir si una unidad estaba en garantía y ahí terminaba.
 * Cuando el cliente volvía con una lavadora que no enciende, empezaba un
 * rastro en papel.
 *
 * La pieza es pequeña porque **el kardex ya existe**: una reparación es otro
 * tipo de movimiento sobre una unidad ya identificada por serial, y el estado
 * `garantia` de `unidades` ya estaba reservado para esto —la API lo describe
 * desde el primer día como «salió a reparación y no es vendible mientras
 * tanto»—. No hace falta ni una columna nueva en `unidades`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reparaciones', function (Blueprint $tabla): void {
            $tabla->id();

            /**
             * Aquí sí hay código propio, al revés que en las entregas: el
             * cliente se va sin su aparato y con un papel en la mano, y ese
             * papel necesita un número con el que volver.
             */
            $tabla->string('codigo', 20)->unique();

            $tabla->foreignId('unidad_id')->constrained('unidades');

            /**
             * De qué venta salió. Nulo si el aparato nunca se vendió —una
             * unidad de stock que llegó fallada del proveedor también pasa por
             * el taller—.
             */
            $tabla->foreignId('venta_id')->nullable()->constrained('ventas');
            $tabla->foreignId('cliente_id')->nullable()->constrained('clientes');

            /**
             * Si entró cubierta por la garantía. **Se congela al recibirla.**
             *
             * Se calcula una vez y se guarda porque la cobertura depende de
             * `productos.meses_garantia`, que alguien puede cambiar mañana. Si
             * se dedujera al leer, una reparación aceptada como garantía
             * aparecería meses después como cobrable, y al revés.
             */
            $tabla->boolean('en_garantia')->default(false);

            /** Hasta cuándo estaba cubierta el día que entró. */
            $tabla->date('garantia_hasta')->nullable();

            /** Lo que dice el cliente: «no enciende», «hace un ruido». */
            $tabla->text('falla_reportada');

            /** Lo que encontró el técnico. */
            $tabla->text('diagnostico')->nullable();

            /** Lo que se hizo. */
            $tabla->text('trabajo_realizado')->nullable();

            /**
             * recibida → en_reparacion ⇄ esperando_repuesto → lista → entregada
             *                                              ↘ irreparable
             * cualquiera abierta → cancelada
             */
            $tabla->string('estado', 25)->default('recibida');

            /**
             * Lo que se le cobra. En garantía es cero, pero se guarda igual
             * para que un reporte pueda sumar una sola columna sin saber de
             * garantías.
             */
            $tabla->decimal('costo', 12, 2)->default(0);

            $tabla->foreignId('tecnico_id')->nullable()->constrained('users');

            /** Lo que se le prometió al cliente. Es lo que se incumple. */
            $tabla->date('prometida_para')->nullable();

            $tabla->timestamp('recibida_en');
            $tabla->timestamp('lista_en')->nullable();
            $tabla->timestamp('entregada_en')->nullable();

            /** Quién se la llevó de vuelta. */
            $tabla->string('entregada_a', 120)->nullable();

            /**
             * En qué estado estaba la unidad antes de entrar al taller, para
             * devolverla ahí al salir.
             *
             * No es lo mismo un aparato vendido que vuelve por garantía que
             * uno de stock que llegó fallado del proveedor: al primero hay que
             * devolverle su `vendido` y al segundo su `en_stock`. Sin esta
             * columna habría que adivinarlo, y adivinar mal devuelve al
             * catálogo un aparato que ya tiene dueño.
             */
            $tabla->string('estado_unidad_origen', 20);

            $tabla->foreignId('recibida_por')->constrained('users');
            $tabla->text('notas')->nullable();

            $tabla->timestamps();

            // El tablero pregunta qué está abierto y qué se prometió para hoy.
            $tabla->index(['estado', 'prometida_para']);
            // «¿Qué historial tiene este aparato?» va por unidad.
            $tabla->index(['unidad_id', 'recibida_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reparaciones');
    }
};
