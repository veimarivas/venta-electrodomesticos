<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entrega a domicilio e instalación.
 *
 * Un refrigerador no sale de la tienda en la mano del cliente. Entre cobrar y
 * entregar hay días, una dirección, alguien que lo lleva y un cliente que
 * llama preguntando — y todo eso vivía en la memoria de quien atiende.
 *
 * Dos tablas: la orden de entrega y qué aparatos van en ella.
 *
 * **No se toca el estado de la unidad.** Un aparato vendido y aún en el
 * almacén sigue estando `vendido`: ya salió del stock vendible el día que se
 * cobró. Inventarle un estado `por_entregar` obligaría a que todas las
 * consultas de stock lo conocieran, y la pregunta que responde —¿dónde está
 * físicamente?— la contesta esta tabla, que es su sitio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entregas', function (Blueprint $tabla): void {
            $tabla->id();

            // Sin código propio a propósito: en el mostrador una entrega se
            // nombra por su venta («la entrega de la VTA-2026-000123»), y un
            // correlativo más sería un número que nadie usa.
            $tabla->foreignId('venta_id')->constrained('ventas');

            /**
             * El destinatario, repetido aquí como en `creditos`.
             *
             * Fija a quién se le prometió la entrega. La venta puede tener
             * cliente nulo —venta al público— y aun así hacer falta llevar el
             * aparato a algún sitio, por eso es nullable.
             */
            $tabla->foreignId('cliente_id')->nullable()->constrained('clientes');

            /** Dónde se lleva. Es el dato que justifica la tabla entera. */
            $tabla->string('direccion', 255);

            /** «Portón verde, frente a la cancha». Lo que evita la llamada. */
            $tabla->string('referencia', 255)->nullable();

            /**
             * A quién llamar al llegar. Se copia y no se lee del cliente: el
             * que recibe puede ser otro —la hija, el portero— y su número no
             * tiene por qué acabar en la ficha del cliente.
             */
            $tabla->string('telefono_contacto', 30)->nullable();

            /** El día que se quedó con el cliente. Nulo: «cuando se pueda». */
            $tabla->date('programada_para')->nullable();

            /**
             * pendiente → en_ruta → entregada
             *                    ↘ fallida → (reprogramar) → pendiente
             * pendiente/en_ruta → cancelada
             */
            $tabla->string('estado', 20)->default('pendiente');

            /** ¿Además de dejarlo, hay que instalarlo? */
            $tabla->boolean('con_instalacion')->default(false);

            $tabla->foreignId('repartidor_id')->nullable()->constrained('users');

            $tabla->timestamp('salio_en')->nullable();
            $tabla->timestamp('entregada_en')->nullable();
            $tabla->timestamp('instalada_en')->nullable();

            /** Quién firmó al recibir. Casi nunca es el titular de la venta. */
            $tabla->string('recibida_por', 120)->nullable();

            /** Por qué no se pudo entregar. Obligatorio al marcar fallida. */
            $tabla->text('motivo_fallo')->nullable();

            $tabla->foreignId('creado_por')->constrained('users');
            $tabla->text('notas')->nullable();

            $tabla->timestamps();

            // «¿Qué sale hoy?» y «¿qué está atrasado?» son las dos consultas
            // del tablero, y las dos van por estado y fecha.
            $tabla->index(['estado', 'programada_para']);
            $tabla->index('repartidor_id');
        });

        Schema::create('entrega_detalles', function (Blueprint $tabla): void {
            $tabla->id();

            $tabla->foreignId('entrega_id')->constrained('entregas')->cascadeOnDelete();

            /**
             * La línea de venta que viaja, no la unidad: así se sabe de qué
             * venta salió el aparato sin una consulta más, y una unidad
             * devuelta y revendida no confunde las dos entregas.
             */
            $tabla->foreignId('venta_detalle_id')->constrained('venta_detalles');

            /**
             * Guardia del doble reparto, calcada de `venta_detalles.
             * unidad_vendida_id`: copia de `venta_detalle_id` mientras la
             * entrega está viva y NULL cuando se cancela o se devuelve el
             * aparato.
             *
             * En MySQL los NULL no chocan entre sí, así que el índice único
             * impide que un aparato esté en dos entregas **vivas** a la vez,
             * pero deja volver a programarlo si la anterior se canceló.
             */
            $tabla->foreignId('venta_detalle_activo_id')->nullable()->unique();

            $tabla->timestamps();

            // Ni siquiera repetido dentro de la misma orden.
            $tabla->unique(['entrega_id', 'venta_detalle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_detalles');
        Schema::dropIfExists('entregas');
    }
};
