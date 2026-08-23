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

#[Fillable([
    'padre_id',
    'nombre',
    'slug',
    'descripcion',
    'imagen',
    'posicion',
    'activo',
])]
class Categoria extends Model
{
    /** @use HasFactory<\Database\Factories\CategoriaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'categorias';

    protected function casts(): array
    {
        return [
            'padre_id' => 'integer',
            'posicion' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'padre_id');
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    /**
     * Solo categorías activas (visibles en el catálogo).
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    /**
     * Orden por defecto de un árbol: primero los hermanos por posición y
     * luego por nombre como desempate estable.
     */
    public function scopeOrdenadas(Builder $query): Builder
    {
        return $query->orderBy('posicion')->orderBy('nombre');
    }

    /**
     * Ruta completa dentro del árbol ("Electrónica / Audio / Parlantes"), para
     * situar la categoría sin tener que abrir el módulo de categorías.
     *
     * Sube por la relación `padre` que ya existe. El tope de saltos es una red
     * de seguridad: si un dato corrupto encadenara un ciclo, el bucle pararía
     * igual en lugar de colgar la petición.
     */
    protected function ruta(): Attribute
    {
        return Attribute::get(function (): string {
            $nombres = [$this->nombre];
            $actual = $this;
            $saltos = 0;

            while ($actual->padre_id !== null && $saltos++ < 50) {
                $actual = $actual->padre;

                if ($actual === null) {
                    break;
                }

                array_unshift($nombres, $actual->nombre);
            }

            return implode(' / ', $nombres);
        });
    }

    /**
     * Ids de todas las subcategorías (descendientes), para impedir ciclos
     * al elegir la categoría padre: una categoría nunca puede colgarse de
     * sí misma ni de una de sus hijas.
     *
     * @return array<int, int>
     */
    public function descendientesIds(): array
    {
        $todas = self::query()->select(['id', 'padre_id'])->get();
        $hijosDe = $todas->groupBy(fn (Categoria $c): int => $c->padre_id ?? 0);

        $ids = [];
        $cola = [$this->id];

        while ($cola !== []) {
            $actual = array_shift($cola);

            foreach ($hijosDe->get($actual, collect()) as $hijo) {
                $ids[] = $hijo->id;
                $cola[] = $hijo->id;
            }
        }

        return $ids;
    }
}
