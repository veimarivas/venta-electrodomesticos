<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un gasto de la tienda que no es mercadería: la comida del personal, un
 * flete, un servicio. Entra en el resumen del día con su método de pago —casi
 * todo se paga por QR— y para quién fue.
 *
 * Se archiva, no se borra: el resumen de un día ya cerrado tiene que poder
 * explicarse después.
 */
#[Fillable([
    'fecha',
    'concepto',
    'categoria',
    'monto',
    'metodo_pago',
    'beneficiario_id',
    'caja_id',
    'comprobante',
    'notas',
    'user_id',
])]
class Gasto extends Model
{
    /** @use HasFactory<\Database\Factories\GastoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'gastos';

    /** Categorías, con su etiqueta. «Otros» cubre lo que no encaja. */
    public const CATEGORIAS = [
        'comida' => 'Comida',
        'transporte' => 'Transporte y fletes',
        'servicios' => 'Servicios (luz, internet, alquiler)',
        'insumos' => 'Insumos y limpieza',
        'personal' => 'Pagos al personal',
        'otros' => 'Otros',
    ];

    /** Cómo se pagó. */
    public const METODOS = [
        'qr' => 'QR',
        'efectivo' => 'Efectivo',
        'transferencia' => 'Transferencia',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
            'beneficiario_id' => 'integer',
            'caja_id' => 'integer',
        ];
    }

    /** Para quién fue el gasto (un vendedor, un administrador). */
    public function beneficiario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiario_id');
    }

    /** Quién lo anotó. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function scopeDelDia(Builder $query, \DateTimeInterface|string $fecha): Builder
    {
        return $query->whereDate('fecha', $fecha);
    }

    public function getComprobanteUrlAttribute(): ?string
    {
        return $this->comprobante ? asset('storage/'.$this->comprobante) : null;
    }
}
