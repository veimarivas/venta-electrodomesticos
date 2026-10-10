<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'avatar_path', 'is_active', 'persona_id'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Avatar del usuario, con fallback a la imagen de la plantilla.
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(fn (): string => $this->avatar_path
            ? Storage::disk('public')->url($this->avatar_path)
            : asset('assets/images/users/user-dummy-img.jpg'));
    }

    /**
     * Datos personales asociados a esta cuenta (relación 1 a 1).
     */
    public function persona(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    /**
     * Teléfonos registrados para recibir notificaciones push.
     */
    public function dispositivos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Dispositivo::class);
    }

    /**
     * Teléfonos registrados para entrar con huella o rostro.
     */
    public function accesosBiometricos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccesoBiometrico::class);
    }

    /**
     * Cambiar la contraseña o bloquear la cuenta borra los teléfonos con
     * huella: quien la cambia porque se la vieron no puede dejar abierta la
     * puerta de un teléfono que ya no tiene. Cada uno vuelve a activarlo al
     * entrar con la contraseña nueva.
     */
    protected static function booted(): void
    {
        static::updated(function (User $usuario): void {
            if ($usuario->wasChanged('password') || ($usuario->wasChanged('is_active') && ! $usuario->is_active)) {
                $usuario->accesosBiometricos()->delete();
            }
        });
    }
}
