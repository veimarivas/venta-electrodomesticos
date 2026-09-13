<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de autorización para vender un aparato por debajo de su mínimo.
 *
 * Guarda una foto del momento —precio de lista, tope de rebaja, costo y precio
 * pedido— porque el catálogo puede cambiar y la autorización tiene que poder
 * auditarse después con los números con los que se decidió.
 *
 * `precio_aprobado` es el piso que autorizó el administrador: al cobrar, la
 * venta solo puede apoyarse en ella si el precio no queda por debajo de ese
 * piso. Una autorización se **gasta** al venderse (`consumida`): no vale para
 * dos aparatos.
 */
#[Fillable([
    'unidad_id',
    'producto_id',
    'user_id',
    'precio_lista',
    'descuento_maximo',
    'costo_unitario',
    'precio_solicitado',
    'estado',
    'precio_aprobado',
    'resuelto_por',
    'resuelto_en',
    'motivo',
    'venta_id',
    'venta_detalle_id',
])]
class SolicitudDescuento extends Model
{
    /** @use HasFactory<\Database\Factories\SolicitudDescuentoFactory> */
    use HasFactory;

    protected $table = 'solicitudes_descuento';

    public const ESTADOS = [
        'pendiente' => 'Pendiente',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
        'consumida' => 'Usada',
        'cancelada' => 'Cancelada',
    ];

    /** Estados que ya no esperan nada de nadie. */
    public const RESUELTAS = ['aprobada', 'rechazada', 'consumida', 'cancelada'];

    protected function casts(): array
    {
        return [
            'unidad_id' => 'integer',
            'producto_id' => 'integer',
            'user_id' => 'integer',
            'resuelto_por' => 'integer',
            'precio_lista' => 'decimal:2',
            'descuento_maximo' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'precio_solicitado' => 'decimal:2',
            'precio_aprobado' => 'decimal:2',
            'resuelto_en' => 'datetime',
        ];
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /** Quién pidió la autorización. */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Quién la aprobó o la rechazó. */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resuelto_por');
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente');
    }

    public function estaPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function estaAprobada(): bool
    {
        return $this->estado === 'aprobada';
    }

    /**
     * ¿Este precio queda cubierto por la autorización?
     *
     * Solo si está aprobada, sin usar, y el precio no baja del piso aprobado.
     */
    public function cubre(int $precioEnCentavos): bool
    {
        return $this->estaAprobada()
            && $this->venta_id === null
            && $this->precio_aprobado !== null
            && $precioEnCentavos >= \App\Support\ProrrateoDeGastos::aCentavos($this->precio_aprobado);
    }
}
