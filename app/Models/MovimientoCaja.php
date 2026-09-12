<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un movimiento de efectivo dentro de un turno de caja.
 *
 * Es la memoria de lo que salió del cajón para pagar un flete o entró como
 * ingreso, para que el arqueo no tenga que adivinar por qué descuadra.
 *
 * Solo escritura: no se edita ni se borra. Un movimiento corregible dejaría de
 * servir para cuadrar. Por eso no tiene `updated_at`.
 */
#[Fillable([
    'caja_id',
    'user_id',
    'tipo',
    'monto',
    'motivo',
])]
class MovimientoCaja extends Model
{
    // Laravel pluralizaría a "movimiento_cajas".
    protected $table = 'movimientos_caja';

    /** Solo se escribe una vez: no hay updated_at que mantener. */
    public const UPDATED_AT = null;

    /** Tipos de movimiento y su etiqueta en español. */
    public const TIPOS = [
        'ingreso' => 'Ingreso',
        'retiro' => 'Retiro',
    ];

    protected function casts(): array
    {
        return [
            'caja_id' => 'integer',
            'user_id' => 'integer',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'monto' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeIngresos(Builder $query): Builder
    {
        return $query->where('tipo', 'ingreso');
    }

    public function scopeRetiros(Builder $query): Builder
    {
        return $query->where('tipo', 'retiro');
    }
}
