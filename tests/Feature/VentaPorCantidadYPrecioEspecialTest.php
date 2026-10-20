<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\SolicitudDescuento;
use App\Models\Tienda;
use App\Models\Unidad;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Venta desde el teléfono (2026-10-20):
 *  - el listado de ventas y el recibo con la tienda cargada (antes, «lazy
 *    loading is disabled» en cuanto había dos ventas con tienda);
 *  - precio especial por producto y cantidad, con precios sugeridos;
 *  - productos con serial por cantidad, identificados al confirmar.
 */
class VentaPorCantidadYPrecioEspecialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function vendedor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('admin');
    }

    private function producto(float $precio = 400, float $tope = 50, bool $serial = true): Producto
    {
        return Producto::factory()->create([
            'precio_venta' => $precio,
            'descuento_maximo' => $tope,
            'tiene_serial' => $serial,
        ]);
    }

    private function unidad(Producto $producto, float $costo = 200): Unidad
    {
        return Unidad::factory()->create([
            'producto_id' => $producto->id,
            'estado' => 'en_stock',
            'costo_unitario' => $costo,
            'precio_venta' => $producto->precio_venta,
        ]);
    }

    // ---- Listado y recibo con tienda ------------------------------------------

    public function test_el_listado_de_ventas_de_la_app_carga_la_tienda(): void
    {
        $tienda = Tienda::query()->create(['nombre' => 'Centro', 'latitud' => -17.78, 'longitud' => -63.18]);
        $producto = $this->producto(serial: false);
        $vendedor = $this->vendedor();

        Sanctum::actingAs($vendedor);

        foreach (range(1, 2) as $_) {
            $this->postJson('/api/v1/pos/cobrar', [
                'lineas' => [['unidad_id' => $this->unidad($producto)->id, 'precio' => 400]],
                'metodo_pago' => 'efectivo',
                'tienda_id' => $tienda->id,
            ])->assertStatus(201);
        }

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/ventas')
            ->assertOk()
            ->assertJsonPath('data.0.tienda', 'Centro')
            ->assertJsonPath('data.1.tienda', 'Centro');

        $ventaId = $this->getJson('/api/v1/ventas')->json('data.0.id');

        $this->get("/api/v1/ventas/{$ventaId}/recibo")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    // ---- Precio especial ------------------------------------------------------

    public function test_las_sugerencias_quedan_bajo_el_minimo_y_sobre_el_costo(): void
    {
        $producto = $this->producto(precio: 400, tope: 50);
        $this->unidad($producto, costo: 320);

        Sanctum::actingAs($this->vendedor());

        $datos = $this->getJson('/api/v1/pos/sugerencias-descuento?producto_id='.$producto->id)
            ->assertOk()
            ->json('data');

        $this->assertEquals(350.0, $datos['precio_minimo']);
        $this->assertNotEmpty($datos['sugerencias']);

        foreach ($datos['sugerencias'] as $precio) {
            $this->assertLessThan(350, $precio);
            $this->assertGreaterThanOrEqual(320, $precio);
        }

        // Nunca viaja el costo.
        $this->assertArrayNotHasKey('costo_unitario', $datos);
    }

    public function test_una_autorizacion_por_producto_cubre_la_cantidad_pedida_y_nada_mas(): void
    {
        $producto = $this->producto(precio: 400, tope: 50);
        $unidades = collect(range(1, 3))->map(fn () => $this->unidad($producto));
        $vendedor = $this->vendedor();

        Sanctum::actingAs($vendedor);

        $id = $this->postJson('/api/v1/pos/solicitudes-descuento', [
            'producto_id' => $producto->id,
            'cantidad' => 2,
            'precio' => 300,
        ])->assertStatus(201)
            ->assertJsonPath('data.por_producto', true)
            ->assertJsonPath('data.cantidad', 2)
            ->json('data.id');

        // El administrador recibe UN aviso por la línea entera.
        $this->assertSame(1, SolicitudDescuento::query()->count());

        Sanctum::actingAs($this->admin());
        $this->postJson("/api/v1/autorizaciones/{$id}/resolver", ['aprobar' => true])->assertOk();

        Sanctum::actingAs($vendedor);

        // Tres a ese precio: la tercera no está cubierta.
        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => $unidades->map(fn (Unidad $u) => ['unidad_id' => $u->id, 'precio' => 300])->all(),
            'metodo_pago' => 'efectivo',
        ])->assertStatus(422);

        // Dos, sí; y la autorización queda gastada.
        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => $unidades->take(2)->map(fn (Unidad $u) => ['unidad_id' => $u->id, 'precio' => 300])->all(),
            'metodo_pago' => 'efectivo',
        ])->assertStatus(201);

        $solicitud = SolicitudDescuento::find($id);
        $this->assertSame(2, $solicitud->usadas);
        $this->assertSame('consumida', $solicitud->estado);
    }

    public function test_otro_vendedor_no_puede_usar_la_autorizacion_ajena(): void
    {
        $producto = $this->producto(precio: 400, tope: 50);
        $unidad = $this->unidad($producto);

        Sanctum::actingAs($this->vendedor());
        $id = $this->postJson('/api/v1/pos/solicitudes-descuento', [
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio' => 300,
        ])->json('data.id');

        Sanctum::actingAs($this->admin());
        $this->postJson("/api/v1/autorizaciones/{$id}/resolver", ['aprobar' => true])->assertOk();

        Sanctum::actingAs($this->vendedor());
        $this->postJson('/api/v1/pos/cobrar', [
            'lineas' => [['unidad_id' => $unidad->id, 'precio' => 300]],
            'metodo_pago' => 'efectivo',
        ])->assertStatus(422);
    }

    public function test_el_vendedor_puede_dejar_de_esperar(): void
    {
        $producto = $this->producto(precio: 400, tope: 50);
        $this->unidad($producto);

        Sanctum::actingAs($this->vendedor());

        $id = $this->postJson('/api/v1/pos/solicitudes-descuento', [
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio' => 300,
        ])->json('data.id');

        $this->postJson("/api/v1/pos/solicitudes-descuento/{$id}/cancelar")
            ->assertOk()
            ->assertJsonPath('data.estado', 'cancelada');
    }

    public function test_no_se_pide_precio_especial_por_debajo_del_costo(): void
    {
        $producto = $this->producto(precio: 400, tope: 50);
        $this->unidad($producto, costo: 320);

        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/pos/solicitudes-descuento', [
            'producto_id' => $producto->id,
            'cantidad' => 1,
            'precio' => 300,
        ])->assertStatus(422);
    }

    public function test_el_pos_del_panel_pide_una_sola_autorizacion_por_la_linea(): void
    {
        $producto = $this->producto(precio: 400, tope: 50, serial: false);
        $primera = $this->unidad($producto);
        $this->unidad($producto);
        app(\App\Support\PreciosDelDia::class)->guardar([$producto->id => 400], $this->admin()->id);
        app(\App\Support\Ajustes::class)->exigirCaja(false);

        $pantalla = \Livewire\Livewire::actingAs($this->vendedor())
            ->test(\App\Livewire\Ventas\Pos::class)
            ->call('agregar', $primera->id)
            ->call('cambiarCantidad', $producto->id, 2);

        $sugeridos = $pantalla->instance()->sugerenciasDe(0);
        $this->assertNotEmpty($sugeridos);

        $pantalla->call('solicitarAutorizacion', 0, $sugeridos[0])
            ->assertSet('carrito.0.solicitud_estado', 'pendiente')
            ->assertSet('carrito.1.solicitud_estado', 'pendiente')
            ->assertSet('carrito.1.precio', number_format($sugeridos[0], 2, '.', ''));

        $solicitud = SolicitudDescuento::query()->sole();
        $this->assertNull($solicitud->unidad_id);
        $this->assertSame(2, $solicitud->cantidad);
    }

    // ---- Productos con serial por cantidad ------------------------------------

    public function test_el_buscador_junta_tambien_los_productos_con_serial(): void
    {
        $producto = $this->producto();
        $producto->update(['nombre' => 'Televisor 55 pulgadas']);
        collect(range(1, 3))->each(fn () => $this->unidad($producto));

        Sanctum::actingAs($this->vendedor());

        $this->getJson('/api/v1/pos/buscar?termino=Televisor')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.disponibles', 3)
            ->assertJsonPath('data.0.tiene_serial', true);
    }

    public function test_identificar_aparta_el_aparato_escaneado_del_mismo_producto(): void
    {
        $producto = $this->producto();
        $escaneado = $this->unidad($producto);
        $escaneado->update(['serial' => 'SN-ABC-123']);
        $vendedor = $this->vendedor();

        Sanctum::actingAs($vendedor);

        $this->postJson('/api/v1/pos/identificar', [
            'producto_id' => $producto->id,
            'codigo' => 'sn-abc-123',
        ])->assertOk()->assertJsonPath('data.unidad_id', $escaneado->id);

        $this->assertSame('reservado', $escaneado->fresh()->estado);
        $this->assertSame($vendedor->id, $escaneado->fresh()->reservado_por);
    }

    public function test_identificar_rechaza_el_serial_de_otro_producto_o_ya_vendido(): void
    {
        $producto = $this->producto();
        $otro = $this->unidad($this->producto());
        $otro->update(['serial' => 'SN-OTRO']);

        $vendido = $this->unidad($producto);
        $vendido->update(['serial' => 'SN-VENDIDO', 'estado' => 'vendido']);

        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/pos/identificar', ['producto_id' => $producto->id, 'codigo' => 'SN-OTRO'])
            ->assertStatus(422);
        $this->postJson('/api/v1/pos/identificar', ['producto_id' => $producto->id, 'codigo' => 'SN-VENDIDO'])
            ->assertStatus(422);
        $this->postJson('/api/v1/pos/identificar', ['producto_id' => $producto->id, 'codigo' => 'NO-EXISTE'])
            ->assertStatus(422);
    }

    public function test_la_lista_para_elegir_trae_las_libres_y_las_mias(): void
    {
        $producto = $this->producto();
        $libre = $this->unidad($producto);
        $mia = $this->unidad($producto);
        $ajena = $this->unidad($producto);
        $vendedor = $this->vendedor();

        $mia->update(['estado' => 'reservado', 'reservado_por' => $vendedor->id, 'reservado_hasta' => now()->addMinutes(10)]);
        $ajena->update(['estado' => 'reservado', 'reservado_por' => $this->vendedor()->id, 'reservado_hasta' => now()->addMinutes(10)]);

        Sanctum::actingAs($vendedor);

        $ids = collect($this->getJson('/api/v1/pos/unidades?producto_id='.$producto->id)->assertOk()->json('data'))
            ->pluck('unidad_id')
            ->all();

        $this->assertEqualsCanonicalizing([$libre->id, $mia->id], $ids);
    }
}
