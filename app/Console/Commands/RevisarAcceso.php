<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dice por qué una cuenta no puede entrar, y permite devolverle el acceso.
 *
 * Existe porque «no puedo entrar» tiene cinco causas distintas que se ven
 * iguales desde el formulario: la cuenta no existe, está desactivada, la
 * contraseña no es la que se cree, el intento está bloqueado por reintentos, o
 * la caché de permisos quedó vieja. Adivinar cuál es desde fuera cuesta más que
 * preguntárselo al sistema.
 *
 * El cambio de contraseña se pide **por teclado y oculto**: pasarla como
 * argumento la dejaría en el historial del intérprete de órdenes y en la lista
 * de procesos, donde la ve cualquiera con acceso al servidor.
 */
class RevisarAcceso extends Command
{
    protected $signature = 'usuario:acceso
                            {identificador : Correo o nombre de usuario}
                            {--reset : Poner una contraseña nueva}
                            {--activar : Reactivar la cuenta si está desactivada}';

    protected $description = 'Diagnostica por qué una cuenta no puede entrar y permite devolverle el acceso';

    public function handle(): int
    {
        // El login normaliza así el identificador antes de buscar
        // (FortifyServiceProvider::registerAuthentication). Se repite igual
        // para que este diagnóstico busque exactamente lo mismo que el
        // formulario, y no encuentre cuentas que el login no encontraría.
        $identificador = Str::lower(trim($this->argument('identificador')));

        $usuario = User::where('email', $identificador)->first()
            ?? User::where('name', $identificador)->orderBy('id')->first();

        if ($usuario === null) {
            $this->components->error("Ninguna cuenta responde a «{$identificador}».");
            $this->line('  El login busca por correo o por nombre, y pasa lo escrito a minúsculas.');
            $this->newLine();
            $this->cuentasDisponibles();

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>CUENTA</>', '');
        $this->components->twoColumnDetail('  Correo', $usuario->email);
        $this->components->twoColumnDetail('  Nombre', $usuario->name);
        $this->components->twoColumnDetail(
            '  Activa',
            $usuario->is_active ? '<fg=green>sí</>' : '<fg=red>NO — no podrá entrar</>'
        );
        $this->components->twoColumnDetail('  Roles', $usuario->getRoleNames()->implode(', ') ?: '<fg=red>ninguno</>');
        $this->components->twoColumnDetail('  Último acceso', $usuario->last_login_at?->diffForHumans() ?? 'nunca');
        $this->components->twoColumnDetail(
            '  Contraseña',
            filled($usuario->password) ? 'establecida' : '<fg=red>VACÍA — no podrá entrar</>'
        );

        $this->newLine();
        $this->components->twoColumnDetail('<fg=cyan>PERMISOS</>', '');
        $this->components->twoColumnDetail('  Es admin', $usuario->hasRole('admin') ? 'sí (acceso total)' : 'no');
        $this->components->twoColumnDetail('  Puede entrar al panel', $usuario->can('reportes.ver') ? 'sí' : 'no');

        $this->bloqueoPorIntentos($usuario);

        if ($this->option('activar') && ! $usuario->is_active) {
            $usuario->update(['is_active' => true]);
            $this->newLine();
            $this->components->info('Cuenta reactivada.');
        }

        if ($this->option('reset')) {
            return $this->cambiarClave($usuario);
        }

        $this->newLine();
        $this->line('  Para poner una contraseña nueva: <comment>php artisan usuario:acceso '
            .$usuario->email.' --reset</comment>');

        return self::SUCCESS;
    }

    /**
     * Fortify bloquea tras varios intentos fallidos y el aviso se parece
     * bastante a «contraseña incorrecta». El contador vive en la caché.
     */
    private function bloqueoPorIntentos(User $usuario): void
    {
        $clave = Str::transliterate(Str::lower($usuario->email).'|127.0.0.1');
        $intentos = RateLimiter::attempts($clave);

        if ($intentos > 0) {
            $this->newLine();
            $this->components->warn("Hay {$intentos} intentos fallidos registrados.");
            $this->line('  Si el mensaje habla de esperar, límpialo con <comment>php artisan cache:clear</comment>.');
        }
    }

    private function cambiarClave(User $usuario): int
    {
        $this->newLine();

        // `secret()` no muestra lo que se teclea y, sobre todo, mantiene la
        // contraseña fuera del historial y de la lista de procesos.
        $nueva = $this->secret('Contraseña nueva (no se muestra)');

        if ($nueva === null || strlen($nueva) < 8) {
            $this->components->error('La contraseña tiene que tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        if ($nueva !== $this->secret('Repítela')) {
            $this->components->error('No coinciden. No se cambió nada.');

            return self::FAILURE;
        }

        $usuario->forceFill([
            'password' => Hash::make($nueva),
            'is_active' => true,
        ])->save();

        // Los permisos se cachean; tras tocar la cuenta conviene refrescarlos
        // para que el primer inicio de sesión no lea una copia vieja.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->components->info('Contraseña cambiada y cuenta activa.');
        $this->line('  Entra con <comment>'.$usuario->email.'</comment> y la contraseña que acabas de poner.');

        return self::SUCCESS;
    }

    private function cuentasDisponibles(): void
    {
        $cuentas = User::query()->orderBy('id')->get();

        if ($cuentas->isEmpty()) {
            $this->components->error('No hay NINGUNA cuenta en la base.');

            return;
        }

        $this->components->twoColumnDetail('<fg=cyan>CUENTAS QUE EXISTEN</>', '');

        foreach ($cuentas as $c) {
            $this->components->twoColumnDetail(
                '  '.$c->email,
                ($c->getRoleNames()->implode(', ') ?: 'sin rol').($c->is_active ? '' : ' <fg=red>[inactiva]</>')
            );
        }
    }
}
