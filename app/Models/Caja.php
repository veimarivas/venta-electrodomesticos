<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un turno de caja: desde que se abre con su fondo hasta que se cuenta y cierra.
 *
 * Una caja cerrada es un hecho consumado, como una venta: no se edita ni se
 * borra. Si al día siguiente se anula una venta del turno, el arqueo sigue
 * diciendo lo que se vio esa noche —que es justo lo que hace útil un arqueo—.
 */
#[Fillable([
    'abierta_por',
    'cerrada_por',
    'abierta_en',
    'cerrada_en',
    'monto_inicial',
    'monto_declarado',
    'monto_esperado',
    'diferencia',
    'estado',
    'notas',
])]
class Caja extends Model
{
    /** @use HasFactory<\Database\Factories\CajaFactory> */
    use HasFactory;

    protected $table = 'cajas';

    public const ESTADOS = [
        'abierta' => 'Abierta',
        'cerrada' => 'Cerrada',
    ];

    protected function casts(): array
    {
        return [
            'abierta_por' => 'integer',
            'cerrada_por' => 'integer',
            'abierta_en' => 'datetime',
            'cerrada_en' => 'datetime',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'monto_inicial' => 'decimal:2',
            'monto_declarado' => 'decimal:2',
            'monto_esperado' => 'decimal:2',
            'diferencia' => 'decimal:2',
        ];
    }

    public function abiertaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abierta_por');
    }

    public function cerradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrada_por');
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    protected function estaAbierta(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'abierta');
    }

    /**
     * ¿El arqueo cuadró?
     *
     * Se compara contra cero exacto porque el dinero se lleva en centavos
     * enteros: un céntimo de diferencia es un céntimo que falta, no un error
     * de redondeo.
     */
    protected function cuadra(): Attribute
    {
        return Attribute::get(fn (): bool => (float) $this->diferencia === 0.0);
    }

    protected function sobra(): Attribute
    {
        return Attribute::get(fn (): bool => (float) $this->diferencia > 0);
    }

    protected function falta(): Attribute
    {
        return Attribute::get(fn (): bool => (float) $this->diferencia < 0);
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->where('estado', 'abierta');
    }

    public function scopeCerradas(Builder $query): Builder
    {
        return $query->where('estado', 'cerrada');
    }
}
