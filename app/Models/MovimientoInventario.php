<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Una línea del kardex: qué le pasó a una unidad física, cuándo y por qué.
 *
 * Tabla de solo escritura. No hay update() ni delete() en ningún flujo de la
 * aplicación: si un movimiento fuera editable dejaría de servir de auditoría.
 * Por eso no tiene `updated_at`.
 */
#[Fillable([
    'unidad_id',
    'tipo',
    'estado_anterior',
    'estado_nuevo',
    'origen_type',
    'origen_id',
    'user_id',
    'cantidad',
    'notas',
])]
class MovimientoInventario extends Model
{
    /** @use HasFactory<\Database\Factories\MovimientoInventarioFactory> */
    use HasFactory;

    // Laravel pluralizaría a "movimiento_inventarios".
    protected $table = 'movimientos_inventario';

    /** Solo se escribe una vez: no hay updated_at que mantener. */
    public const UPDATED_AT = null;

    /** Tipos de movimiento y su etiqueta en español. */
    public const TIPOS = [
        'entrada' => 'Entrada',
        'salida' => 'Salida',
        'ajuste' => 'Ajuste',
        'devolucion' => 'Devolución',
        'dano' => 'Daño',
        'traspaso' => 'Traspaso',
    ];

    protected function casts(): array
    {
        return [
            'unidad_id' => 'integer',
            'user_id' => 'integer',
            'cantidad' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** Quién registró el movimiento. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Documento que originó el movimiento (una compra, una venta…). Puede ser
     * null: un ajuste manual no tiene documento detrás.
     */
    public function origen(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeDeTipo(Builder $query, ?string $tipo): Builder
    {
        return $tipo === null || $tipo === '' ? $query : $query->where('tipo', $tipo);
    }
}
