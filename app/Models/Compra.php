<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cabecera de una compra al proveedor.
 *
 * Nace en 'pendiente': la mercadería se compró pero todavía no se verificó, y
 * sus unidades NO entran al stock hasta que se recepciona. Al recepcionarla se
 * generan las unidades físicas (con sus seriales si el producto los lleva) y
 * queda congelada: cambiarla después falsearía el costo real de unidades que
 * ya están en el almacén o vendidas.
 */
#[Fillable([
    'proveedor_id',
    'user_id',
    'codigo',
    'numero_factura',
    'fecha_compra',
    'subtotal',
    'descuento',
    'impuesto',
    'flete',
    'otros_gastos',
    'total',
    'moneda',
    'tipo_cambio',
    'estado',
    'recepcionada_en',
    'notas',
])]
class Compra extends Model
{
    /** @use HasFactory<\Database\Factories\CompraFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'compras';

    /** Estados de la compra y su etiqueta en español. */
    public const ESTADOS = [
        'borrador' => 'Borrador',
        'pendiente' => 'Pendiente',
        'recepcionada' => 'Recepcionada',
        'anulada' => 'Anulada',
    ];

    protected function casts(): array
    {
        return [
            'fecha_compra' => 'date',
            'recepcionada_en' => 'datetime',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'impuesto' => 'decimal:2',
            'flete' => 'decimal:2',
            'otros_gastos' => 'decimal:2',
            'total' => 'decimal:2',
            'tipo_cambio' => 'decimal:6',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    /** Quién la registró. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class);
    }

    /**
     * Pagos hechos al proveedor por esta compra. Pueden ser varios hasta
     * cubrir el total; cada uno lleva su boucher y su monto.
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(PagoCompra::class);
    }

    /**
     * Unidades físicas generadas por esta compra. La columna compra_id de
     * unidades está denormalizada justamente para que esta consulta sea directa.
     */
    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }

    protected function esBorrador(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'borrador');
    }

    protected function esPendiente(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'pendiente');
    }

    protected function estaRecepcionada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'recepcionada');
    }

    /**
     * ¿Todavía se puede recepcionar? Solo lo que no se recibió ni se anuló:
     * un borrador viejo entra igual.
     */
    protected function puedeRecepcionarse(): Attribute
    {
        return Attribute::get(fn (): bool => in_array($this->estado, ['borrador', 'pendiente'], true));
    }

    /**
     * Cuánto se lleva pagado de esta compra. Prefiere la suma agregada que
     * deja `withSum('pagos as total_pagado')`; si no se cargó, se consulta.
     * El `array_key_exists` evita que strict mode reviente por el agregado
     * ausente.
     */
    protected function totalPagado(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->relationLoaded('pagos')) {
                return (string) $this->pagos->sum('monto');
            }

            $agregado = array_key_exists('total_pagado', $this->attributes)
                ? $this->attributes['total_pagado']
                : $this->pagos()->sum('monto');

            return (string) $agregado;
        });
    }

    /** Lo que falta por pagar; negativo o cero significa pagada. */
    protected function saldoPendiente(): Attribute
    {
        return Attribute::get(fn (): string => bcsub(
            (string) $this->total,
            (string) $this->total_pagado,
            2
        ));
    }

    protected function estaPagada(): Attribute
    {
        return Attribute::get(fn (): bool => bccomp($this->saldo_pendiente, '0', 2) <= 0);
    }

    /**
     * Gastos que se reparten entre las unidades: flete y otros costos.
     * El impuesto queda fuera porque en Bolivia suele ser recuperable.
     */
    protected function gastosProrrateables(): Attribute
    {
        return Attribute::get(fn (): string => bcadd(
            (string) $this->flete,
            (string) $this->otros_gastos,
            2
        ));
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('codigo', 'like', "%{$termino}%")
                ->orWhere('numero_factura', 'like', "%{$termino}%")
                ->orWhereHas('proveedor', fn (Builder $s) => $s->where('nombre', 'like', "%{$termino}%"));
        });
    }
}
