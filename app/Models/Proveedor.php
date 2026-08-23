<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Quien nos vende la mercadería. De aquí cuelgan las compras y, a través de
 * ellas, el costo real de cada unidad física.
 */
#[Fillable([
    'nombre',
    'nit',
    'contacto',
    'telefono',
    'correo',
    'direccion',
    'notas',
    'activo',
])]
class Proveedor extends Model
{
    /** @use HasFactory<\Database\Factories\ProveedorFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'proveedores';

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class);
    }

    /**
     * Iniciales para el avatar del listado.
     */
    protected function iniciales(): Attribute
    {
        return Attribute::get(function (): string {
            $palabras = preg_split('/\s+/', trim((string) $this->nombre)) ?: [];
            $iniciales = Str::substr($palabras[0] ?? '', 0, 1).Str::substr($palabras[1] ?? '', 0, 1);

            return Str::upper($iniciales !== '' ? $iniciales : 'P');
        });
    }

    /**
     * Color estable por proveedor, para que no cambie entre páginas.
     */
    protected function colorAvatar(): Attribute
    {
        $paleta = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];

        return Attribute::get(fn (): string => $paleta[($this->id ?? 0) % count($paleta)]);
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            foreach (['nombre', 'nit', 'contacto', 'telefono', 'correo'] as $campo) {
                $q->orWhere($campo, 'like', "%{$termino}%");
            }
        });
    }
}
