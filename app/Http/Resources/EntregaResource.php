<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Entrega para la app de quien reparte.
 *
 * Lleva la dirección, la referencia y el teléfono en el propio objeto: quien
 * va en el camión no puede estar pidiendo un segundo endpoint por cada parada.
 *
 * @mixin \App\Models\Entrega
 */
class EntregaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'estado' => $this->estado,
            'estado_texto' => \App\Models\Entrega::ESTADOS[$this->estado] ?? $this->estado,
            // Se manda calculado y no se deduce en el móvil: la fecha del
            // teléfono puede estar mal, y «atrasada» es una decisión del
            // servidor.
            'esta_atrasada' => $this->esta_atrasada,
            'esta_abierta' => $this->esta_abierta,

            'direccion' => $this->direccion,
            'referencia' => $this->referencia,
            'telefono_contacto' => $this->telefono_contacto,
            'programada_para' => $this->programada_para?->toDateString(),
            'con_instalacion' => $this->con_instalacion,

            'venta_id' => $this->venta_id,
            'venta_codigo' => $this->venta?->codigo,
            'cliente' => $this->cliente?->persona?->nombre_completo ?? 'Público general',

            'repartidor_id' => $this->repartidor_id,
            'repartidor' => $this->repartidor?->name,

            'salio_en' => $this->salio_en?->toIso8601String(),
            'entregada_en' => $this->entregada_en?->toIso8601String(),
            'instalada_en' => $this->instalada_en?->toIso8601String(),
            'recibida_por' => $this->recibida_por,
            'motivo_fallo' => $this->motivo_fallo,
            'notas' => $this->notas,

            // Qué se lleva, con serial: es lo que se comprueba antes de cargar
            // el camión.
            'aparatos' => $this->whenLoaded('detalles', fn (): array => $this->detalles
                ->map(fn ($detalle): array => [
                    'producto' => $detalle->ventaDetalle?->producto?->nombre,
                    'serial' => $detalle->ventaDetalle?->unidad?->serial
                        ?: $detalle->ventaDetalle?->unidad?->codigo_interno,
                    // Un aparato devuelto deja de viajar aunque su fila siga
                    // en la orden, y quien carga tiene que verlo.
                    'vigente' => $detalle->venta_detalle_activo_id !== null,
                ])
                ->values()
                ->all()),
        ];
    }
}
