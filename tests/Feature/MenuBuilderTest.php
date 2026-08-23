<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MenuBuilder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class MenuBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function menuLabels(): array
    {
        return collect(app(MenuBuilder::class)->build())
            ->map(fn (array $item) => $item['label'])
            ->all();
    }

    public function test_el_administrador_ve_todos_los_modulos(): void
    {
        $this->actingAs(User::factory()->create()->syncRoles('admin'));

        $labels = $this->menuLabels();

        $this->assertContains('Compras', $labels);
        $this->assertContains('Reportes', $labels);
        $this->assertContains('Administración', $labels);
    }

    public function test_el_vendedor_no_ve_los_modulos_sin_permiso(): void
    {
        $this->actingAs(User::factory()->create()->syncRoles('vendedor'));

        $labels = $this->menuLabels();

        $this->assertContains('Ventas', $labels);
        $this->assertNotContains('Compras', $labels);
        $this->assertNotContains('Reportes', $labels);
        $this->assertNotContains('Administración', $labels);
    }

    public function test_los_titulos_sin_items_visibles_se_descartan(): void
    {
        $this->actingAs(User::factory()->create()->syncRoles('vendedor'));

        $titulos = collect(app(MenuBuilder::class)->build())
            ->where('type', 'title')
            ->pluck('label')
            ->all();

        // "Análisis" solo encabeza Reportes y "Sistema" solo Administración:
        // sin esos permisos, los encabezados quedarían sueltos.
        $this->assertNotContains('Análisis', $titulos);
        $this->assertNotContains('Sistema', $titulos);
        $this->assertContains('Principal', $titulos);
    }

    public function test_una_ruta_inexistente_no_rompe_el_menu(): void
    {
        // Los módulos aún no implementados apuntan a rutas que no existen;
        // el menú debe degradar a '#' en lugar de lanzar RouteNotFoundException.
        Config::set('menu', [
            ['label' => 'Pendiente', 'icon' => 'ri-test-tube-line', 'route' => 'ruta.que.no.existe'],
        ]);

        $this->actingAs(User::factory()->create()->syncRoles('admin'));

        $this->assertSame('#', app(MenuBuilder::class)->build()[0]['url']);
    }

    public function test_marca_como_activo_el_item_de_la_url_actual(): void
    {
        $this->actingAs(User::factory()->create()->syncRoles('admin'));

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('nav-link menu-link active', escape: false);
    }
}
