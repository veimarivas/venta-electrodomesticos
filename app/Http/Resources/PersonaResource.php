<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Datos personales, compartidos por trabajadores y clientes.
 *
 * Va anidado dentro de ellos y nunca suelto: una persona por sí sola no es
 * nada que la app tenga que listar, y exponer el padrón entero por API sería
 * dar más de lo que ninguna pantalla necesita.
 *
 * @mixin \App\Models\Persona
 */
class PersonaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre_completo' => $this->nombre_completo,
            'nombres' => $this->nombres,
            'apellidos' => $this->apellidos,
            'iniciales' => $this->iniciales,
            'carnet' => $this->carnet,
            'celular' => $this->celular,
            'correo' => $this->correo,
            'direccion' => $this->direccion,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'edad' => $this->edad,
        ];
    }
}
