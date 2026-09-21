<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * El precio de venta de un producto en una fecha concreta.
 *
 * Es el historial: cada jornada deja su precio. El más reciente es el que el
 * punto de venta ofrece; los anteriores quedan para consultar cómo se movió.
 */
#[Fillable([
    'producto_id',
    'user_id',
    'fecha',
    'precio_venta',
    'costo_referencia',
    'notas',
])]
class PrecioProducto extends Model
{
    protected $table = 'precios_producto';

    protected function casts(): array
    {
        return [
            'producto_id' => 'integer',
            'user_id' => 'integer',
            'fecha' => 'date',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'precio_venta' => 'decimal:2',
            'costo_referencia' => 'decimal:2',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDeFecha(Builder $query, \DateTimeInterface|string $fecha): Builder
    {
        return $query->whereDate('fecha', $fecha);
    }
}
