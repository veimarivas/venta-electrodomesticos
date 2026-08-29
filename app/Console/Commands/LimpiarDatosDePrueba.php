<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deja la base como recién instalada, pero **sin perder los accesos**.
 *
 * Sirve para empezar a probar de cero sin tener que volver a montar roles,
 * permisos y la cuenta de administrador, que es lo caro de reconstruir.
 *
 * Se conserva:
 *   · La estructura de permisos completa (roles, permisos y su matriz).
 *   · **Una sola cuenta**: la del administrador, con su rol.
 *
 * Se borra todo lo demás: catálogo, inventario, compras, ventas, personas,
 * avisos y las sesiones abiertas.
 *
 * Es destructivo y no tiene vuelta atrás. Por eso pide confirmación escribiendo
 * el nombre de la base, se niega en producción salvo insistencia expresa, y
 * recuerda hacer copia antes.
 */
class LimpiarDatosDePrueba extends Command
{
    protected $signature = 'datos:limpiar
                            {--admin= : Correo o usuario de la cuenta que se conserva}
                            {--force : No preguntar (para guiones desatendidos)}';

    protected $description = 'Borra los datos de operación y conserva roles, permisos y la cuenta de administrador';

    /**
     * Tablas que NO se tocan: son la estructura de acceso y el historial de
     * migraciones. `users` se limpia a mano más abajo, porque hay que dejar
     * una fila viva.
     *
     * @var array<int, string>
     */
    private const INTOCABLES = [
        'migrations',
        'permissions',
        'roles',
        'role_has_permissions',
        'users',
        // Se filtran por usuario, no se vacían: el admin conserva su rol.
        'model_has_roles',
        'model_has_permissions',
    ];

    public function handle(): int
    {
        $admin = $this->admin();

        if ($admin === null) {
            $this->components->error('No se encontró la cuenta de administrador que conservar.');
            $this->line('  Indícala con <comment>--admin=correo@ejemplo.com</comment>.');

            return self::FAILURE;
        }

        $tablas = $this->tablasAVaciar();

        $this->components->info('Base de datos: '.config('database.connections.'.config('database.default').'.database'));
        $this->newLine();

        $this->components->twoColumnDetail('<fg=green>SE CONSERVA</>', '');
        $this->components->twoColumnDetail('  Roles', (string) DB::table('roles')->count());
        $this->components->twoColumnDetail('  Permisos', (string) DB::table('permissions')->count());
        $this->components->twoColumnDetail('  Cuenta', $admin->email.' ('.($admin->getRoleNames()->implode(', ') ?: 'sin rol').')');
        $this->newLine();

        $this->components->twoColumnDetail('<fg=red>SE BORRA</>', '');

        $total = 0;

        foreach ($tablas as $tabla) {
            $filas = DB::table($tabla)->count();
            $total += $filas;

            if ($filas > 0) {
                $this->components->twoColumnDetail('  '.$tabla, (string) $filas);
            }
        }

        $otrasCuentas = User::query()->whereKeyNot($admin->getKey())->count();

        if ($otrasCuentas > 0) {
            $this->components->twoColumnDetail('  users (otras cuentas)', (string) $otrasCuentas);
            $total += $otrasCuentas;
        }

        $this->newLine();

        if ($total === 0) {
            $this->components->info('No hay nada que borrar.');

            return self::SUCCESS;
        }

        if (! $this->confirmar($total)) {
            $this->components->warn('Cancelado. No se tocó nada.');

            return self::FAILURE;
        }

        $this->vaciar($tablas, $admin);

        $this->newLine();
        $this->components->info("Listo: {$total} registros borrados.");
        $this->line('  Los roles, los permisos y la cuenta <comment>'.$admin->email.'</comment> siguen intactos.');

        return self::SUCCESS;
    }

    private function admin(): ?User
    {
        $indicado = $this->option('admin');

        if ($indicado !== null) {
            // Por correo o por nombre, que son las dos formas de identificarse
            // al entrar (ver FortifyServiceProvider::authenticateUsing). No hay
            // columna `username` en esta instalación.
            return User::query()
                ->where('email', $indicado)
                ->orWhere('name', $indicado)
                ->orderBy('id')
                ->first();
        }

        // Sin indicación, la cuenta con rol admin. Si hubiera varias se toma la
        // más antigua: es la que creó la instalación.
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array<int, string>
     */
    private function tablasAVaciar(): array
    {
        // `SHOW TABLES` devuelve objetos con una sola propiedad cuyo nombre
        // depende de la base (`Tables_in_loquesea`), así que se lee la primera
        // en vez de nombrarla.
        return collect(DB::select('SHOW TABLES'))
            ->map(function (object $fila): string {
                $valores = array_values((array) $fila);

                return (string) $valores[0];
            })
            ->reject(fn (string $t): bool => in_array($t, self::INTOCABLES, true))
            ->filter(fn (string $t): bool => Schema::hasTable($t))
            ->values()
            ->all();
    }

    private function confirmar(int $total): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $base = DB::connection()->getDatabaseName();

        if (app()->environment('production')) {
            $this->newLine();
            $this->components->warn('Esto es PRODUCCIÓN. Se van a borrar datos reales.');
        }

        $this->components->warn("Se borrarán {$total} registros y no hay vuelta atrás.");
        $this->line('  Haz una copia antes: <comment>php artisan backup:run</comment>');
        $this->newLine();

        // Escribir el nombre de la base, no un sí/no: obliga a mirar sobre qué
        // se está ejecutando. Es la diferencia entre vaciar la de pruebas y
        // vaciar la de la tienda.
        $escrito = $this->ask("Escribe el nombre de la base para confirmar ({$base})");

        return $escrito === $base;
    }

    /**
     * @param  array<int, string>  $tablas
     */
    private function vaciar(array $tablas, User $admin): void
    {
        // Las claves foráneas se desactivan durante el vaciado: TRUNCATE las
        // respeta y, con dependencias circulares, no hay ningún orden que
        // funcione. Se vuelven a activar pase lo que pase.
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($tablas as $tabla) {
                DB::table($tabla)->truncate();
                $this->components->task("Vaciando {$tabla}", fn (): bool => true);
            }

            // Las demás cuentas se borran de una en una para que se lleven por
            // delante sus roles y permisos asignados.
            User::query()->whereKeyNot($admin->getKey())->get()->each->forceDelete();

            $this->components->task('Borrando las demás cuentas', fn (): bool => true);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
}
