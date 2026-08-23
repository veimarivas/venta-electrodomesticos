<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ficha laboral de un trabajador.
 *
 * La baja es un estado, no un borrado: quien ya no trabaja aquí sigue en el
 * listado con su fecha y su motivo, porque las ventas y compras que registró
 * apuntan a él.
 *
 * @mixin \App\Models\Trabajador
 */
class TrabajadorResource extends JsonResource
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
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'cargo' => $this->cargo?->nombre,
            'cargo_id' => $this->cargo_id,
            'fecha_ingreso' => $this->fecha_ingreso?->toDateString(),
            'fecha_baja' => $this->fecha_baja?->toDateString(),
            'motivo_baja' => $this->motivo_baja,
            'esta_activo' => $this->esta_activo,
            'antiguedad' => $this->antiguedad,
            'persona' => new PersonaResource($this->whenLoaded('persona')),

            // ---- Solo en la ficha ------------------------------------------
            // Si tiene cuenta de acceso y con qué rol entra: es lo que se
            // pregunta cuando alguien «no puede entrar al sistema».
            'cuenta' => $this->when(
                $this->detalle && ($request->user()?->can('usuarios.ver') ?? false),
                fn (): ?array => $this->persona?->user === null ? null : [
                    // `name` es el nombre de usuario con el que entra
                    // («jperezlopez»), no el nombre de pila.
                    'usuario' => $this->persona->user->name,
                    'correo' => $this->persona->user->email,
                    'activa' => (bool) $this->persona->user->is_active,
                    'roles' => $this->persona->user->getRoleNames()->all(),
                    'ultimo_acceso' => $this->persona->user->last_login_at?->toIso8601String(),
                ]
            ),

            // Ventas que registró. Solo a quien puede ver el histórico: es el
            // rendimiento de una persona, no un dato de ficha.
            'ventas' => $this->when(
                $this->detalle && ($request->user()?->can('ventas.ver') ?? false),
                fn (): array => [
                    'total' => (int) $this->ventas_registradas,
                    'importe' => (float) $this->importe_vendido,
                ]
            ),
        ];
    }
}
