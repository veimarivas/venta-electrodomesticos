<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una orden de servicio técnico sobre un aparato concreto.
 *
 * `en_garantia` se congela al recibirla: la cobertura depende de
 * `productos.meses_garantia`, que alguien puede cambiar mañana, y una
 * reparación aceptada como garantía no puede volverse cobrable sola.
 */
#[Fillable([
    'codigo',
    'unidad_id',
    'venta_id',
    'cliente_id',
    'en_garantia',
    'garantia_hasta',
    'falla_reportada',
    'diagnostico',
    'trabajo_realizado',
    'estado',
    'costo',
    'tecnico_id',
    'prometida_para',
    'recibida_en',
    'lista_en',
    'entregada_en',
    'entregada_a',
    'estado_unidad_origen',
    'recibida_por',
    'notas',
])]
class Reparacion extends Model
{
    /** @use HasFactory<\Database\Factories\ReparacionFactory> */
    use HasFactory;

    protected $table = 'reparaciones';

    public const ESTADOS = [
        'recibida' => 'Recibida',
        'en_reparacion' => 'En reparación',
        'esperando_repuesto' => 'Esperando repuesto',
        'lista' => 'Lista para entregar',
        'entregada' => 'Entregada',
        'irreparable' => 'Sin arreglo',
        'cancelada' => 'Cancelada',
    ];

    /** Las que siguen dando trabajo: el aparato está en el taller. */
    public const ESTADOS_ABIERTOS = ['recibida', 'en_reparacion', 'esperando_repuesto', 'lista'];

    /** Las que ya terminaron: el aparato salió del taller. */
    public const ESTADOS_CERRADOS = ['entregada', 'irreparable', 'cancelada'];

    protected function casts(): array
    {
        return [
            'unidad_id' => 'integer',
            'venta_id' => 'integer',
            'cliente_id' => 'integer',
            'tecnico_id' => 'integer',
            'recibida_por' => 'integer',
            'en_garantia' => 'boolean',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'costo' => 'decimal:2',
            'garantia_hasta' => 'date',
            'prometida_para' => 'date',
            'recibida_en' => 'datetime',
            'lista_en' => 'datetime',
            'entregada_en' => 'datetime',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function recibidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibida_por');
    }

    protected function estaAbierta(): Attribute
    {
        return Attribute::get(fn (): bool => in_array($this->estado, self::ESTADOS_ABIERTOS, true));
    }

    protected function estaLista(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'lista');
    }

    /**
     * Se prometió para antes de hoy y sigue sin estar lista.
     *
     * Una que ya está lista no está atrasada aunque el cliente no haya venido
     * a recogerla: el taller cumplió.
     */
    protected function estaAtrasada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->esta_abierta
            && ! $this->esta_lista
            && $this->prometida_para !== null
            && $this->prometida_para->isPast());
    }

    /** Cuántos días lleva el aparato en el taller. */
    protected function diasEnTaller(): Attribute
    {
        return Attribute::get(fn (): int => (int) $this->recibida_en->startOfDay()->diffInDays(
            ($this->entregada_en ?? now())->startOfDay()
        ));
    }

    public function scopeAbiertas(Builder $query): Builder
    {
        return $query->whereIn('estado', self::ESTADOS_ABIERTOS);
    }

    public function scopeAtrasadas(Builder $query): Builder
    {
        return $query->whereIn('estado', ['recibida', 'en_reparacion', 'esperando_repuesto'])
            ->whereNotNull('prometida_para')
            ->whereDate('prometida_para', '<', today());
    }

    /** Búsqueda por lo que se recuerda: el papel, el serial o el cliente. */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('codigo', 'like', "%{$termino}%")
                ->orWhere('falla_reportada', 'like', "%{$termino}%")
                ->orWhereHas('unidad', fn (Builder $u) => $u->buscar($termino))
                ->orWhereHas('cliente', fn (Builder $c) => $c->buscar($termino));
        });
    }
}
