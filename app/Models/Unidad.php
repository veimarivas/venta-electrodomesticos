<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
// Sin este import, el tipo de retorno de `garantiaHasta()` resuelve a
// App\Models\Attribute: Eloquent no lo reconoce como accessor y, con
// `Model::shouldBeStrict()`, leer `$unidad->garantia_hasta` lanza
// MissingAttributeException en vez de calcular la garantía.
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unidad física del producto: cada registro es un aparato con su código o
 * serial propio. Es lo que se vende al cliente, nunca el producto completo.
 */
#[Fillable([
    'producto_id',
    'compra_detalle_id',
    'compra_id',
    'serial',
    'codigo_interno',
    'costo_unitario',
    'precio_venta',
    'estado',
    'ubicacion',
    'ingresado_en',
    'vendido_en',
    'notas',
])]
class Unidad extends Model
{
    /** @use HasFactory<\Database\Factories\UnidadFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'unidades';

    /** Estados posibles de una unidad física y su etiqueta en español. */
    public const ESTADOS = [
        'en_stock' => 'En stock',
        'reservado' => 'Reservado',
        'vendido' => 'Vendido',
        'devuelto' => 'Devuelto',
        'danado' => 'Dañado',
        // «En taller» y no «En garantía»: por aquí pasan también las
        // reparaciones que el cliente paga, y llamarlas garantía haría creer
        // que no se cobran.
        'garantia' => 'En taller',
        'perdido' => 'Perdido',
    ];

    protected function casts(): array
    {
        return [
            'producto_id' => 'integer',
            'compra_detalle_id' => 'integer',
            'compra_id' => 'integer',
            // Dinero como decimal:2, nunca float: la aritmética en coma
            // flotante pierde centavos y aquí se calculan costos reales y
            // ganancias (0.1 + 0.2 no da 0.3 en float).
            'costo_unitario' => 'decimal:2',
            'precio_venta' => 'decimal:2',
            'estado' => 'string',
            'ingresado_en' => 'datetime',
            'vendido_en' => 'datetime',
        ];
    }

    /**
     * Hasta cuándo está cubierto este aparato.
     *
     * Los meses viven en el producto; la fecha desde la que se cuentan, aquí.
     * Se mantiene como accessor para que `$unidad->garantia_hasta` siga
     * funcionando aunque la columna ya no exista en DB.
     *
     * **Cuenta desde la venta, no desde que entró al almacén.** Contarla desde
     * `ingresado_en` le quitaba al cliente todo el tiempo que el aparato pasó
     * en la bodega: un refrigerador con 12 meses que estuvo 8 en el depósito
     * llegaba a su casa con 4, y esa fecha recortada es la que se imprimía en
     * su recibo. Mientras el aparato no se ha vendido se cuenta desde que
     * entró, que es la garantía que el proveedor le dio a la tienda.
     */
    protected function garantiaHasta(): Attribute
    {
        return Attribute::get(function (): ?\Carbon\CarbonInterface {
            $desde = $this->vendido_en ?? $this->ingresado_en;

            if (! $desde) {
                return null;
            }

            // Producto puede no estar cargado: se consulta perezosamente.
            $producto = $this->relationLoaded('producto') ? $this->producto : $this->producto()->first();
            $meses = (int) ($producto?->meses_garantia ?? 0);

            if ($meses <= 0) {
                return null;
            }

            // `addMonthsNoOverflow`: comprado un 31 de enero, la garantía de
            // un mes vence el 28 de febrero y no el 3 de marzo.
            return \Carbon\Carbon::parse($desde)->addMonthsNoOverflow($meses);
        });
    }

    /** ¿Sigue cubierto hoy? */
    protected function enGarantia(): Attribute
    {
        return Attribute::get(fn (): bool => $this->garantia_hasta !== null
            && $this->garantia_hasta->endOfDay()->isFuture());
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Compra::class);
    }

    public function ventaDetalle(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\VentaDetalle::class);
    }

    /** Sus pasos por el taller, del más reciente al más antiguo. */
    public function reparaciones(): HasMany
    {
        return $this->hasMany(Reparacion::class)->latest('recibida_en')->latest('id');
    }

    /**
     * Kardex de esta unidad: su historia completa, del más reciente al más
     * antiguo. Se escribe siempre a través de App\Support\Kardex.
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class)->latest('created_at')->latest('id');
    }

    /**
     * Unidades disponibles para la venta.
     */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->where('estado', 'en_stock');
    }

    /**
     * Búsqueda por el aparato concreto: su serial, su código interno o el
     * producto al que pertenece.
     */
    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('serial', 'like', "%{$termino}%")
                ->orWhere('codigo_interno', 'like', "%{$termino}%")
                ->orWhereHas('producto', fn (Builder $p) => $p->buscar($termino));
        });
    }

    /**
     * ¿Esta unidad se puede vender ahora mismo?
     */
    public function esVendible(): bool
    {
        return $this->estado === 'en_stock';
    }
}
