<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Una tienda (sucursal) con su ubicación, el radio dentro del cual se marca
 * asistencia y, si lleva control de atrasos, su hora de entrada.
 */
class Tienda extends Model
{
    use SoftDeletes;

    protected $table = 'tiendas';

    protected $fillable = [
        'nombre', 'direccion', 'latitud', 'longitud', 'radio_metros',
        'hora_entrada', 'tolerancia_minutos', 'activa',
    ];

    protected function casts(): array
    {
        return [
            'latitud' => 'float',
            'longitud' => 'float',
            'radio_metros' => 'integer',
            'tolerancia_minutos' => 'integer',
            'activa' => 'boolean',
        ];
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    /** Activas y con ubicación: las únicas donde se puede marcar. */
    public function scopeParaMarcar(Builder $query): Builder
    {
        return $query->where('activa', true)->whereNotNull('latitud')->whereNotNull('longitud');
    }

    public function tieneUbicacion(): bool
    {
        return $this->latitud !== null && $this->longitud !== null;
    }

    /** «08:30» o null. */
    public function horaEntradaCorta(): ?string
    {
        return $this->hora_entrada ? substr((string) $this->hora_entrada, 0, 5) : null;
    }

    public function enlaceMapa(): ?string
    {
        return $this->tieneUbicacion()
            ? "https://www.google.com/maps?q={$this->latitud},{$this->longitud}"
            : null;
    }
}
