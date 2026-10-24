<?php

namespace App\Livewire\Personal;

use App\Models\User;
use App\Support\CuentaDeTrabajador;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reiniciar el acceso de quien olvidó su usuario o su contraseña.
 *
 * Modal reutilizable (Usuarios y Vendedores lo abren con el evento
 * `reiniciar-acceso`). Primero confirma; al aceptar, la cuenta vuelve a la
 * convención de la tienda —usuario = inicial y apellidos, contraseña = carnet—,
 * se cierran sus sesiones y al entrar se le pide una contraseña propia. El
 * modal termina enseñando el usuario y la contraseña temporal para dárselos
 * (o mandárselos por WhatsApp).
 */
class ReiniciarAcceso extends Component
{
    public ?int $userId = null;

    public string $nombre = '';

    public string $usuario = '';

    public string $temporal = '';

    public ?string $celular = null;

    /** false = preguntando; true = ya reiniciado, enseñando los datos. */
    public bool $hecho = false;

    #[On('reiniciar-acceso')]
    public function abrir(int $userId): void
    {
        abort_unless(auth()->user()?->can('usuarios.editar') ?? false, 403);

        $cuenta = User::with('persona')->findOrFail($userId);

        if ($cuenta->is(auth()->user())) {
            $this->dispatch('toast', tipo: 'error', mensaje: 'Tu propia contraseña se cambia desde tu perfil.');

            return;
        }

        if ($cuenta->persona === null || trim((string) $cuenta->persona->carnet) === '') {
            $this->dispatch('toast', tipo: 'error', mensaje: 'La cuenta no está vinculada a una persona con carnet: edítala y ponle una contraseña.');

            return;
        }

        $this->userId = $cuenta->id;
        $this->nombre = $cuenta->persona->nombre_completo;
        $this->usuario = app(CuentaDeTrabajador::class)->usuario($cuenta->persona, $cuenta->id);
        $this->temporal = '';
        $this->celular = $cuenta->persona->celular ?: $cuenta->phone;
        $this->hecho = false;

        $this->dispatch('abrir-modal-reiniciar-acceso');
    }

    public function confirmar(): void
    {
        abort_unless(auth()->user()?->can('usuarios.editar') ?? false, 403);

        $cuenta = User::with('persona')->find($this->userId);

        if ($cuenta === null || $cuenta->persona === null || $cuenta->is(auth()->user())) {
            $this->dispatch('cerrar-modal-reiniciar-acceso');

            return;
        }

        $servicio = app(CuentaDeTrabajador::class);
        $servicio->reiniciar($cuenta, $cuenta->persona);

        $this->usuario = $cuenta->fresh()->name;
        $this->temporal = $servicio->passwordDe($cuenta->persona);
        $this->hecho = true;

        $this->dispatch('acceso-reiniciado');
        $this->dispatch('toast', tipo: 'success', mensaje: "Acceso de {$this->nombre} reiniciado.");
    }

    /** Enlace a WhatsApp con los datos para el trabajador, si tiene celular. */
    public function enlaceWhatsapp(): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $this->celular);

        if ($digitos === '' || ! $this->hecho) {
            return null;
        }

        $numero = strlen($digitos) === 8 ? '591'.$digitos : $digitos;
        $texto = "Hola {$this->nombre}. Tu acceso a ".config('app.nombre_comercial')." quedó así:\n"
            ."Usuario: {$this->usuario}\nContraseña temporal: tu carnet\n"
            .'Al entrar te pedirá una contraseña nueva.';

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($texto);
    }

    public function render(): View
    {
        return view('livewire.personal.reiniciar-acceso');
    }
}
