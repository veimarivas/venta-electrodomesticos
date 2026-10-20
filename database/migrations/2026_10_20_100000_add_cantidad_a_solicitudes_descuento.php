<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Autorizaciones de descuento por producto y cantidad.
 *
 * Antes cada solicitud iba atada a un aparato: vender tres iguales a un precio
 * especial eran tres avisos al administrador, y con la venta por cantidad de
 * los productos con serial el aparato ni siquiera se conoce al pactar el precio
 * (se escanea al confirmar). Ahora una solicitud puede pedir «N unidades de
 * este producto a este precio»: `unidad_id` queda nulo, `cantidad` dice
 * cuántas cubre y `usadas` cuántas ya salieron por caja.
 *
 * Las solicitudes de siempre (un aparato) siguen igual: cantidad 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_descuento', function (Blueprint $tabla): void {
            $tabla->foreignId('unidad_id')->nullable()->change();

            $tabla->unsignedSmallInteger('cantidad')->default(1)->after('producto_id');
            $tabla->unsignedSmallInteger('usadas')->default(0)->after('cantidad');

            // Al cobrar se buscan las aprobadas del producto y del vendedor.
            $tabla->index(['producto_id', 'user_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_descuento', function (Blueprint $tabla): void {
            $tabla->dropIndex(['producto_id', 'user_id', 'estado']);
            $tabla->dropColumn(['cantidad', 'usadas']);
        });
    }
};
