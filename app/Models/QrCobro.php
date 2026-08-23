<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * QR de cobro que la tienda muestra al cliente.
 *
 * La imagen la emite el banco con una fecha límite; pasada esa fecha el pago
 * no llega. El POS solo ofrece los vigentes, así que caducar equivale a
 * desaparecer del mostrador sin que nadie tenga que acordarse de desactivarlo.
 */
#[Fillable([
    'nombre',
    'banco',
    'titular',
    'imagen',
    'fecha_limite',
    'activo',
    'notas',
])]
class QrCobro extends Model
{
    /** @use HasFactory<\Database\Factories\QrCobroFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'qrs_cobro';

    protected function casts(): array
    {
        return [
            'fecha_limite' => 'date',
            'activo' => 'boolean',
        ];
    }

    /** Ventas cobradas con este QR. */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'qr_cobro_id');
    }

    /** URL pública de la imagen, lista para el <img>. */
    protected function imagenUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->imagen
            ? asset('storage/' . $this->imagen)
            : null);
    }

    protected function estaVigente(): Attribute
    {
        return Attribute::get(fn (): bool => $this->activo
            && $this->fecha_limite !== null
            && ! $this->fecha_limite->isBefore(Carbon::today()));
    }

    /** Días que le quedan; negativo si ya caducó. */
    protected function diasRestantes(): Attribute
    {
        return Attribute::get(fn (): int => $this->fecha_limite === null
            ? 0
            : (int) Carbon::today()->diffInDays($this->fecha_limite, false));
    }

    /**
     * Los únicos que el POS puede mostrar: activos y sin caducar. El día de la
     * fecha límite todavía cuenta — el banco lo acepta hasta el cierre.
     */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->where('activo', true)
            ->whereDate('fecha_limite', '>=', Carbon::today());
    }

    public function scopeBuscar(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($termino) {
            $q->where('nombre', 'like', "%{$termino}%")
                ->orWhere('banco', 'like', "%{$termino}%")
                ->orWhere('titular', 'like', "%{$termino}%");
        });
    }
}
