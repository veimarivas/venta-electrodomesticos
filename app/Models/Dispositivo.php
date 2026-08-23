<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Teléfono registrado para recibir notificaciones push.
 *
 * El token lo emite Firebase por instalación de la app y puede rotar, así que
 * las altas se hacen con updateOrCreate sobre el token: si el mismo teléfono
 * vuelve a registrarse, se actualiza la fila en lugar de crear otra.
 */
#[Fillable(['user_id', 'token', 'plataforma', 'nombre_dispositivo', 'ultimo_uso_en'])]
class Dispositivo extends Model
{
    /** @use HasFactory<\Database\Factories\DispositivoFactory> */
    use HasFactory;

    protected $table = 'dispositivos';

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'ultimo_uso_en' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
