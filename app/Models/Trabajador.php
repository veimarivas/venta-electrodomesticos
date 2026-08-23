<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['persona_id', 'cargo_id', 'codigo', 'fecha_ingreso', 'fecha_baja', 'motivo_baja'])]
class Trabajador extends Model
{
    /** @use HasFactory<\Database\Factories\TrabajadorFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'trabajadores';

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_baja' => 'date',
        ];
    }

    /**
     * Datos personales del trabajador (relación 1 a 1).
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Cargo que ocupa dentro de la tienda.
     */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    /**
     * ¿Sigue trabajando en la tienda?
     */
    protected function estaActivo(): Attribute
    {
        return Attribute::get(fn (): bool => $this->fecha_baja === null);
    }

    /**
     * Solo el personal vigente.
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->whereNull('fecha_baja');
    }

    /**
     * Solo quienes ya no trabajan aquí. Sus fichas se conservan porque las
     * ventas y compras que registraron siguen apuntando a ellos.
     */
    public function scopeDadosDeBaja(Builder $query): Builder
    {
        return $query->whereNotNull('fecha_baja');
    }

    /**
     * Tiempo trabajado, en texto legible ("2 años", "8 meses"). Si ya fue dado
     * de baja se calcula hasta esa fecha, no hasta hoy.
     */
    protected function antiguedad(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->fecha_ingreso === null) {
                return '—';
            }

            $meses = (int) $this->fecha_ingreso->diffInMonths($this->fecha_baja ?? now());

            if ($meses < 1) {
                return 'Menos de un mes';
            }

            if ($meses < 12) {
                return $meses === 1 ? '1 mes' : "{$meses} meses";
            }

            $anios = intdiv($meses, 12);

            return $anios === 1 ? '1 año' : "{$anios} años";
        });
    }

    /**
     * Búsqueda por código, o por los datos de la persona y su cargo.
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('codigo', 'like', "%{$termino}%")
                ->orWhereHas('persona', fn (Builder $p) => $p->buscar($termino))
                ->orWhereHas('cargo', fn (Builder $c) => $c->where('nombre', 'like', "%{$termino}%"));
        });
    }
}
