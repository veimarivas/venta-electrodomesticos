<?php

namespace App\Models;

use App\Support\ProrrateoDeGastos;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una línea de la venta: exactamente una unidad física.
 *
 * No hay columna `cantidad` porque el inventario está serializado.
 *
 * `unidad_vendida_id` es la guardia de la doble venta: copia de `unidad_id`
 * mientras la venta está viva, y NULL al anularla. Su índice único impide que
 * un aparato esté en dos ventas completadas a la vez, pero deja revenderlo si
 * la anterior se anuló y volvió al stock.
 */
#[Fillable([
    'venta_id',
    'unidad_id',
    'unidad_vendida_id',
    'producto_id',
    'precio_unitario',
    'costo_unitario',
    'descuento',
    'ganancia',
    'devuelto_en',
    'motivo_devolucion',
])]
class VentaDetalle extends Model
{
    /** @use HasFactory<\Database\Factories\VentaDetalleFactory> */
    use HasFactory;

    protected $table = 'venta_detalles';

    protected function casts(): array
    {
        return [
            'venta_id' => 'integer',
            'unidad_id' => 'integer',
            'unidad_vendida_id' => 'integer',
            'producto_id' => 'integer',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'precio_unitario' => 'decimal:2',
            'costo_unitario' => 'decimal:2',
            'descuento' => 'decimal:2',
            'ganancia' => 'decimal:2',
            'devuelto_en' => 'datetime',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /** ¿Este aparato se devolvió? */
    public function estaDevuelto(): bool
    {
        return $this->devuelto_en !== null;
    }

    /**
     * Lo que se cobró por este aparato: el precio pactado menos su rebaja.
     *
     * Es el importe que se descuenta de la venta al devolverlo, y el que se
     * suma a `ventas.total_devuelto`.
     */
    public function netoEnCentavos(): int
    {
        return ProrrateoDeGastos::aCentavos($this->precio_unitario)
            - ProrrateoDeGastos::aCentavos($this->descuento);
    }

    /** Solo las líneas que siguen vendidas. */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('devuelto_en');
    }

    public function scopeDevueltos(Builder $query): Builder
    {
        return $query->whereNotNull('devuelto_en');
    }
}
