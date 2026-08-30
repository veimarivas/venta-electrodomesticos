<?php

namespace App\Models;

use App\Support\ProrrateoDeGastos;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un vencimiento del plan: cuánto se debe pagar y cuándo.
 *
 * El estado no se guarda, se deduce de lo pagado. Guardarlo obligaría a
 * recordar actualizarlo en cada camino que toca el dinero —el cobro, la
 * corrección, la devolución— y basta olvidarse en uno para que la cartera
 * empiece a mentir.
 */
#[Fillable([
    'credito_id',
    'numero',
    'vence_en',
    'monto',
    'monto_pagado',
    'pagada_en',
])]
class Cuota extends Model
{
    /** @use HasFactory<\Database\Factories\CuotaFactory> */
    use HasFactory;

    protected $table = 'cuotas';

    protected function casts(): array
    {
        return [
            'credito_id' => 'integer',
            'numero' => 'integer',
            'vence_en' => 'date',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'monto' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
            'pagada_en' => 'datetime',
        ];
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(Credito::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoCredito::class);
    }

    public function montoEnCentavos(): int
    {
        return ProrrateoDeGastos::aCentavos($this->monto);
    }

    public function pagadoEnCentavos(): int
    {
        return ProrrateoDeGastos::aCentavos($this->monto_pagado);
    }

    /** Lo que falta para saldarla. Nunca negativo. */
    public function faltaEnCentavos(): int
    {
        return max(0, $this->montoEnCentavos() - $this->pagadoEnCentavos());
    }

    protected function falta(): Attribute
    {
        return Attribute::get(fn (): string => ProrrateoDeGastos::aDecimal($this->faltaEnCentavos()));
    }

    protected function estaPagada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->faltaEnCentavos() === 0);
    }

    /** Debe algo y ya pasó su fecha. */
    protected function estaVencida(): Attribute
    {
        return Attribute::get(fn (): bool => ! $this->esta_pagada && $this->vence_en->isPast());
    }

    /** Etiqueta para el listado: es lo que se lee de un vistazo. */
    protected function etiquetaEstado(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->esta_pagada) {
                return 'Pagada';
            }

            if ($this->esta_vencida) {
                return 'Vencida';
            }

            return $this->pagadoEnCentavos() > 0 ? 'Pago parcial' : 'Pendiente';
        });
    }

    /**
     * Cuotas que todavía deben algo.
     *
     * La comparación va en SQL contra las columnas, no contra un estado
     * guardado: así no hay dos verdades sobre la misma cuota.
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereColumn('monto_pagado', '<', 'monto');
    }

    public function scopeVencidas(Builder $query): Builder
    {
        return $query->pendientes()->whereDate('vence_en', '<', today());
    }
}
