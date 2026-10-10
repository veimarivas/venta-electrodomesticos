<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Una cotización del dólar: oficial (BCB) o paralela. */
class CotizacionDolar extends Model
{
    public const OFICIAL = 'oficial';

    public const PARALELO = 'paralelo';

    protected $table = 'cotizaciones_dolar';

    protected $fillable = ['tipo', 'compra', 'venta', 'fuente', 'publicado_en'];

    protected function casts(): array
    {
        return [
            'compra' => 'decimal:4',
            'venta' => 'decimal:4',
            'publicado_en' => 'datetime',
        ];
    }
}
