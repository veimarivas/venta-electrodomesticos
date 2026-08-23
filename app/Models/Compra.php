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
 * Mientras está en 'borrador' se puede editar libremente. Al recepcionarla se
 * generan las unidades físicas y queda congelada: cambiarla después
 * falsearía el costo real de unidades que ya están en el almacén o vendidas.
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

    protected function estaRecepcionada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'recepcionada');
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
