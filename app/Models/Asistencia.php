<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un turno de un trabajador en una tienda: entrada y, cuando la marca, salida.
 */
class Asistencia extends Model
{
    protected $table = 'asistencias';

    protected $fillable = [
        'user_id', 'tienda_id', 'fecha',
        'entrada_en', 'entrada_latitud', 'entrada_longitud', 'entrada_distancia', 'entrada_precision',
        'salida_en', 'salida_latitud', 'salida_longitud', 'salida_distancia', 'salida_precision',
        'minutos_atraso', 'corregida_por', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'entrada_en' => 'datetime',
            'salida_en' => 'datetime',
            'entrada_latitud' => 'float',
            'entrada_longitud' => 'float',
            'salida_latitud' => 'float',
            'salida_longitud' => 'float',
            'entrada_distancia' => 'integer',
            'salida_distancia' => 'integer',
            'minutos_atraso' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tienda(): BelongsTo
    {
        return $this->belongsTo(Tienda::class)->withTrashed();
    }

    public function corrector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corregida_por');
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->whereNull('salida_en');
    }

    public function scopeDelMes(Builder $query, int $anio, int $mes): Builder
    {
        return $query->whereYear('fecha', $anio)->whereMonth('fecha', $mes);
    }

    public function estaAbierta(): bool
    {
        return $this->salida_en === null;
    }

    /** Un turno de un día anterior que nunca se cerró. */
    public function sinSalida(): bool
    {
        return $this->salida_en === null && ! $this->fecha->isToday();
    }

    /** Minutos trabajados; null mientras no tenga salida. */
    public function minutosTrabajados(): ?int
    {
        return $this->salida_en === null
            ? null
            : (int) max(0, $this->entrada_en->diffInMinutes($this->salida_en));
    }

    public function llegoTarde(): bool
    {
        return ($this->minutos_atraso ?? 0) > 0;
    }

    /** Lo que ven la app y el panel. */
    public function aArreglo(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'trabajador' => $this->relationLoaded('user') ? $this->user?->name : null,
            'tienda_id' => $this->tienda_id,
            'tienda' => $this->relationLoaded('tienda') ? $this->tienda?->nombre : null,
            'fecha' => $this->fecha->toDateString(),
            'entrada_en' => $this->entrada_en->toIso8601String(),
            'entrada_distancia' => $this->entrada_distancia,
            'salida_en' => $this->salida_en?->toIso8601String(),
            'salida_distancia' => $this->salida_distancia,
            'minutos' => $this->minutosTrabajados(),
            'minutos_atraso' => $this->minutos_atraso,
            'abierta' => $this->estaAbierta(),
            'sin_salida' => $this->sinSalida(),
            'corregida' => $this->corregida_por !== null,
            'notas' => $this->notas,
        ];
    }
}
