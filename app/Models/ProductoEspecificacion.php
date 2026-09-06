<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una característica de un producto: `clave` + `valor`, en el orden en que se
 * registró (`posicion`).
 *
 * Reemplaza a la columna JSON `productos.especificaciones`: una fila por
 * característica, que es lo que permite editar cada una sin pelear con el
 * formato de la columna.
 */
#[Fillable([
    'producto_id',
    'clave',
    'valor',
    'posicion',
])]
class ProductoEspecificacion extends Model
{
    protected $table = 'producto_especificaciones';

    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}