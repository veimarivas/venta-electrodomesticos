<?php

namespace Database\Seeders;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CargoSeeder::class,
            CategoriaSeeder::class,
            MarcaSeeder::class,
            ProductoSeeder::class,
        ]);

        $admin = User::firstOrCreate(
            ['email' => 'admin@electronicahogar.test'],
            [
                'name' => 'Administrador',
                'password' => 'password',
                'is_active' => true,
            ]
        );

        $admin->syncRoles('admin');
        $this->vincularPersona($admin, 'Administrador del Sistema');

        $vendedor = User::firstOrCreate(
            ['email' => 'vendedor@electronicahogar.test'],
            [
                'name' => 'Vendedor de Prueba',
                'password' => 'password',
                'is_active' => true,
            ]
        );

        $vendedor->syncRoles('vendedor');
        $this->vincularPersona($vendedor, 'Vendedor de Prueba');
    }

    /**
     * Todo usuario necesita su ficha personal (relación 1 a 1 obligatoria).
     * Si el usuario ya tenía persona, conserva la que sea.
     */
    private function vincularPersona(User $usuario, string $nombre): void
    {
        if ($usuario->persona_id !== null) {
            return;
        }

        $persona = Persona::create([
            'carnet' => 'USR'.str_pad((string) $usuario->id, 5, '0', STR_PAD_LEFT),
            'nombres' => $nombre,
            'apellido_paterno' => $nombre,
            'correo' => $usuario->email,
        ]);

        $usuario->update(['persona_id' => $persona->id]);
    }
}
