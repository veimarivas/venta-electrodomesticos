<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * `usuario:acceso` diagnostica por qué una cuenta no entra.
 *
 * Lo importante que se fija aquí: busca **igual que el formulario de entrada**.
 * Si los dos no normalizan el identificador de la misma forma, el comando diría
 * que la cuenta existe mientras el login sigue rechazándola, que es peor que no
 * tener diagnóstico.
 */
class RevisarAccesoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_encuentra_la_cuenta_por_correo(): void
    {
        User::factory()->create(['email' => 'admin@tienda.test'])->syncRoles('admin');

        $this->artisan('usuario:acceso', ['identificador' => 'admin@tienda.test'])
            ->assertSuccessful();
    }

    public function test_encuentra_la_cuenta_aunque_se_escriba_en_mayusculas(): void
    {
        // El login hace `Str::lower` antes de buscar; el comando tiene que
        // hacer lo mismo o daría un diagnóstico que no corresponde.
        User::factory()->create(['email' => 'admin@tienda.test'])->syncRoles('admin');

        $this->artisan('usuario:acceso', ['identificador' => '  ADMIN@Tienda.Test  '])
            ->assertSuccessful();
    }

    public function test_si_no_existe_lo_dice_y_lista_las_que_hay(): void
    {
        User::factory()->create(['email' => 'otro@tienda.test'])->syncRoles('admin');

        $this->artisan('usuario:acceso', ['identificador' => 'nadie@tienda.test'])
            ->expectsOutputToContain('otro@tienda.test')
            ->assertFailed();
    }

    public function test_reactiva_una_cuenta_desactivada(): void
    {
        // Una cuenta inactiva no entra, y el formulario lo dice con un mensaje
        // que se confunde con «contraseña incorrecta».
        $usuario = User::factory()->create(['is_active' => false])->syncRoles('admin');

        $this->artisan('usuario:acceso', [
            'identificador' => $usuario->email,
            '--activar' => true,
        ])->assertSuccessful();

        $this->assertTrue($usuario->fresh()->is_active);
    }

    public function test_cambia_la_contrasena_y_deja_la_cuenta_activa(): void
    {
        $usuario = User::factory()->create([
            'is_active' => false,
            'password' => Hash::make('la-vieja'),
        ])->syncRoles('admin');

        $this->artisan('usuario:acceso', [
            'identificador' => $usuario->email,
            '--reset' => true,
        ])
            ->expectsQuestion('Contraseña nueva (no se muestra)', 'una-clave-larga')
            ->expectsQuestion('Repítela', 'una-clave-larga')
            ->assertSuccessful();

        $usuario->refresh();

        $this->assertTrue(Hash::check('una-clave-larga', $usuario->password));
        // Cambiar la clave sin reactivar dejaría a la persona igual de fuera.
        $this->assertTrue($usuario->is_active);
    }

    public function test_no_cambia_nada_si_las_dos_no_coinciden(): void
    {
        $usuario = User::factory()->create(['password' => Hash::make('la-vieja')])
            ->syncRoles('admin');

        $this->artisan('usuario:acceso', [
            'identificador' => $usuario->email,
            '--reset' => true,
        ])
            ->expectsQuestion('Contraseña nueva (no se muestra)', 'una-clave-larga')
            ->expectsQuestion('Repítela', 'otra-distinta')
            ->assertFailed();

        $this->assertTrue(Hash::check('la-vieja', $usuario->fresh()->password));
    }

    public function test_rechaza_una_contrasena_corta(): void
    {
        $usuario = User::factory()->create(['password' => Hash::make('la-vieja')])
            ->syncRoles('admin');

        $this->artisan('usuario:acceso', [
            'identificador' => $usuario->email,
            '--reset' => true,
        ])
            ->expectsQuestion('Contraseña nueva (no se muestra)', 'corta')
            ->assertFailed();

        $this->assertTrue(Hash::check('la-vieja', $usuario->fresh()->password));
    }
}
