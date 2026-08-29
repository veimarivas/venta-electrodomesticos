<?php

namespace Tests\Feature;

use App\Livewire\Dashboard\Panel;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Qué enseña el dashboard del panel según el permiso de quien mira.
 *
 * El dashboard es la pantalla de aterrizaje: no se corta el acceso entero, se
 * corta lo que lleva importes. La regla tiene que ser la MISMA que la de la
 * API (`GET /api/v1/dashboard/*`, tras `reportes.ver`); si aquí fuera más laxa,
 * la misma cuenta enseñaría cosas distintas según por dónde entrase.
 */
class DashboardPermisosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles($rol);
    }

    // ---- Ticket promedio y margen ------------------------------------------

    public function test_el_dashboard_muestra_el_ticket_promedio(): void
    {
        Livewire::actingAs($this->usuario('supervisor'))
            ->test(Panel::class)
            ->assertSee('Ticket promedio', false)
            ->assertSee('Aparatos vendidos', false);
    }

    public function test_el_margen_solo_se_ve_con_el_permiso_de_costos(): void
    {
        // El margen dice cuánto gana la tienda: es el mismo dato que la
        // ganancia, expresado en porcentaje. Va tras el mismo permiso.
        Livewire::actingAs($this->usuario('supervisor'))
            ->test(Panel::class)
            ->assertSee('Margen', false);

        Livewire::actingAs($this->usuario('vendedor'))
            ->test(Panel::class)
            ->assertDontSee('Margen', false);
    }

    // ---- El hueco que se cerró ---------------------------------------------

    public function test_un_vendedor_no_ve_la_caja_del_dia(): void
    {
        // `vendedor` NO tiene `reportes.ver`, así que la API le niega
        // /api/v1/dashboard/resumen. El panel se lo enseñaba igualmente.
        $vendedor = $this->usuario('vendedor');

        $this->assertFalse(
            $vendedor->can('reportes.ver'),
            'Si el vendedor pasa a tener reportes.ver, esta prueba deja de medir lo que cree.'
        );

        Livewire::actingAs($vendedor)
            ->test(Panel::class)
            ->assertDontSee('Ventas de hoy', false)
            ->assertDontSee('Esta semana', false)
            ->assertDontSee('Ticket promedio', false)
            ->assertDontSee('Más vendidos', false);
    }

    public function test_el_vendedor_sigue_viendo_el_almacen(): void
    {
        // No se le corta el dashboard entero: lo que hay en la estantería sí es
        // información suya, y es la pantalla a la que aterriza al entrar.
        Livewire::actingAs($this->usuario('vendedor'))
            ->test(Panel::class)
            ->assertOk()
            ->assertSee('Almacén', false)
            ->assertSee('Aparatos disponibles', false);
    }

    public function test_quien_no_ve_ventas_no_ve_las_ultimas_ventas(): void
    {
        // Antes solo estaba condicionado el enlace «Ver todas», así que quien
        // no podía entrar al listado veía igualmente los totales aquí.
        $sinVentas = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($sinVentas)
            ->test(Panel::class)
            ->assertDontSee('Últimas ventas', false);
    }

    public function test_un_supervisor_lo_ve_todo(): void
    {
        Livewire::actingAs($this->usuario('supervisor'))
            ->test(Panel::class)
            ->assertSee('Ventas de hoy', false)
            ->assertSee('Ticket promedio', false)
            ->assertSee('Últimas ventas', false)
            ->assertSee('Más vendidos', false)
            ->assertSee('Almacén', false)
            ->assertSee('Aparatos disponibles', false);
    }
}
