<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Un ajuste de la tienda, guardado como clave y valor. Se lee y se escribe a
 * través de `App\Support\Ajustes`, que sabe el valor por defecto de cada uno.
 */
#[Fillable(['clave', 'valor', 'user_id'])]
class Ajuste extends Model
{
    protected $table = 'ajustes';
}
