<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class RecordLastLogin
{
    /**
     * Deja constancia del último acceso. Sirve para detectar cuentas de
     * vendedores que ya no se usan y conviene desactivar.
     */
    public function handle(Login $event): void
    {
        $event->user->forceFill([
            'last_login_at' => now(),
        ])->saveQuietly();
    }
}
