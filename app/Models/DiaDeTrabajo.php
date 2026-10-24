<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un día que a un vendedor le toca trabajar.
 *
 * El calendario del mes lo arma quien supervisa (`asistencia.programar`). Con
 * él, el historial de asistencia distingue un día libre de una falta.
 */
#[Fillable(['user_id', 'fecha', 'programado_por'])]
class DiaDeTrabajo extends Model
{
    protected $table = 'dias_de_trabajo';

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'fecha' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDelMes(Builder $query, int $anio, int $mes): Builder
    {
        return $query->whereYear('fecha', $anio)->whereMonth('fecha', $mes);
    }
}
