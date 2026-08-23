<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una línea de la compra: N unidades del mismo producto a un mismo costo.
 */
#[Fillable([
    'compra_id',
    'producto_id',
    'cantidad',
    'costo_unitario',
    'subtotal',
    'costo_real_unitario',
    'precio_venta',
])]
class CompraDetalle extends Model
{
    /** @use HasFactory<\Database\Factories\CompraDetalleFactory> */
    use HasFactory;

    protected $table = 'compra_detalles';

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'costo_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'costo_real_unitario' => 'decimal:2',
            'precio_venta' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Unidades físicas que generó esta línea al recepcionarse.
     */
    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class, 'compra_detalle_id');
    }
}
