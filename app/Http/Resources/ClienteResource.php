<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha comercial de un cliente.
 *
 * El resumen de compras solo viaja a quien puede ver el histórico de ventas:
 * cuánto ha gastado alguien es información de ventas, no de su ficha.
 *
 * @mixin \App\Models\Cliente
 */
class ClienteResource extends JsonResource
{
    /** ¿Ficha completa? Ver la nota de ProductoResource sobre shouldBeStrict. */
    public bool $detalle = false;

    public function conDetalle(): static
    {
        $this->detalle = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $verVentas = $request->user()?->can('ventas.ver') ?? false;

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            // Archivado, no borrado: las ventas que hizo siguen apuntando aquí.
            'archivado' => $this->trashed(),
            'registrado_en' => $this->created_at?->toIso8601String(),
            'persona' => new PersonaResource($this->whenLoaded('persona')),

            'compras' => $this->when($verVentas, fn (): array => [
                'total' => (int) $this->compras_count,
                'importe' => (float) $this->importe_comprado,
                'ultima' => $this->ultima_compra,
            ]),

            // Las últimas compras, para no tener que ir al historial general y
            // buscarlo por nombre. `whenLoaded` y no `when`: con
            // `Model::shouldBeStrict()` activo, tocar una relación que no se
            // cargó lanza excepción en vez de consultarla por lo bajo.
            'ultimas_ventas' => VentaResource::collection($this->whenLoaded('ventas')),
        ];
    }
}
