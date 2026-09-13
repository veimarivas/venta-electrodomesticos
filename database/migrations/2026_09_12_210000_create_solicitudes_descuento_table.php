<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solicitudes de autorización para vender por debajo del mínimo del producto.
 *
 * El vendedor puede rebajar hasta el tope del producto por su cuenta. Si quiere
 * bajar más —pero sin llegar por debajo del costo— tiene que pedir permiso. La
 * solicitud guarda una **foto** del momento: precio de lista, tope, costo y el
 * precio pedido. Congelarlos es lo que permite auditar después por qué se
 * autorizó una rebaja aunque el catálogo haya cambiado.
 *
 * `precio_aprobado` es el piso que autorizó el administrador; puede ser el
 * mismo importe pedido (aprobó) o uno distinto (sugirió). Al cobrar, la venta
 * solo puede usar la solicitud si el precio no queda por debajo de ese piso.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_descuento', function (Blueprint $tabla): void {
            $tabla->id();

            $tabla->foreignId('unidad_id')->constrained('unidades')->restrictOnDelete();
            $tabla->foreignId('producto_id')->constrained('productos')->restrictOnDelete();

            // Quién la pide. Se conserva aunque el usuario se borre suave: la
            // solicitud es un registro de auditoría.
            $tabla->foreignId('user_id')->constrained('users')->restrictOnDelete();

            // Foto del momento de la solicitud.
            $tabla->decimal('precio_lista', 12, 2);
            $tabla->decimal('descuento_maximo', 12, 2);
            $tabla->decimal('costo_unitario', 12, 2);
            $tabla->decimal('precio_solicitado', 12, 2);

            // pendiente | aprobada | rechazada | consumida | cancelada
            $tabla->string('estado', 20)->default('pendiente');

            $tabla->decimal('precio_aprobado', 12, 2)->nullable();
            $tabla->foreignId('resuelto_por')->nullable()->constrained('users')->nullOnDelete();
            $tabla->timestamp('resuelto_en')->nullable();
            $tabla->text('motivo')->nullable();

            // La venta que la usó. Una autorización se gasta al venderse: no
            // vale para dos aparatos ni para dos ventas.
            $tabla->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $tabla->foreignId('venta_detalle_id')->nullable()->constrained('venta_detalles')->nullOnDelete();

            $tabla->timestamps();

            // El cajero consulta sus pendientes por unidad; el administrador,
            // las pendientes de todos.
            $tabla->index(['estado', 'created_at']);
            $tabla->index(['unidad_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_descuento');
    }
};
