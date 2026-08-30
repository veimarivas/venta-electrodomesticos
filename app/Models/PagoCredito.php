<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dinero imputado a una cuota.
 *
 * Una entrega que alcanza para cuota y media son **dos filas** con el mismo
 * `recibo`. Una sola fila con el total dejaría sin respuesta qué cuota quedó
 * saldada, que es justo lo que se discute en el mostrador.
 */
#[Fillable([
    'credito_id',
    'cuota_id',
    'recibo',
    'caja_id',
    'user_id',
    'monto',
    'metodo_pago',
    'comprobante_qr',
    'pagado_en',
    'notas',
])]
class PagoCredito extends Model
{
    /** @use HasFactory<\Database\Factories\PagoCreditoFactory> */
    use HasFactory;

    protected $table = 'pagos_credito';

    /** Con qué se puede cobrar una cuota. */
    public const METODOS_PAGO = [
        'efectivo' => 'Efectivo',
        'qr' => 'QR',
        'transferencia' => 'Transferencia',
    ];

    /** Los que exigen respaldo del banco: no entran al cajón. */
    public const METODOS_CON_RESPALDO = ['qr', 'transferencia'];

    protected function casts(): array
    {
        return [
            'credito_id' => 'integer',
            'cuota_id' => 'integer',
            'caja_id' => 'integer',
            'user_id' => 'integer',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'monto' => 'decimal:2',
            'pagado_en' => 'datetime',
        ];
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class);
    }

    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class);
    }

    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeEnEfectivo(Builder $query): Builder
    {
        return $query->where('metodo_pago', 'efectivo');
    }
}
