<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un pago hecho al proveedor, respaldado con su boucher.
 *
 * Una compra se paga en varios plazos: cada `CompraPago` es una cuota de ese
 * total, con la imagen del comprobante y el monto de esa vez. La suma de los
 * pagos frente al total de la compra es lo que dice cuánto falta por pagar.
 */
#[Fillable([
    'compra_id',
    'user_id',
    'monto',
    'imagen',
    'fecha',
    'notas',
])]
class PagoCompra extends Model
{
    /** @use HasFactory<\Database\Factories\PagoCompraFactory> */
    use HasFactory;

    protected $table = 'compra_pagos';

    protected function casts(): array
    {
        return [
            // Dinero como decimal:2, nunca float (ver docs/PLAN.md §9).
            'monto' => 'decimal:2',
            'fecha' => 'date',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}