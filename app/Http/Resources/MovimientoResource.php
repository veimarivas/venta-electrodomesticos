<?php

namespace App\Http\Resources;

use App\Models\MovimientoInventario;
use App\Models\Unidad;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Una línea del kardex de una unidad, tal como la pinta el teléfono.
 *
 * El kardex se lee de arriba abajo como una historia, así que además del dato
 * crudo viaja el **texto** de cada estado y del documento de origen: la app no
 * tiene la tabla de estados ni sabe traducir `App\Models\Compra` a «Compra
 * C-2608-0007`, y hacer que lo aprendiera sería duplicar en Dart un catálogo
 * que ya vive en el modelo.
 *
 * @mixin \App\Models\MovimientoInventario
 */
class MovimientoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'tipo_texto' => MovimientoInventario::TIPOS[$this->tipo] ?? $this->tipo,
            'estado_anterior' => $this->estado_anterior,
            'estado_anterior_texto' => $this->estado_anterior === null
                ? null
                : (Unidad::ESTADOS[$this->estado_anterior] ?? $this->estado_anterior),
            'estado_nuevo' => $this->estado_nuevo,
            'estado_nuevo_texto' => Unidad::ESTADOS[$this->estado_nuevo] ?? $this->estado_nuevo,
            'notas' => $this->notas,
            // Quién lo hizo. Puede faltar: los seeders y los comandos de
            // consola escriben kardex sin usuario autenticado.
            'usuario' => $this->whenLoaded('user', fn () => $this->user?->name),
            'origen' => $this->whenLoaded('origen', fn () => $this->textoDeOrigen()),
            'ocurrido_en' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Nombre legible del documento que provocó el movimiento.
     *
     * Un ajuste manual no tiene ninguno, y eso es información: significa que
     * alguien tocó el inventario a mano en vez de que lo moviera una compra o
     * una venta.
     */
    private function textoDeOrigen(): ?string
    {
        $origen = $this->origen;

        if ($origen === null) {
            return null;
        }

        return match ($origen::class) {
            \App\Models\Compra::class => "Compra {$origen->codigo}",
            \App\Models\Venta::class => "Venta {$origen->codigo}",
            default => null,
        };
    }
}
