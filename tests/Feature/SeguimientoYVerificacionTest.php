<?php

namespace Tests\Feature;

use App\Livewire\Caja\Index as CajaIndex;
use App\Livewire\Compras\Show as CompraShow;
use App\Livewire\Compras\Verificar;
use App\Livewire\Gastos\Index as GastosIndex;
use App\Livewire\Ventas\Pos;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Gasto;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Notifications\CompraAsignadaPush;
use App\Support\Ajustes;
use App\Support\ArqueoDeCaja;
use App\Support\PreciosDelDia;
use App\Support\RegistroDeVenta;
use App\Support\ResumenDiario;
use App\Support\SeguimientoDeVendedores;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Compras asignadas para verificar, caja opcional, gastos, resumen del día y
 * ventas por vendedor.
 */
class SeguimientoYVerificacionTest extends TestCase
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

    private function vendedor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    /** Una compra pendiente con una línea sin serial y una con serial. */
    private function compraPendiente(): Compra
    {
        $compra = Compra::factory()->create(['estado' => 'pendiente', 'total' => 1300]);

        CompraDetalle::factory()->create([
            'compra_id' => $compra->id,
            'producto_id' => Producto::factory()->create(['nombre' => 'Cable HDMI', 'tiene_serial' => false])->id,
            'cantidad' => 4,
            'costo_unitario' => 25,
            'subtotal' => 100,
        ]);

        CompraDetalle::factory()->create([
            'compra_id' => $compra->id,
            'producto_id' => Producto::factory()->create(['nombre' => 'Smart TV', 'tiene_serial' => true])->id,
            'cantidad' => 2,
            'costo_unitario' => 600,
            'subtotal' => 1200,
        ]);

        return $compra->fresh();
    }

    // ---- Compras asignadas -------------------------------------------------

    public function test_el_vendedor_no_ve_compras_pero_si_sus_asignadas(): void
    {
        $vendedor = $this->vendedor();
        $suya = $this->compraPendiente();
        $ajena = $this->compraPendiente();
        $suya->update(['verificador_id' => $vendedor->id, 'asignada_en' => now()]);

        $this->actingAs($vendedor)->get(route('compras.index'))->assertForbidden();
        $this->actingAs($vendedor)->get(route('compras.show', $suya))->assertForbidden();

        $this->actingAs($vendedor)->get(route('compras.verificaciones'))
            ->assertOk()
            ->assertSee($suya->codigo)
            ->assertDontSee($ajena->codigo);

        $this->actingAs($vendedor)->get(route('compras.verificar', $suya))->assertOk();
        $this->actingAs($vendedor)->get(route('compras.verificar', $ajena))->assertForbidden();
    }

    public function test_la_ficha_de_verificacion_no_muestra_costos(): void
    {
        $vendedor = $this->vendedor();
        $compra = $this->compraPendiente();
        $compra->update(['verificador_id' => $vendedor->id]);

        $this->actingAs($vendedor)->get(route('compras.verificar', $compra))
            ->assertOk()
            ->assertSee('Cable HDMI')
            ->assertDontSee('1.300')
            ->assertDontSee('1300');
    }

    public function test_el_vendedor_verifica_por_tandas(): void
    {
        $vendedor = $this->vendedor();
        $compra = $this->compraPendiente();
        $compra->update(['verificador_id' => $vendedor->id]);
        [$cables, $tvs] = $compra->detalles()->orderBy('id')->get()->all();

        Livewire::actingAs($vendedor)
            ->test(Verificar::class, ['compra' => $compra])
            ->set("cantidades.{$cables->id}", '3')
            ->set("seriales.{$tvs->id}.0", 'TV-0001')
            ->call('verificar')
            ->assertDispatched('toast', tipo: 'success');

        $this->assertSame(4, Unidad::where('compra_id', $compra->id)->count());
        $this->assertSame('pendiente', $compra->fresh()->estado);

        Livewire::actingAs($vendedor)
            ->test(Verificar::class, ['compra' => $compra->fresh()])
            ->call('llegoTodo', $cables->id)
            ->assertSet("cantidades.{$cables->id}", '1')
            ->set("seriales.{$tvs->id}.0", 'TV-0002')
            ->call('verificar');

        $this->assertSame('recepcionada', $compra->fresh()->estado);
        $this->assertSame(6, Unidad::where('compra_id', $compra->id)->count());
    }

    public function test_el_admin_asigna_y_le_llega_aviso_al_vendedor(): void
    {
        Notification::fake();

        $vendedor = $this->vendedor();
        $compra = $this->compraPendiente();

        Livewire::actingAs($this->admin())
            ->test(CompraShow::class, ['compra' => $compra])
            ->set('verificadorId', $vendedor->id)
            ->call('asignarVerificador')
            ->assertHasNoErrors();

        $this->assertSame($vendedor->id, $compra->fresh()->verificador_id);
        Notification::assertSentTo($vendedor, CompraAsignadaPush::class);
    }

    public function test_el_panel_recepciona_productos_sin_serial(): void
    {
        // Antes mandaba `verificada` y el servicio esperaba `cantidad_verificada`.
        $compra = $this->compraPendiente();
        [$cables, $tvs] = $compra->detalles()->orderBy('id')->get()->all();

        Livewire::actingAs($this->admin())
            ->test(CompraShow::class, ['compra' => $compra])
            ->call('abrirRecepcion')
            ->set("verificadas.{$cables->id}", true)
            ->set("seriales.{$tvs->id}", ['S-1', 'S-2'])
            ->call('recepcionar')
            ->assertDispatched('toast', tipo: 'success');

        $this->assertSame('recepcionada', $compra->fresh()->estado);
        $this->assertSame(4, Unidad::where('compra_detalle_id', $cables->id)->count());
    }

    public function test_la_api_de_verificacion_solo_da_las_suyas_y_sin_costos(): void
    {
        $vendedor = $this->vendedor();
        $suya = $this->compraPendiente();
        $ajena = $this->compraPendiente();
        $suya->update(['verificador_id' => $vendedor->id, 'asignada_en' => now()]);
        $cables = $suya->detalles()->orderBy('id')->first();

        Sanctum::actingAs($vendedor);

        $this->getJson('/api/v1/compras-por-verificar')
            ->assertOk()
            ->assertJsonCount(1, 'data.pendientes')
            ->assertJsonPath('data.pendientes.0.id', $suya->id)
            ->assertJsonPath('data.pendientes.0.faltan', 6);

        $ficha = $this->getJson("/api/v1/compras-por-verificar/{$suya->id}")->assertOk();
        $this->assertArrayNotHasKey('total', $ficha->json('data'));
        $this->assertArrayNotHasKey('costo_unitario', $ficha->json('data.lineas.0'));

        $this->getJson("/api/v1/compras-por-verificar/{$ajena->id}")->assertForbidden();
        $this->getJson('/api/v1/compras')->assertForbidden();

        $this->postJson("/api/v1/compras-por-verificar/{$suya->id}/recepcionar", [
            'lineas' => [['linea_id' => $cables->id, 'cantidad_verificada' => 4]],
        ])->assertOk()->assertJsonPath('data.lineas.0.faltan', 0);
    }

    public function test_la_api_asigna_la_verificacion(): void
    {
        Notification::fake();
        $vendedor = $this->vendedor();
        $compra = $this->compraPendiente();

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/compras/verificadores')
            ->assertOk()
            ->assertJsonFragment(['id' => $vendedor->id]);

        $this->postJson("/api/v1/compras/{$compra->id}/asignar", ['verificador_id' => $vendedor->id])
            ->assertOk()
            ->assertJsonPath('data.verificador_id', $vendedor->id);

        Notification::assertSentTo($vendedor, CompraAsignadaPush::class);
    }

    // ---- Caja opcional ------------------------------------------------------

    private function unidadVendible(float $precio = 500, float $costo = 300): Unidad
    {
        $producto = Producto::factory()->create(['precio_venta' => $precio, 'descuento_maximo' => 50, 'activo' => true]);
        app(PreciosDelDia::class)->guardar([$producto->id => $precio], $this->admin()->id);

        return Unidad::factory()->create([
            'producto_id' => $producto->id,
            'estado' => 'en_stock',
            'costo_unitario' => $costo,
            'precio_venta' => $precio,
        ]);
    }

    public function test_sin_caja_obligatoria_se_vende_sin_abrir_turno(): void
    {
        $unidad = $this->unidadVendible();
        $admin = $this->admin();

        Livewire::actingAs($admin)->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->assertSet('ventaValida', false);

        app(Ajustes::class)->exigirCaja(false);

        Livewire::actingAs($admin)->test(Pos::class)
            ->assertSet('ventaValida', true)
            ->call('cobrar')
            ->assertHasNoErrors();

        $this->assertSame(1, Venta::count());
        $this->assertNull(Venta::first()->caja_id);
    }

    public function test_solo_el_administrador_cambia_la_caja_obligatoria(): void
    {
        Livewire::actingAs($this->vendedor())->test(CajaIndex::class)
            ->call('alternarObligatoria')
            ->assertForbidden();

        Livewire::actingAs($this->admin())->test(CajaIndex::class)
            ->call('alternarObligatoria');

        $this->assertFalse(app(Ajustes::class)->cajaObligatoria());

        Sanctum::actingAs($this->admin());
        $this->postJson('/api/v1/caja/obligatoria', ['obligatoria' => true])
            ->assertOk()
            ->assertJsonPath('data.obligatoria', true);
    }

    // ---- Gastos --------------------------------------------------------------

    public function test_un_gasto_en_efectivo_del_cajon_baja_el_esperado(): void
    {
        $admin = $this->admin();
        $caja = app(ArqueoDeCaja::class)->abrir($admin->id, 200);

        Livewire::actingAs($admin)->test(GastosIndex::class)
            ->call('nuevo')
            ->set('concepto', 'Almuerzo')
            ->set('categoria', 'comida')
            ->set('monto', '35')
            ->set('metodoPago', 'efectivo')
            ->set('deCaja', true)
            ->call('guardar')
            ->assertHasNoErrors();

        // Uno por QR no toca el cajón.
        Livewire::actingAs($admin)->test(GastosIndex::class)
            ->call('nuevo')
            ->set('concepto', 'Flete')
            ->set('categoria', 'transporte')
            ->set('monto', '80')
            ->set('metodoPago', 'qr')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, Gasto::count());
        $this->assertSame(16500, app(ArqueoDeCaja::class)->esperadoEnCentavos($caja->fresh()));
    }

    public function test_el_vendedor_no_ve_gastos(): void
    {
        $this->actingAs($this->vendedor())->get(route('gastos.index'))->assertForbidden();

        Sanctum::actingAs($this->vendedor());
        $this->getJson('/api/v1/gastos')->assertForbidden();
        $this->getJson('/api/v1/reportes/resumen-diario')->assertForbidden();
    }

    public function test_la_api_registra_y_archiva_gastos(): void
    {
        $admin = $this->admin();
        $vendedor = $this->vendedor();
        Sanctum::actingAs($admin);

        $id = $this->postJson('/api/v1/gastos', [
            'fecha' => now()->toDateString(),
            'concepto' => 'Almuerzo de Ana',
            'categoria' => 'comida',
            'monto' => 30,
            'metodo_pago' => 'qr',
            'beneficiario_id' => $vendedor->id,
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/v1/gastos')
            ->assertOk()
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('data.0.beneficiario', $vendedor->name);

        $this->deleteJson("/api/v1/gastos/{$id}")->assertOk();
        $this->assertSoftDeleted('gastos', ['id' => $id]);
    }

    // ---- Resumen del día -------------------------------------------------------

    public function test_el_resumen_del_dia_suma_ingresos_y_egresos(): void
    {
        $admin = $this->admin();
        $efectivo = $this->unidadVendible(500);
        $qr = $this->unidadVendible(300);

        $registro = app(RegistroDeVenta::class);
        $registro->registrar([['unidad_id' => $efectivo->id, 'precio_unitario' => '500']], ['metodo_pago' => 'efectivo'], $admin->id);
        $registro->registrar([['unidad_id' => $qr->id, 'precio_unitario' => '300']], [
            'metodo_pago' => 'qr',
            'qr_cobro_id' => \App\Models\QrCobro::factory()->create()->id,
            'comprobante_qr' => 'comprobantes-qr/x.jpg',
        ], $admin->id);

        Gasto::factory()->create(['monto' => 40, 'metodo_pago' => 'efectivo']);
        Gasto::factory()->create(['monto' => 60, 'metodo_pago' => 'qr']);

        $compra = Compra::factory()->create(['estado' => 'recepcionada']);
        $compra->pagos()->create(['user_id' => $admin->id, 'monto' => 200, 'fecha' => now()->toDateString()]);

        $r = app(ResumenDiario::class)->del(now());

        $this->assertEquals(800, $r['ingresos']['ventas']['cobrado']);
        $this->assertEquals(500, $r['ingresos']['ventas']['efectivo']);
        $this->assertEquals(300, $r['ingresos']['ventas']['qr']);
        $this->assertEquals(100, $r['egresos']['gastos']['total']);
        $this->assertEquals(200, $r['egresos']['proveedores']['total']);
        $this->assertEquals(500, $r['neto']);
        // Efectivo: 500 entró, 40 salió en gastos en billetes.
        $this->assertEquals(460, $r['efectivo']['neto']);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/reportes/resumen-diario')
            ->assertOk()
            ->assertJsonPath('data.neto', 500);
    }

    // ---- Ventas por vendedor ---------------------------------------------------

    public function test_el_seguimiento_separa_descuentos_y_sobreprecios(): void
    {
        $admin = $this->admin();
        $vendedor = $this->vendedor();
        app(Ajustes::class)->exigirCaja(false);

        $rebajada = $this->unidadVendible(500);
        $cara = $this->unidadVendible(500);

        Livewire::actingAs($vendedor)->test(Pos::class)
            ->call('agregar', $rebajada->id)
            ->call('agregar', $cara->id)
            ->set('carrito.0.precio', '470')
            ->set('carrito.1.precio', '560')
            ->call('cobrar')
            ->assertHasNoErrors();

        // La lista del momento queda en cada línea.
        $this->assertDatabaseHas('venta_detalles', ['unidad_id' => $cara->id, 'precio_lista' => 500, 'precio_unitario' => 560]);

        $fila = collect(app(SeguimientoDeVendedores::class)->entre(now(), now()))->firstWhere('vendedor_id', $vendedor->id);

        $this->assertSame(2, $fila['unidades']);
        $this->assertEquals(1030, $fila['total']);
        $this->assertEquals(30, $fila['descuento']);
        $this->assertEquals(60, $fila['sobreprecio']);
        $this->assertEquals(30, $fila['balance']);
        $this->assertCount(2, $fila['desvios']);

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/reportes/vendedores')
            ->assertOk()
            ->assertJsonPath('data.0.vendedor_id', $vendedor->id)
            ->assertJsonPath('data.0.sobreprecio', 60);
    }
}
