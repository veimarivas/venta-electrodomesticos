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
 * El plan de cuotas pactado sobre una venta.
 *
 * No guarda saldo: el saldo es lo que falta en las cuotas. Una columna de
 * saldo se desincroniza el día que alguien corrige un pago a mano, y a partir
 * de ahí la cartera miente sin que nadie lo note.
 */
#[Fillable([
    'venta_id',
    'cliente_id',
    'cuota_inicial',
    'total_financiado',
    'numero_cuotas',
    'primer_vencimiento',
    'estado',
    'creado_por',
    'notas',
])]
class Credito extends Model
{
    /** @use HasFactory<\Database\Factories\CreditoFactory> */
    use HasFactory;

    protected $table = 'creditos';

    public const ESTADOS = [
        'vigente' => 'Vigente',
        'pagado' => 'Pagado',
        'anulado' => 'Anulado',
    ];

    protected function casts(): array
    {
        return [
            'venta_id' => 'integer',
            'cliente_id' => 'integer',
            'numero_cuotas' => 'integer',
            'creado_por' => 'integer',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'cuota_inicial' => 'decimal:2',
            'total_financiado' => 'decimal:2',
            'primer_vencimiento' => 'date',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** De la más antigua a la más nueva: es el orden en que se cobran. */
    public function cuotas(): HasMany
    {
        return $this->hasMany(Cuota::class)->orderBy('numero');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(PagoCredito::class)->latest('pagado_en')->latest('id');
    }

    protected function estaVigente(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'vigente');
    }

    /**
     * Lo que falta por cobrar, en centavos.
     *
     * Necesita las cuotas cargadas. El proyecto corre con
     * `Model::shouldBeStrict()`, así que se piden con `loadMissing` en vez de
     * confiar en que el llamante las trajo.
     */
    public function saldoEnCentavos(): int
    {
        $this->loadMissing('cuotas');

        return $this->cuotas->reduce(
            fn (int $suma, Cuota $cuota): int => $suma + $cuota->faltaEnCentavos(),
            0
        );
    }

    protected function saldo(): Attribute
    {
        return Attribute::get(fn (): string => ProrrateoDeGastos::aDecimal($this->saldoEnCentavos()));
    }

    /** La cuota más antigua que todavía debe algo, o null si no queda ninguna. */
    public function proximaCuota(): ?Cuota
    {
        $this->loadMissing('cuotas');

        return $this->cuotas->first(fn (Cuota $cuota): bool => ! $cuota->esta_pagada);
    }

    /**
     * ¿Hay alguna cuota vencida sin saldar?
     *
     * Es la pregunta que ordena la cartera: a quién hay que llamar hoy.
     */
    protected function estaEnMora(): Attribute
    {
        return Attribute::get(function (): bool {
            $this->loadMissing('cuotas');

            return $this->esta_vigente
                && $this->cuotas->contains(fn (Cuota $cuota): bool => $cuota->esta_vencida);
        });
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('estado', 'vigente');
    }

    /**
     * Créditos vigentes con al menos una cuota vencida sin saldar.
     *
     * Se resuelve con `whereHas` y no cargando las cuotas en PHP: la cartera
     * de una tienda con años de historia no cabe en memoria.
     */
    public function scopeEnMora(Builder $query): Builder
    {
        return $query->vigentes()->whereHas('cuotas', fn (Builder $q) => $q->vencidas());
    }

    /** Créditos vigentes con alguna cuota que vence dentro de los próximos días. */
    public function scopePorVencer(Builder $query, int $dias = 7): Builder
    {
        return $query->vigentes()->whereHas(
            'cuotas',
            fn (Builder $q) => $q->pendientes()->whereBetween('vence_en', [today(), today()->addDays($dias)])
        );
    }

    /** Búsqueda por el cliente o por el código de la venta. */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->whereHas('venta', fn (Builder $v) => $v->where('codigo', 'like', "%{$termino}%"))
                ->orWhereHas('cliente', fn (Builder $c) => $c->buscar($termino));
        });
    }
}
