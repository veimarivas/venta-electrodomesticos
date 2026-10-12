<?php

namespace Tests\Feature;

use App\Livewire\Sistema\TiendaActual as SelectorDeTienda;
use App\Models\Asistencia;
use App\Models\Caja;
use App\Models\PagoCompra;
use App\Models\Producto;
use App\Models\Tienda;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\ArqueoDeCaja;
use App\Support\RegistroDeVenta;
use App\Support\ResumenDiario;
use App\Support\SeguimientoDeVendedores;
use App\Support\TiendaActual;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Seguimiento por tienda: de qué tienda sale cada venta, una caja por tienda,
 * los reportes por tienda y la tienda principal para lo de antes. El stock no
 * se separa.
 */
class VentasPorTiendaTest extends TestCase
{
    use RefreshDatabase;

    private Tienda $centro;

    private Tienda $norte;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->centro = Tienda::query()->create(['nombre' => 'Centro', 'latitud' => -17.7833, 'longitud' => -63.1821]);
        $this->norte = Tienda::query()->create(['nombre' => 'Norte', 'latitud' => -17.7560, 'longitud' => -63.1800]);
    }

    private function vendedor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('admin');
    }

    private function enTurno(User $u, Tienda $t): void
    {
        Asistencia::query()->create([
            'user_id' => $u->id, 'tienda_id' => $t->id, 'fecha' => today()->toDateString(),
            'entrada_en' => now(), 'entrada_latitud' => $t->latitud, 'entrada_longitud' => $t->longitud,
            'entrada_distancia' => 3,
        ]);
    }

    private function vender(User $u, ?int $tiendaId, float $precio = 500): Venta
    {
        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create(['descuento_maximo' => 0])->id,
            'estado' => 'en_stock',
            'costo_unitario' => 300,
        ]);

        return app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => (string) $precio]],
            ['metodo_pago' => 'efectivo', 'tienda_id' => $tiendaId],
            $u->id,
        );
    }

    // ---- De qué tienda es cada uno ----------------------------------------------

    public function test_la_tienda_sale_de_la_asistencia_luego_de_la_elegida_y_luego_de_la_principal(): void
    {
        $servicio = app(TiendaActual::class);
        $ana = $this->vendedor();

        // Sin elegir ni principal, con dos tiendas: ninguna.
        $this->assertNull($servicio->de($ana));

        $servicio->hacerPrincipal($this->centro);
        $this->assertSame(['Centro', 'principal'], [$servicio->de($ana)->nombre, $servicio->resolver($ana)['fuente']]);

        $servicio->elegir($ana, $this->norte->id);
        $this->assertSame('elegida', app(TiendaActual::class)->resolver($ana->fresh())['fuente']);
        $this->assertSame('Norte', app(TiendaActual::class)->de($ana->fresh())->nombre);

        $this->enTurno($ana, $this->centro);
        $resuelto = app(TiendaActual::class)->resolver($ana->fresh());
        $this->assertSame(['Centro', 'asistencia'], [$resuelto['tienda']->nombre, $resuelto['fuente']]);
    }

    public function test_con_turno_abierto_no_se_cambia_de_tienda(): void
    {
        $ana = $this->vendedor();
        $this->enTurno($ana, $this->centro);

        $this->expectException(RuntimeException::class);
        app(TiendaActual::class)->elegir($ana, $this->norte->id);
    }

    public function test_sin_tiendas_todo_sigue_como_antes(): void
    {
        Tienda::query()->delete();
        $u = $this->vendedor();

        $this->assertNull(app(TiendaActual::class)->de($u));

        $caja = app(ArqueoDeCaja::class)->abrir($u->id, 100);
        $venta = $this->vender($u, null);

        $this->assertNull($venta->tienda_id);
        $this->assertSame($caja->id, $venta->caja_id);
    }

    // ---- Ventas y caja por tienda ---------------------------------------------------

    public function test_cada_tienda_tiene_su_caja_y_la_venta_entra_en_la_suya(): void
    {
        $arqueo = app(ArqueoDeCaja::class);
        $u = $this->vendedor();

        $cajaCentro = $arqueo->abrir($u->id, 100, null, $this->centro->id);
        $cajaNorte = $arqueo->abrir($u->id, 50, null, $this->norte->id);

        // La segunda en la misma tienda no.
        try {
            $arqueo->abrir($u->id, 10, null, $this->norte->id);
            $this->fail('Abrió dos cajas en la misma tienda.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('en esta tienda', $e->getMessage());
        }

        $venta = $this->vender($u, $this->norte->id, 700);

        $this->assertSame($this->norte->id, $venta->tienda_id);
        $this->assertSame($cajaNorte->id, $venta->caja_id);
        $this->assertSame(5000 + 70000, $arqueo->esperadoEnCentavos($cajaNorte));
        $this->assertSame(10000, $arqueo->esperadoEnCentavos($cajaCentro));
    }

    public function test_la_api_toma_la_tienda_de_la_asistencia_y_la_del_telefono_si_viene(): void
    {
        $u = $this->vendedor();
        $this->enTurno($u, $this->norte);
        Sanctum::actingAs($u);

        $this->getJson('/api/v1/tienda-actual')
            ->assertOk()
            ->assertJsonPath('data.tienda.nombre', 'Norte')
            ->assertJsonPath('data.fuente', 'asistencia')
            ->assertJsonPath('data.puede_cambiar', false);

        $servicio = app(TiendaActual::class);
        $this->assertSame($this->norte->id, $servicio->idPara($u));
        // Una venta cobrada sin conexión trae su tienda: manda esa.
        $this->assertSame($this->centro->id, $servicio->idPara($u, $this->centro->id));
        // Una tienda que no existe se ignora.
        $this->assertSame($this->norte->id, $servicio->idPara($u, 999));
    }

    public function test_elegir_tienda_por_api_y_en_el_panel(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/tienda-actual', ['tienda_id' => $this->norte->id])
            ->assertOk()
            ->assertJsonPath('data.tienda.nombre', 'Norte')
            ->assertJsonPath('data.fuente', 'elegida');

        $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSee('tienda-chip', false);

        Livewire::actingAs($admin)->test(SelectorDeTienda::class)
            ->assertSee('Norte')
            ->call('elegir', $this->centro->id);

        $this->assertSame($this->centro->id, $admin->fresh()->tienda_id);
    }

    public function test_la_tienda_principal_se_queda_con_lo_de_antes(): void
    {
        $u = $this->vendedor();
        $vieja = $this->vender($u, null);
        $caja = Caja::query()->create(['abierta_por' => $u->id, 'abierta_en' => now(), 'monto_inicial' => 0, 'estado' => 'abierta']);

        $asignado = app(TiendaActual::class)->hacerPrincipal($this->centro, $u->id);

        $this->assertSame(1, $asignado['ventas']);
        $this->assertSame(1, $asignado['cajas']);
        $this->assertSame($this->centro->id, $vieja->fresh()->tienda_id);
        $this->assertSame($this->centro->id, $caja->fresh()->tienda_id);
        $this->assertSame($this->centro->id, app(TiendaActual::class)->principal()->id);
    }

    // ---- Reportes -----------------------------------------------------------------

    public function test_el_resumen_del_dia_por_tienda(): void
    {
        $u = $this->vendedor();
        $this->vender($u, $this->centro->id, 500);
        $this->vender($u, $this->norte->id, 300);
        PagoCompra::query()->create([
            'compra_id' => \App\Models\Compra::factory()->create()->id,
            'user_id' => $u->id,
            'monto' => 1000,
            'fecha' => today(),
        ]);

        $resumen = app(ResumenDiario::class);

        $todas = $resumen->del(now());
        $this->assertSame(800.0, $todas['ingresos']['ventas']['cobrado']);
        $this->assertSame(1000.0, $todas['egresos']['proveedores']['total']);
        $this->assertCount(2, $todas['por_tienda']);
        $this->assertSame('Centro', $todas['por_tienda'][0]['tienda']);

        $norte = $resumen->del(now(), $this->norte->id);
        $this->assertSame(300.0, $norte['ingresos']['ventas']['cobrado']);
        // Las compras son del depósito compartido: no son de una tienda.
        $this->assertSame(0.0, $norte['egresos']['proveedores']['total']);
        $this->assertSame([], $norte['por_tienda']);
    }

    public function test_ventas_por_vendedor_por_tienda(): void
    {
        $u = $this->vendedor();
        $this->vender($u, $this->centro->id, 500);
        $this->vender($u, $this->norte->id, 300);

        $todas = app(SeguimientoDeVendedores::class)->entre(now(), now());
        $this->assertSame(800.0, $todas[0]['total']);
        $this->assertCount(2, $todas[0]['tiendas']);

        $soloNorte = app(SeguimientoDeVendedores::class)->entre(now(), now(), null, $this->norte->id);
        $this->assertSame(300.0, $soloNorte[0]['total']);

        Sanctum::actingAs($this->admin());
        $this->getJson('/api/v1/reportes/resumen-diario?tienda_id='.$this->centro->id)
            ->assertOk()
            ->assertJsonPath('data.ingresos.ventas.cobrado', 500)
            ->assertJsonPath('meta.tiendas.0.nombre', 'Centro');
    }
}
