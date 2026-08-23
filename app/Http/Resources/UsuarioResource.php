<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Usuario tal como lo ve la app.
 *
 * Deliberadamente NO expone el hash de la contraseña ni los secretos de 2FA:
 * el modelo los oculta, pero un Resource explícito hace que añadir una columna
 * nueva no la filtre por accidente.
 *
 * @mixin \App\Models\User
 */
class UsuarioResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->name,
            'usuario' => $this->name,
            'correo' => $this->email,
            'activo' => (bool) $this->is_active,
            'avatar' => $this->avatar_url,
            'roles' => $this->getRoleNames()->all(),

            // El admin no tiene permisos asignados: los recibe todos por
            // Gate::before (ver AppServiceProvider). Si se devolviera
            // getAllPermissions() a secas, la app vería una lista vacía y
            // escondería todas las pantallas justo al usuario que puede todo.
            'es_admin' => $this->hasRole('admin'),
            'permisos' => $this->hasRole('admin')
                ? \Spatie\Permission\Models\Permission::orderBy('name')->pluck('name')->all()
                : $this->getAllPermissions()->pluck('name')->sort()->values()->all(),
            'persona' => $this->whenLoaded('persona', fn (): array => [
                'nombre_completo' => $this->persona->nombre_completo,
                'carnet' => $this->persona->carnet,
                'celular' => $this->persona->celular,
            ]),
        ];
    }
}
