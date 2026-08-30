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
 * Una orden de entrega: qué aparatos se llevan, adónde y en qué anda.
 *
 * Una venta puede tener varias —tres aparatos que no caben en un viaje— y hay
 * ventas que no tienen ninguna, porque el cliente se llevó la licuadora en la
 * mano.
 */
#[Fillable([
    'venta_id',
    'cliente_id',
    'direccion',
    'referencia',
    'telefono_contacto',
    'programada_para',
    'estado',
    'con_instalacion',
    'repartidor_id',
    'salio_en',
    'entregada_en',
    'instalada_en',
    'recibida_por',
    'motivo_fallo',
    'creado_por',
    'notas',
])]
class Entrega extends Model
{
    /** @use HasFactory<\Database\Factories\EntregaFactory> */
    use HasFactory;

    protected $table = 'entregas';

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'en_ruta' => 'En ruta',
        'entregada' => 'Entregada',
        'fallida' => 'No se pudo entregar',
        'cancelada' => 'Cancelada',
    ];

    /** Las que todavía dan trabajo: salen en el tablero por defecto. */
    public const ESTADOS_ABIERTOS = ['pendiente', 'en_ruta', 'fallida'];

    protected function casts(): array
    {
        return [
            'venta_id' => 'integer',
            'cliente_id' => 'integer',
            'repartidor_id' => 'integer',
            'creado_por' => 'integer',
            'con_instalacion' => 'boolean',
            'programada_para' => 'date',
            'salio_en' => 'datetime',
            'entregada_en' => 'datetime',
            'instalada_en' => 'datetime',
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

    public function repartidor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'repartidor_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(EntregaDetalle::class);
    }

    protected function estaAbierta(): Attribute
    {
        return Attribute::get(fn (): bool => in_array($this->estado, self::ESTADOS_ABIERTOS, true));
    }

    protected function estaEntregada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'entregada');
    }

    /**
     * Tenía que haber salido y sigue sin entregarse.
     *
     * Sin fecha pactada no hay atraso: «cuando se pueda» no se incumple.
     */
    protected function estaAtrasada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->esta_abierta
            && $this->programada_para !== null
            && $this->programada_para->isPast());
    }

    protected function esParaHoy(): Attribute
    {
        return Attribute::get(fn (): bool => $this->esta_abierta
            && $this->programada_para !== null
            && $this->programada_para->isToday());
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->whereIn('estado', self::ESTADOS_ABIERTOS);
    }

    public function scopeAtrasadas(Builder $query): Builder
    {
        return $query->abiertas()->whereNotNull('programada_para')
            ->whereDate('programada_para', '<', today());
    }

    public function scopeDeHoy(Builder $query): Builder
    {
        return $query->abiertas()->whereDate('programada_para', today());
    }

    /** Búsqueda por venta, cliente o dirección — lo que se recuerda al llamar. */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('direccion', 'like', "%{$termino}%")
                ->orWhere('referencia', 'like', "%{$termino}%")
                ->orWhereHas('venta', fn (Builder $v) => $v->where('codigo', 'like', "%{$termino}%"))
                ->orWhereHas('cliente', fn (Builder $c) => $c->buscar($termino));
        });
    }
}
