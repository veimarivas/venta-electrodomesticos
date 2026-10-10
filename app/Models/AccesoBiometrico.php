<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un teléfono registrado para entrar con huella o rostro.
 *
 * Solo guarda el hash SHA-256 de la llave: quien lea la tabla no puede entrar
 * con ella. SHA-256 y no bcrypt porque la llave son 64 caracteres al azar, no
 * una contraseña adivinable; con eso basta y se busca en tiempo constante.
 */
class AccesoBiometrico extends Model
{
    protected $table = 'accesos_biometricos';

    protected $fillable = ['user_id', 'dispositivo_id', 'nombre', 'llave_hash', 'ultimo_uso_en'];

    protected $hidden = ['llave_hash'];

    protected function casts(): array
    {
        return ['ultimo_uso_en' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashDe(string $llave): string
    {
        return hash('sha256', $llave);
    }

    public function aceptaLlave(string $llave): bool
    {
        return hash_equals($this->llave_hash, self::hashDe($llave));
    }
}
