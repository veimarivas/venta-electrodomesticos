<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Proveedor: quien nos vende la mercadería.
 *
 * Lo invertido y la última compra solo viajan a quien puede ver compras. Sin
 * ese permiso queda la ficha de contacto, que es lo que se necesita para
 * llamarle y preguntar por un pedido.
 *
 * @mixin \App\Models\Proveedor
 */
class ProveedorResource extends JsonResource
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
        $verCompras = $request->user()?->can('compras.ver') ?? false;

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'iniciales' => $this->iniciales,
            'nit' => $this->nit,
            'contacto' => $this->contacto,
            'telefono' => $this->telefono,
            'correo' => $this->correo,
            'direccion' => $this->direccion,
            'notas' => $this->notas,
            'activo' => (bool) $this->activo,
            'archivado' => $this->trashed(),

            'compras' => $this->when($verCompras, fn (): array => [
                // Solo las recepcionadas: un borrador todavía no es dinero
                // puesto ni mercadería que haya entrado al almacén.
                'total' => (int) $this->compras_count,
                'invertido' => (float) $this->invertido,
                'ultima' => $this->ultima_compra,
                'unidades' => (int) $this->unidades_compradas,
            ]),

            'ultimas_compras' => CompraResource::collection($this->whenLoaded('compras')),
        ];
    }
}
