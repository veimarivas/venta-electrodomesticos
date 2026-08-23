<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre'])]
class Cargo extends Model
{
    /** @use HasFactory<\Database\Factories\CargoFactory> */
    use HasFactory;

    protected $table = 'cargos';

    /**
     * Un cargo agrupa a muchos trabajadores (relación 1 a N).
     */
    public function trabajadores(): HasMany
    {
        return $this->hasMany(Trabajador::class);
    }
}
