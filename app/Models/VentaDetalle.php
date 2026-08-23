<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de la venta: exactamente una unidad física.
 *
 * No hay columna `cantidad` porque el inventario está serializado.
 *
 * `unidad_vendida_id` es la guardia de la doble venta: copia de `unidad_id`
 * mientras la venta está viva, y NULL al anularla. Su índice único impide que
 * un aparato esté en dos ventas completadas a la vez, pero deja revenderlo si
 * la anterior se anuló y volvió al stock.
 */
#[Fillable([
    'venta_id',
    'unidad_id',
    'unidad_vendida_id',
    'producto_id',
    'precio_unitario',
    'costo_unitario',
    'descuento',
    'ganancia',
])]
class VentaDetalle extends Model
{
    /** @use HasFactory<\Database\Factories\VentaDetalleFactory> */
    use HasFactory;

    protected $table = 'venta_detalles';

    protected function casts(): array
    {
        return [
            'venta_id' => 'integer',
            'unidad_id' => 'integer',
            'unidad_vendida_id' => 'integer',
            'producto_id' => 'integer',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'precio_unitario' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'descuento' => 'decimal:2',
            'ganancia' => 'decimal:2',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
