<?php

namespace Tests\Feature;

use App\Livewire\Ventas\Pos;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Support\PreciosDelDia;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Precios por jornada: se fijan al empezar el día y el último registrado es el
 * que ofrece el punto de venta.
 */
class PreciosDelDiaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('admin');
    }

    private function productoConStock(float $precio = 100, float $costo = 60): Producto
    {
        $producto = Producto::factory()->create(['precio_venta' => $precio, 'activo' => true]);

        Unidad::factory()->create([
            'producto_id' => $producto->id,
            'estado' => 'en_stock',
            'costo_unitario' => $costo,
            'precio_venta' => $precio,
        ]);

        return $producto;
    }

    // ---- El servicio --------------------------------------------------------

    public function test_sin_precios_registrados_el_vigente_es_el_inicial(): void
    {
        $producto = $this->productoConStock(100);

        $this->assertEquals(100.0, app(PreciosDelDia::class)->precioVigente($producto->id));
    }

    public function test_el_precio_vigente_es_el_ultimo_registrado(): void
    {
        $producto = $this->productoConStock(100);
        $servicio = app(PreciosDelDia::class);

        $servicio->guardar([$producto->id => 150], $this->admin()->id, now()->subDay());
        $this->assertEquals(150.0, $servicio->precioVigente($producto->id));

        $servicio->guardar([$producto->id => 180], $this->admin()->id, now());
        $this->assertEquals(180.0, $servicio->precioVigente($producto->id));
    }

    public function test_el_precio_anterior_es_el_de_la_jornada_previa(): void
    {
        $producto = $this->productoConStock(100);
        $servicio = app(PreciosDelDia::class);

        $servicio->guardar([$producto->id => 150], $this->admin()->id, now()->subDay());

        // Al fijar el de hoy se enseña el de ayer; ayer no había ninguno.
        $this->assertEquals(150.0, $servicio->anterior($producto->id, now()));
        $this->assertNull($servicio->anterior($producto->id, now()->subDay()));
    }

    public function test_reenviar_el_mismo_dia_corrige_en_vez_de_duplicar(): void
    {
        $producto = $this->productoConStock(100);
        $servicio = app(PreciosDelDia::class);
        $admin = $this->admin();

        $servicio->guardar([$producto->id => 150], $admin->id);
        $servicio->guardar([$producto->id => 170], $admin->id);

        $this->assertSame(1, \App\Models\PrecioProducto::where('producto_id', $producto->id)->count());
        $this->assertEquals(170.0, $servicio->precioVigente($producto->id));
    }

    public function test_el_pos_usa_el_precio_del_dia(): void
    {
        $producto = $this->productoConStock(100, 60);
        $unidad = $producto->unidades()->first();

        app(PreciosDelDia::class)->guardar([$producto->id => 180], $this->admin()->id);

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->assertSet('carrito.0.precio_lista', '180.00');
    }

    // ---- La pantalla del panel ---------------------------------------------

    public function test_el_panel_guarda_los_precios(): void
    {
        $producto = $this->productoConStock(100, 60);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Precios\Index::class)
            ->set('precios.'.$producto->id, '180')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success');

        $this->assertDatabaseHas('precios_producto', [
            'producto_id' => $producto->id,
            'precio_venta' => 180,
        ]);
    }

    public function test_el_panel_rechaza_un_precio_por_debajo_del_costo(): void
    {
        $producto = $this->productoConStock(100, 60);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Precios\Index::class)
            ->set('precios.'.$producto->id, '50')
            ->call('guardar')
            ->assertHasErrors('precios');

        $this->assertSame(0, \App\Models\PrecioProducto::count());
    }

    // ---- La API -------------------------------------------------------------

    public function test_la_api_lista_los_productos_con_stock(): void
    {
        $producto = $this->productoConStock(100, 60);

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/precios-del-dia')
            ->assertOk()
            ->assertJsonPath('data.0.producto_id', $producto->id)
            ->assertJsonPath('data.0.precio_anterior', 100)
            ->assertJsonPath('meta.pendientes', 1);
    }

    public function test_la_api_guarda_los_precios(): void
    {
        $producto = $this->productoConStock(100, 60);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/precios-del-dia', [
            'precios' => [['producto_id' => $producto->id, 'precio' => 180]],
        ])->assertOk();

        $this->assertDatabaseHas('precios_producto', [
            'producto_id' => $producto->id,
            'precio_venta' => 180,
        ]);
    }

    public function test_la_api_rechaza_un_precio_por_debajo_del_costo(): void
    {
        $producto = $this->productoConStock(100, 60);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/precios-del-dia', [
            'precios' => [['producto_id' => $producto->id, 'precio' => 50]],
        ])->assertStatus(422)->assertJsonValidationErrors('precios');
    }

    public function test_la_api_exige_permiso(): void
    {
        $vendedor = User::factory()->create(['is_active' => true]);
        $vendedor->syncPermissions(['ventas.ver']);

        Sanctum::actingAs($vendedor);

        $this->getJson('/api/v1/precios-del-dia')->assertForbidden();
    }

    // ---- La validación de la unidad ----------------------------------------

    public function test_el_alta_de_unidad_exige_precio_mayor_que_el_costo(): void
    {
        $producto = Producto::factory()->create(['precio_venta' => 100]);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/unidades', [
            'producto_id' => $producto->id,
            'precio_venta' => 50,
            'costo_unitario' => 80,
        ])->assertStatus(422)->assertJsonValidationErrors('precio_venta');
    }
}
