<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un aparato dentro de una orden de entrega.
 *
 * `venta_detalle_activo_id` es la guardia del doble reparto, calcada de
 * `venta_detalles.unidad_vendida_id`: copia de `venta_detalle_id` mientras la
 * entrega vive y NULL cuando se cancela o se devuelve el aparato. Su índice
 * único impide que un aparato esté en dos entregas vivas a la vez, pero deja
 * volver a programarlo si la anterior se canceló.
 */
#[Fillable([
    'entrega_id',
    'venta_detalle_id',
    'venta_detalle_activo_id',
])]
class EntregaDetalle extends Model
{
    /** @use HasFactory<\Database\Factories\EntregaDetalleFactory> */
    use HasFactory;

    protected $table = 'entrega_detalles';

    protected function casts(): array
    {
        return [
            'entrega_id' => 'integer',
            'venta_detalle_id' => 'integer',
            'venta_detalle_activo_id' => 'integer',
        ];
    }

    public function entrega(): BelongsTo
    {
        return $this->belongsTo(Entrega::class);
    }

    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class);
    }
}
