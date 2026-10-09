<?php

namespace Tests\Feature;

use App\Livewire\Ventas\Pos;
use App\Models\CompraDetalle;
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

    // ---- Sugerencias por compra nueva --------------------------------------

    /** Un lote de compra del producto: unidades con su costo y su fecha de ingreso. */
    private function lote(Producto $producto, float $costo, \DateTimeInterface $recibido, int $cantidad = 1): CompraDetalle
    {
        $linea = CompraDetalle::factory()->create([
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'costo_unitario' => $costo,
            'subtotal' => $costo * $cantidad,
            'costo_real_unitario' => $costo,
        ]);

        Unidad::factory()->count($cantidad)->create([
            'producto_id' => $producto->id,
            'compra_id' => $linea->compra_id,
            'compra_detalle_id' => $linea->id,
            'estado' => 'en_stock',
            'costo_unitario' => $costo,
            'precio_venta' => $producto->precio_venta,
            'ingresado_en' => $recibido,
        ]);

        return $linea;
    }

    /**
     * La licuadora del ejemplo: comprada a 800, vendida a 1100 y confirmada
     * hace dos días con ese precio.
     */
    private function licuadoraConfirmada(): Producto
    {
        $producto = Producto::factory()->create(['nombre' => 'Licuadora LG LK50', 'precio_venta' => 1100, 'activo' => true]);

        $this->lote($producto, 800, now()->subDays(10));
        app(PreciosDelDia::class)->guardar([$producto->id => 1100], $this->admin()->id, now()->subDays(2));

        return $producto;
    }

    private function sugerenciaDe(Producto $producto): ?object
    {
        return app(PreciosDelDia::class)->paraRevisar()
            ->first(fn ($fila): bool => $fila->producto->id === $producto->id)
            ?->sugerencia;
    }

    public function test_una_compra_mas_cara_sugiere_subir_conservando_el_margen(): void
    {
        $producto = $this->licuadoraConfirmada();
        $linea = $this->lote($producto, 850, now()->subDay());

        $sugerencia = $this->sugerenciaDe($producto);

        // 1100 × 850 / 800 = 1168,75 → al Bs entero hacia arriba.
        $this->assertNotNull($sugerencia);
        $this->assertSame('sube', $sugerencia->tipo);
        $this->assertEquals(1169.0, $sugerencia->precio_sugerido);
        $this->assertEquals(800.0, $sugerencia->costo_anterior);
        $this->assertEquals(850.0, $sugerencia->costo_nuevo);
        $this->assertSame($linea->compra->codigo, $sugerencia->compra_codigo);
    }

    public function test_una_compra_mas_barata_sugiere_bajar(): void
    {
        $producto = $this->licuadoraConfirmada();
        $this->lote($producto, 750, now()->subDay());

        $sugerencia = $this->sugerenciaDe($producto);

        // 1100 × 750 / 800 = 1031,25 → 1032.
        $this->assertSame('baja', $sugerencia->tipo);
        $this->assertEquals(1032.0, $sugerencia->precio_sugerido);
    }

    public function test_lo_recibido_hoy_se_sugiere_manana_no_hoy(): void
    {
        $producto = $this->licuadoraConfirmada();
        $this->lote($producto, 850, now());

        $this->assertNull($this->sugerenciaDe($producto));
    }

    public function test_una_compra_anterior_a_la_ultima_confirmacion_no_se_vuelve_a_sugerir(): void
    {
        $producto = $this->licuadoraConfirmada();
        $this->lote($producto, 850, now()->subDays(5));

        $this->assertNull($this->sugerenciaDe($producto));
    }

    public function test_el_mismo_costo_no_sugiere_nada(): void
    {
        $producto = $this->licuadoraConfirmada();
        $this->lote($producto, 802, now()->subDay());

        // +0,25 %: ruido del prorrateo, no un cambio de costo.
        $this->assertNull($this->sugerenciaDe($producto));
    }

    public function test_la_sugerencia_no_cambia_el_precio_hasta_confirmarla(): void
    {
        $producto = $this->licuadoraConfirmada();
        $this->lote($producto, 850, now()->subDay());

        // Sin confirmar, se sigue vendiendo al precio de antes.
        $this->assertEquals(1100.0, app(PreciosDelDia::class)->precioVigente($producto->id));

        $pantalla = Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Precios\Index::class)
            ->assertSet('precios.'.$producto->id, '1100.00')
            ->assertSet('filtro', 'sugerencias')
            ->call('aplicarSugerencia', $producto->id)
            ->assertSet('precios.'.$producto->id, '1169.00');

        // Aplicada pero sin confirmar: todavía nada cambió.
        $this->assertEquals(1100.0, app(PreciosDelDia::class)->precioVigente($producto->id));

        $pantalla->call('guardar')->assertHasNoErrors();

        $this->assertEquals(1169.0, app(PreciosDelDia::class)->precioVigente($producto->id));
    }

    public function test_confirmar_sin_cambios_mantiene_el_precio_de_ayer(): void
    {
        $producto = $this->licuadoraConfirmada();
        $this->lote($producto, 850, now()->subDay());

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Precios\Index::class)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success');

        $this->assertTrue(app(PreciosDelDia::class)->listos());
        $this->assertEquals(1100.0, app(PreciosDelDia::class)->precioVigente($producto->id));
    }

    public function test_un_precio_bajo_el_costo_se_marca_en_su_fila_y_quita_el_filtro(): void
    {
        $producto = $this->licuadoraConfirmada();
        $this->lote($producto, 850, now()->subDay());
        $otro = $this->productoConStock(100, 60);

        Livewire::actingAs($this->admin())
            ->test(\App\Livewire\Precios\Index::class)
            ->assertSet('filtro', 'sugerencias')
            ->set('precios.'.$otro->id, '50')
            ->call('guardar')
            ->assertHasErrors(['precios', 'precios.'.$otro->id])
            // El que bloquea no tiene sugerencia: con el filtro puesto no se veía.
            ->assertSet('filtro', 'todos')
            // Al corregirlo, su marca roja se va sin esperar a otro envío.
            ->set('precios.'.$otro->id, '120')
            ->assertHasNoErrors('precios.'.$otro->id);

        $this->assertSame(0, \App\Models\PrecioProducto::whereDate('fecha', now())->count());
    }

    public function test_la_api_manda_la_sugerencia(): void
    {
        $producto = $this->licuadoraConfirmada();
        $this->lote($producto, 850, now()->subDay());

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/precios-del-dia')
            ->assertOk()
            ->assertJsonPath('meta.sugerencias', 1)
            ->assertJsonPath('data.0.producto_id', $producto->id)
            ->assertJsonPath('data.0.precio_anterior', 1100)
            ->assertJsonPath('data.0.sugerencia.tipo', 'sube')
            ->assertJsonPath('data.0.sugerencia.precio_sugerido', 1169);
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
