<?php

namespace App\Support;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Alta y reinicio de la cuenta de acceso de un trabajador.
 *
 * La cuenta se cuelga de la persona, no del trabajador: la clave foránea es
 * users.persona_id, que ya existía. No se agrega ninguna relación nueva; el
 * camino es trabajador → persona → user.
 *
 * Convención pedida para el alta y para cada reinicio:
 *   · usuario    = inicial de los nombres + apellido paterno + apellido materno
 *                  ("Juan Carlos Pérez Gómez" → "jperezgomez")
 *   · contraseña = el carnet de la persona
 */
class CuentaDeTrabajador
{
    /**
     * Dominio de respaldo para el correo de acceso.
     *
     * users.email es NOT NULL y UNIQUE, y el inicio de sesión de Fortify va
     * por correo (config/fortify.php: 'username' => 'email'), así que toda
     * cuenta necesita uno. Cuando la persona no tiene correo registrado se
     * arma uno interno con su nombre de usuario. Es una dirección de acceso,
     * no un buzón: no existe fuera del sistema y no recibe correo.
     */
    public const DOMINIO_INTERNO = 'electronicahogar.local';

    /** Rol que se propone al crear la cuenta; el modal permite cambiarlo. */
    public const ROL_POR_DEFECTO = 'vendedor';

    /**
     * Nombre de usuario que le corresponde a una persona, ya libre de
     * colisiones. Si otro usuario ya lo ocupa se le añade un correlativo
     * ("jperezgomez", "jperezgomez2", ...).
     */
    public function usuario(Persona $persona, ?int $ignorarUserId = null): string
    {
        $base = $this->base($persona);
        $candidato = $base;
        $sufijo = 1;

        while ($this->usuarioOcupado($candidato, $ignorarUserId)) {
            $candidato = $base.(++$sufijo);
        }

        return $candidato;
    }

    /**
     * Correo con el que iniciará sesión. Se usa el de la persona si lo tiene;
     * si no, uno interno derivado del nombre de usuario.
     */
    public function correo(Persona $persona, string $usuario, ?int $ignorarUserId = null): string
    {
        $propio = Str::lower(trim((string) $persona->correo));

        if ($propio !== '' && ! $this->correoOcupado($propio, $ignorarUserId)) {
            return $propio;
        }

        $base = $usuario.'@'.self::DOMINIO_INTERNO;
        $candidato = $base;
        $sufijo = 1;

        while ($this->correoOcupado($candidato, $ignorarUserId)) {
            $candidato = $usuario.(++$sufijo).'@'.self::DOMINIO_INTERNO;
        }

        return $candidato;
    }

    /**
     * La contraseña es el carnet, tal cual. El cast 'hashed' del modelo User
     * la cifra al asignarla, así que nunca se guarda en claro.
     */
    public function passwordDe(Persona $persona): string
    {
        return (string) $persona->carnet;
    }

    /**
     * Crea la cuenta y le asigna el rol indicado.
     *
     * Cuenta y rol van en una transacción: un usuario sin rol no puede entrar
     * a ninguna pantalla, y sería peor que no haberlo creado.
     */
    public function crear(Persona $persona, string $rol): User
    {
        return DB::transaction(function () use ($persona, $rol): User {
            $usuario = $this->usuario($persona);

            $user = User::create([
                'persona_id' => $persona->id,
                'name' => $usuario,
                'email' => $this->correo($persona, $usuario),
                'password' => $this->passwordDe($persona),
                'phone' => $persona->celular,
                'is_active' => true,
            ]);

            $user->syncRoles([$rol]);

            return $user;
        });
    }

    /**
     * Devuelve la contraseña al carnet y realinea el nombre de usuario con la
     * convención, por si los datos de la persona cambiaron desde el alta.
     * El correo NO se toca: es la credencial con la que la cuenta ya entra y
     * cambiarla en un reinicio de contraseña dejaría fuera al trabajador.
     */
    public function reiniciar(User $user, Persona $persona): void
    {
        $user->forceFill([
            'name' => $this->usuario($persona, $user->id),
            'password' => $this->passwordDe($persona),
        ])->save();
    }

    /**
     * Parte fija del nombre de usuario, sin acentos, espacios ni signos.
     * "Peña Ríos" → "penarios".
     */
    private function base(Persona $persona): string
    {
        $inicial = Str::substr($this->soloLetras($persona->nombres), 0, 1);

        $base = $inicial
            .$this->soloLetras($persona->apellido_paterno)
            .$this->soloLetras($persona->apellido_materno);

        // Una persona sin nombres ni apellidos utilizables no debería existir
        // (personas los valida), pero un usuario vacío sería inservible: se
        // cae al carnet, que siempre está.
        return $base !== '' ? $base : 'u'.$persona->carnet;
    }

    private function soloLetras(?string $texto): string
    {
        return Str::lower((string) preg_replace('/[^a-z]/i', '', Str::ascii((string) $texto)));
    }

    private function usuarioOcupado(string $usuario, ?int $ignorarUserId): bool
    {
        return User::query()
            ->where('name', $usuario)
            ->when($ignorarUserId !== null, fn ($q) => $q->whereKeyNot($ignorarUserId))
            ->exists();
    }

    private function correoOcupado(string $correo, ?int $ignorarUserId): bool
    {
        return User::query()
            ->where('email', $correo)
            ->when($ignorarUserId !== null, fn ($q) => $q->whereKeyNot($ignorarUserId))
            ->exists();
    }
}
