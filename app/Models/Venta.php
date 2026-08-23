<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    'codigo',
    'vendida_en',
    'subtotal',
    'descuento',
    'total',
    'costo_total',
    'ganancia',
    'metodo_pago',
    'qr_cobro_id',
    'monto_efectivo',
    'monto_qr',
    'comprobante_qr',
    'estado',
    'anulada_en',
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
    public const METODOS_POS = ['efectivo', 'qr', 'mixto'];

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
            'anulada_en' => 'datetime',
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'subtotal' => 'decimal:2',
            'descuento' => 'decimal:2',
            'total' => 'decimal:2',
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

    protected function estaAnulada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'anulada');
    }

    protected function estaCompletada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->estado === 'completada');
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
