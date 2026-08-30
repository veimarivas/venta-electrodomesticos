<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cliente de la tienda.
 *
 * Misma forma que Trabajador: la ficha solo guarda lo que hace a alguien
 * cliente (su código), y todo lo personal cuelga de `personas` en una
 * relación 1 a 1. Así una misma persona puede ser trabajador y cliente sin
 * que sus datos se dupliquen ni se contradigan.
 *
 * Por ahora los clientes NO tienen cuenta de usuario.
 */
#[Fillable(['persona_id', 'codigo'])]
class Cliente extends Model
{
    /** @use HasFactory<\Database\Factories\ClienteFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'clientes';

    /**
     * Datos personales del cliente (relación 1 a 1).
     */
    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Compras del cliente. Incluye las anuladas: el histórico se conserva y
     * quien consulta decide si las cuenta (los reportes no lo hacen).
     */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    /**
     * Sus planes de cuotas. Del más reciente al más antiguo, que es como se
     * lee un estado de cuenta.
     */
    public function creditos(): HasMany
    {
        return $this->hasMany(Credito::class)->latest('id');
    }

    /**
     * Búsqueda por código o por los datos de la persona.
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('codigo', 'like', "%{$termino}%")
                ->orWhereHas('persona', fn (Builder $p) => $p->buscar($termino));
        });
    }
}
