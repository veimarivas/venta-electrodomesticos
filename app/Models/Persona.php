<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'carnet',
    'nombres',
    'apellido_paterno',
    'apellido_materno',
    'celular',
    'direccion',
    'correo',
    'fecha_nacimiento',
])]
class Persona extends Model
{
    /** @use HasFactory<\Database\Factories\PersonaFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'personas';

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    /**
     * Cuenta de acceso al sistema (opcional). La clave foránea vive en users
     * (users.persona_id), de modo que ningún usuario queda sin ficha personal.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'persona_id');
    }

    /**
     * Ficha laboral, si esta persona trabaja en la tienda.
     */
    public function trabajador(): HasOne
    {
        return $this->hasOne(Trabajador::class);
    }

    /**
     * Ficha comercial, si esta persona compra en la tienda. Es independiente
     * de la laboral: un trabajador también puede ser cliente.
     */
    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class);
    }

    /**
     * Nombre completo para listados y selectores.
     */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn (): string => trim(implode(' ', array_filter([
            $this->nombres,
            $this->apellido_paterno,
            $this->apellido_materno,
        ]))));
    }

    /**
     * Apellidos juntos, para la columna del listado.
     */
    protected function apellidos(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->apellido_paterno.' '.$this->apellido_materno));
    }

    /**
     * Iniciales para el avatar del listado. Usa el apellido paterno y, si
     * no existe (personas con solo apellido materno), cae al materno.
     */
    protected function iniciales(): Attribute
    {
        return Attribute::get(function (): string {
            $apellido = $this->apellido_paterno !== '' ? $this->apellido_paterno : $this->apellido_materno;

            return Str::upper(
                Str::substr($this->nombres, 0, 1).Str::substr((string) $apellido, 0, 1)
            );
        });
    }

    /**
     * Color del avatar. Se deriva del id para que una misma persona
     * conserve siempre el mismo color entre páginas y recargas.
     */
    protected function colorAvatar(): Attribute
    {
        $paleta = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];

        return Attribute::get(fn (): string => $paleta[($this->id ?? 0) % count($paleta)]);
    }

    /**
     * Edad en años cumplidos, o null si no se registró la fecha.
     */
    protected function edad(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->fecha_nacimiento?->age);
    }

    /**
     * Búsqueda por carnet, nombres, apellidos o correo.
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            foreach (['carnet', 'nombres', 'apellido_paterno', 'apellido_materno', 'correo', 'celular'] as $campo) {
                $q->orWhere($campo, 'like', "%{$termino}%");
            }
        });
    }
}
