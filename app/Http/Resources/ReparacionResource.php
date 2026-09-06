<?php

namespace App\Http\Resources;

use App\Models\Reparacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReparacionResource extends JsonResource
{
    private bool $detalle = false;

    public function conDetalle(): self
    {
        $this->detalle = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        /** @var Reparacion $this */
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'estado' => $this->estado,
            'estado_texto' => Reparacion::ESTADOS[$this->estado] ?? $this->estado,
            'esta_abierta' => $this->esta_abierta,
            'esta_atrasada' => $this->esta_atrasada,

            // Unidad
            'unidad_id' => $this->unidad_id,
            'producto' => $this->whenLoaded('unidad', fn () => $this->unidad->producto?->nombre),
            'serial' => $this->whenLoaded('unidad', fn () => $this->unidad->serial),
            'codigo_interno' => $this->whenLoaded('unidad', fn () => $this->unidad->codigo_interno),

            // Cliente
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente->persona?->nombres.' '.$this->cliente->persona?->apellido_paterno),
            'cliente_id' => $this->cliente_id,

            // Venta
            'venta_id' => $this->venta_id,
            'venta_codigo' => $this->whenLoaded('venta', fn () => $this->venta->codigo),

            // Técnico
            'tecnico' => $this->whenLoaded('tecnico', fn () => $this->tecnico->name),
            'tecnico_id' => $this->tecnico_id,

            // Garantía
            'en_garantia' => $this->en_garantia,
            'garantia_hasta' => $this->garantia_hasta?->toDateString(),

            // Detalle
            'falla_reportada' => $this->falla_reportada,
            'diagnostico' => $this->when($this->detalle, $this->diagnostico),
            'trabajo_realizado' => $this->when($this->detalle, $this->trabajo_realizado),
            'costo' => $this->when($this->detalle || $request->user()?->can('reportes.ver_costos'), $this->costo),
            'notas' => $this->when($this->detalle, $this->notas),

            // Fechas
            'prometida_para' => $this->prometida_para?->toDateString(),
            'recibida_en' => $this->recibida_en?->toIso8601String(),
            'lista_en' => $this->when($this->detalle, $this->lista_en?->toIso8601String()),
            'entregada_en' => $this->when($this->detalle, $this->entregada_en?->toIso8601String()),
            'entregada_a' => $this->when($this->detalle, $this->entregada_a),
            'dias_en_taller' => $this->when($this->detalle, $this->dias_en_taller),

            // Quién
            'recibida_por' => $this->whenLoaded('recibidaPor', fn () => $this->recibidaPor->name),
        ];
    }
}
