<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

/**
 * Cabecera de una venta.
 *
 * Una venta completada es un hecho consumado: no se edita ni se borra, solo se
 * anula. Sus importes quedan congelados porque los reportes históricos tienen
 * que seguir dando el mismo número dentro de un año.
 */
#[Fillable([
    'cliente_id',
    'user_id',
    'caja_id',
    'codigo',
    'clave_idempotencia',
    'vendida_en',
    'subtotal',
    'descuento',
    'total',
    'total_devuelto',
    'costo_total',
    'ganancia',
    'metodo_pago',
    'qr_cobro_id',
    'monto_efectivo',
    'monto_qr',
    'comprobante_qr',
    'estado',
    'anulada_en',
    'primera_devolucion_en',
    'motivo_anulacion',
    'notas',
])]
class Venta extends Model
{
    /** @use HasFactory<\Database\Factories\VentaFactory> */
    use HasFactory;

    protected $table = 'ventas';

    /** Métodos de pago aceptados y su etiqueta en español. */
    public const METODOS_PAGO = [
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'transferencia' => 'Transferencia',
        'qr' => 'QR',
        'mixto' => 'Mixto (efectivo + QR)',
        'credito' => 'Crédito',
    ];

    /** Los que exigen respaldo del banco: se cobran fuera de caja. */
    public const METODOS_CON_QR = ['qr', 'mixto'];

    /**
     * Lo que el mostrador acepta hoy.
     *
     * `tarjeta` y `transferencia` siguen en METODOS_PAGO —hay ventas viejas
     * cobradas así y el histórico tiene que poder mostrarlas— pero ya no se
     * ofrecen al cobrar. Quitarlos del enum rompería esas ventas.
     */
    public const METODOS_POS = ['efectivo', 'qr', 'mixto', 'credito'];

    public const ESTADOS = [
        'completada' => 'Completada',
        'anulada' => 'Anulada',
    ];

    protected function casts(): array
    {
        return [
            'cliente_id' => 'integer',
            'user_id' => 'integer',
            'vendida_en' => 'datetime',
            'caja_id' => 'integer',
            'anulada_en' => 'datetime',
            'primera_devolucion_en' => 'datetime',
            // Dinero como decimal:2, nunca float (ver docs/ARQUITECTURA.md §5).
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
            'total_devuelto' => 'decimal:2',
            'costo_total' => 'decimal:2',
            'ganancia' => 'decimal:2',
            'monto_efectivo' => 'decimal:2',
            'monto_qr' => 'decimal:2',
            'qr_cobro_id' => 'integer',
        ];
    }

    /** QR que se mostró al cliente; null si se cobró solo en efectivo. */
    public function qrCobro(): BelongsTo
    {
        return $this->belongsTo(QrCobro::class, 'qr_cobro_id');
    }

    /** URL del respaldo del pago por QR, para abrirlo desde el historial. */
    protected function comprobanteUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->comprobante_qr
            ? Storage::disk('public')->url($this->comprobante_qr)
            : null);
    }

    /** Cliente de la venta; null en la venta al público sin datos. */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** Quién la registró. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(VentaDetalle::class);
    }

    /**
     * Las líneas tal como las ve el cliente en el recibo: el precio final de
     * cada cosa, sin precio de lista ni rebaja (son datos internos, y al
     * vender por encima de la lista confundirían).
     *
     * Las unidades de un producto sin serial vendidas al mismo precio se juntan
     * («3 × Cable HDMI»); las que llevan serial van una por una, con su S/N y
     * su garantía, que es lo que se reclama. Necesita `detalles.unidad` y
     * `detalles.producto` cargados.
     *
     * @return \Illuminate\Support\Collection<int, object{nombre: string, cantidad: int, unitario: float, importe: float, codigo: ?string, serial: ?string, garantia_hasta: ?\Carbon\CarbonInterface, devuelto: bool}>
     */
    public function lineasDelRecibo(): \Illuminate\Support\Collection
    {
        return $this->detalles
            ->groupBy(fn (VentaDetalle $d): string => $d->unidad?->serial === null
                && ! ($d->producto?->tiene_serial ?? true)
                && ! $d->estaDevuelto()
                    ? 'p'.$d->producto_id.'-'.$d->netoEnCentavos()
                    : 'd'.$d->id)
            ->map(function (\Illuminate\Support\Collection $grupo): object {
                /** @var VentaDetalle $primero */
                $primero = $grupo->first();
                $unitario = $primero->netoEnCentavos() / 100;

                return (object) [
                    'nombre' => $primero->producto?->nombre ?? 'Producto',
                    'cantidad' => $grupo->count(),
                    'unitario' => $unitario,
                    'importe' => $unitario * $grupo->count(),
                    // En una línea agrupada no se imprime cada código: son
                    // iguales para el cliente y alargarían el ticket.
                    'codigo' => $grupo->count() === 1 ? $primero->unidad?->codigo_interno : null,
                    'serial' => $primero->unidad?->serial,
                    'garantia_hasta' => $primero->unidad?->garantia_hasta,
                    'devuelto' => $primero->estaDevuelto(),
                ];
            })
            ->values();
    }

    /** Plan de cuotas, si se vendió a crédito. */
    public function credito(): HasOne
    {
        return $this->hasOne(Credito::class);
    }

    /**
     * Órdenes de entrega. Pueden ser varias: tres aparatos que no caben en un
     * viaje, o uno que se reprogramó y otro que ya salió.
     */
    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class)->latest('id');
    }

    protected function esACredito(): Attribute
    {
        return Attribute::get(fn (): bool => $this->metodo_pago === 'credito');
    }

    protected function estaAnulada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'anulada');
    }

    protected function estaCompletada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'completada');
    }

    /** ¿Se devolvió algún aparato de esta venta? */
    protected function tieneDevoluciones(): Attribute
    {
        return Attribute::get(fn (): bool => (float) $this->total_devuelto > 0);
    }

    /**
     * Lo que se cobró en su día, antes de cualquier devolución.
     *
     * `total` guarda el NETO para que los reportes sumen sin tocar ninguna
     * consulta; el importe original se reconstruye aquí.
     */
    protected function totalOriginal(): Attribute
    {
        return Attribute::get(
            fn (): string => number_format(
                (float) $this->total + (float) $this->total_devuelto,
                2, '.', ''
            )
        );
    }

    /** Solo las ventas que cuentan para los reportes. */
    public function scopeCompletadas(Builder $query): Builder
    {
        return $query->where('estado', 'completada');
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('codigo', 'like', "%{$termino}%")
                ->orWhereHas('cliente.persona', fn (Builder $p) => $p->buscar($termino))
                // También por el aparato vendido: en la tienda se pregunta por
                // el serial mucho más que por el número de venta.
                ->orWhereHas('detalles.unidad', fn (Builder $u) => $u->where('serial', 'like', "%{$termino}%")
                    ->orWhere('codigo_interno', 'like', "%{$termino}%"));
        });
    }
}
