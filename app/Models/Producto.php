<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'categoria_id',
    'marca_id',
    'sku',
    'nombre',
    'slug',
    'modelo',
    'descripcion',
    'especificaciones',
    'imagen',
    'precio_venta',
    'descuento_maximo',
    'stock_minimo',
    'meses_garantia',
    'activo',
])]
class Producto extends Model
{
    /** @use HasFactory<\Database\Factories\ProductoFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'productos';

    protected function casts(): array
    {
        return [
            'categoria_id' => 'integer',
            'marca_id' => 'integer',
            'especificaciones' => 'array',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'precio_venta' => 'decimal:2',
            // Tope de rebaja en Bs sobre el precio de la unidad. 0 = se cobra
            // el precio de lista, sin margen para negociar en mostrador.
            'descuento_maximo' => 'decimal:2',
            'stock_minimo' => 'integer',
            'meses_garantia' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Búsqueda por lo que el mostrador tiene a mano: el nombre que dice el
     * cliente, el SKU de la etiqueta o el modelo impreso en el aparato.
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            foreach (['nombre', 'sku', 'modelo'] as $campo) {
                $q->orWhere($campo, 'like', "%{$termino}%");
            }
        });
    }
}
