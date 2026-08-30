<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un pago imputado a una cuota.
 *
 * Una entrega de dinero que alcanzó para cuota y media son **dos filas** con
 * el mismo `recibo`; la app las agrupa por ese número, igual que el panel.
 *
 * @mixin \App\Models\PagoCredito
 */
class PagoCreditoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'recibo' => $this->recibo,
            'monto' => (float) $this->monto,
            'metodo_pago' => $this->metodo_pago,
            'metodo_pago_texto' => \App\Models\PagoCredito::METODOS_PAGO[$this->metodo_pago]
                ?? $this->metodo_pago,
            'cuota_numero' => $this->cuota?->numero,
            'pagado_en' => $this->pagado_en?->toIso8601String(),
            'cobrado_por' => $this->user?->name,
            'notas' => $this->notas,
        ];
    }
}
