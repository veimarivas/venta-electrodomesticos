<?php

namespace Tests\Feature;

use App\Events\VentaRegistrada;
use App\Livewire\Reportes\Index as ReportesIndex;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\RegistroDeVenta;
use App\Support\Reportes;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create()->syncRoles('admin');
    }

    /** Registra una venta real de una unidad en stock. */
    private function vender(float $precio = 1500, float $costo = 1000, ?User $vendedor = null): Venta
    {
        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create(['precio_venta' => $precio])->id,
            'estado' => 'en_stock',
            'costo_unitario' => $costo,
            'precio_venta' => $precio,
        ]);

        return app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => (string) $precio, 'descuento' => '0']],
            [],
            ($vendedor ?? $this->admin())->id
        );
    }

    // ---- Resumen del período ----------------------------------------------

    public function test_el_resumen_suma_ingresos_y_ganancias(): void
    {
        $this->vender(1500, 1000);
        $this->vender(2000, 1200);

        $resumen = app(Reportes::class)->resumen(now()->startOfMonth(), now()->endOfMonth());

        $this->assertSame(2, $resumen['ventas']);
        $this->assertSame(2, $resumen['unidades']);
        $this->assertSame(3500.0, $resumen['ingreso']);
        $this->assertSame(1300.0, $resumen['ganancia']);
        $this->assertSame(1750.0, $resumen['ticket']);
    }

    public function test_el_resumen_descarta_las_ventas_anuladas(): void
    {
        // Una anulada devolvió su mercadería y su dinero: sumarla inflaría
        // todos los indicadores.
        $this->vender(1500, 1000);
        $anulada = $this->vender(9000, 1000);

        app(RegistroDeVenta::class)->anular($anulada, 'Devolución');

        $resumen = app(Reportes::class)->resumen(now()->startOfMonth(), now()->endOfMonth());

        $this->assertSame(1, $resumen['ventas']);
        $this->assertSame(1500.0, $resumen['ingreso']);
    }

    public function test_el_resumen_respeta_el_rango_de_fechas(): void
    {
        $vieja = $this->vender(1000, 500);
        $vieja->update(['vendida_en' => now()->subMonths(2)]);

        $this->vender(2000, 1000);

        $resumen = app(Reportes::class)->resumen(now()->startOfMonth(), now()->endOfMonth());

        $this->assertSame(1, $resumen['ventas']);
        $this->assertSame(2000.0, $resumen['ingreso']);
    }

    public function test_sin_ventas_el_ticket_promedio_no_divide_por_cero(): void
    {
        $resumen = app(Reportes::class)->resumen(now()->startOfMonth(), now()->endOfMonth());

        $this->assertSame(0, $resumen['ventas']);
        $this->assertSame(0.0, $resumen['ticket']);
        $this->assertSame(0.0, $resumen['margen']);
    }

    // ---- Serie diaria ------------------------------------------------------

    public function test_la_serie_rellena_los_dias_sin_ventas(): void
    {
        // Una gráfica que salta del lunes al jueves miente sobre el ritmo del
        // negocio.
        $this->vender(1500, 1000);

        $serie = app(Reportes::class)->porDia(now()->subDays(4)->startOfDay(), now()->endOfDay());

        $this->assertCount(5, $serie);
        $this->assertSame(0.0, $serie->first()['ingreso']);
        $this->assertSame(1500.0, $serie->last()['ingreso']);
    }

    // ---- Top de productos --------------------------------------------------

    public function test_el_top_ordena_por_unidades_vendidas(): void
    {
        $popular = Producto::factory()->create(['nombre' => 'TV popular']);
        $otro = Producto::factory()->create(['nombre' => 'Lavadora suelta']);
        $admin = $this->admin();

        foreach (range(1, 3) as $i) {
            $unidad = Unidad::factory()->create([
                'producto_id' => $popular->id,
                'estado' => 'en_stock',
                'costo_unitario' => 1000,
                'precio_venta' => 1500,
            ]);

            app(RegistroDeVenta::class)->registrar(
                [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
                [],
                $admin->id
            );
        }

        $unidad = Unidad::factory()->create([
            'producto_id' => $otro->id,
            'estado' => 'en_stock',
            'costo_unitario' => 1000,
            'precio_venta' => 1500,
        ]);

        app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $admin->id
        );

        $top = app(Reportes::class)->topProductos(now()->startOfMonth(), now()->endOfMonth());

        $this->assertSame('TV popular', $top->first()->nombre);
        $this->assertSame(3, (int) $top->first()->unidades);
    }

    // ---- Por vendedor ------------------------------------------------------

    public function test_agrupa_las_ventas_por_vendedor(): void
    {
        $ana = User::factory()->create(['name' => 'Ana'])->syncRoles('vendedor');
        $luis = User::factory()->create(['name' => 'Luis'])->syncRoles('vendedor');

        $this->vender(2000, 1000, $ana);
        $this->vender(1000, 500, $luis);

        $porVendedor = app(Reportes::class)->porVendedor(now()->startOfMonth(), now()->endOfMonth());

        // Ordenado por ingreso: Ana primero.
        $this->assertSame('Ana', $porVendedor->first()->name);
        $this->assertSame(2, $porVendedor->count());
    }

    // ---- Inventario --------------------------------------------------------

    public function test_el_valor_del_inventario_solo_cuenta_lo_disponible(): void
    {
        Unidad::factory()->create(['estado' => 'en_stock', 'costo_unitario' => 1000, 'precio_venta' => 1500]);
        Unidad::factory()->create(['estado' => 'vendido', 'costo_unitario' => 800, 'precio_venta' => 1200]);
        Unidad::factory()->create(['estado' => 'danado', 'costo_unitario' => 700, 'precio_venta' => 900]);

        $inventario = app(Reportes::class)->inventarioEnStock();

        $this->assertSame(1, $inventario['unidades']);
        $this->assertSame(1000.0, $inventario['costo']);
        $this->assertSame(1500.0, $inventario['valor']);
        $this->assertSame(500.0, $inventario['potencial']);
    }

    // ---- Pantalla ----------------------------------------------------------

    public function test_los_atajos_de_periodo_ajustan_las_fechas(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ReportesIndex::class)
            ->assertSet('periodo', 'mes')
            ->call('aplicarPeriodo', 'hoy')
            ->assertSet('desde', now()->format('Y-m-d'))
            ->assertSet('hasta', now()->format('Y-m-d'))
            ->call('aplicarPeriodo', 'anio')
            ->assertSet('desde', now()->startOfYear()->format('Y-m-d'));
    }

    public function test_elegir_fechas_a_mano_cambia_el_periodo_a_rango(): void
    {
        Livewire::actingAs($this->admin())
            ->test(ReportesIndex::class)
            ->set('desde', now()->subWeek()->format('Y-m-d'))
            ->assertSet('periodo', 'rango');
    }

    public function test_un_rango_invertido_no_deja_el_reporte_vacio(): void
    {
        // Si el usuario pone «hasta» antes que «desde», el reporte saldría sin
        // datos y parecería que no hubo ventas.
        $this->vender(1500, 1000);

        Livewire::actingAs($this->admin())
            ->test(ReportesIndex::class)
            ->set('desde', now()->format('Y-m-d'))
            ->set('hasta', now()->subWeek()->format('Y-m-d'))
            ->assertOk();
    }

    public function test_la_pantalla_exige_el_permiso_de_ver_reportes(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)->get('/reportes')->assertForbidden();
        $this->actingAs($this->admin())->get('/reportes')->assertOk();
    }

    // ---- Dashboard en vivo -------------------------------------------------

    public function test_registrar_una_venta_dispara_el_evento_de_broadcast(): void
    {
        Event::fake([VentaRegistrada::class]);

        $venta = $this->vender(1500, 1000);

        Event::assertDispatched(
            VentaRegistrada::class,
            fn (VentaRegistrada $evento): bool => $evento->venta->id === $venta->id
        );
    }

    public function test_el_evento_viaja_por_el_canal_privado_de_ventas(): void
    {
        $venta = $this->vender(1500, 1000);
        $evento = new VentaRegistrada($venta);

        $canales = $evento->broadcastOn();

        $this->assertSame('private-ventas', $canales[0]->name);
        $this->assertSame('VentaRegistrada', $evento->broadcastAs());
    }

    public function test_el_evento_se_emite_sin_pasar_por_la_cola(): void
    {
        // Con ShouldBroadcast (encolado) el broadcast se queda en la tabla
        // `jobs` hasta que alguien levante un queue:work, y el panel no se
        // entera de nada. Un panel "en vivo" no puede depender de eso.
        $this->assertInstanceOf(
            \Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class,
            new VentaRegistrada($this->vender(1500, 1000))
        );
    }

    public function test_los_componentes_escuchan_el_nombre_de_evento_correcto(): void
    {
        // El punto de '.VentaRegistrada' es obligatorio: sin él Echo antepone
        // el namespace y escucha 'App.Events.VentaRegistrada', mientras que el
        // servidor emite 'VentaRegistrada'. El evento llegaba al navegador y
        // nadie lo recogía.
        foreach ([ReportesIndex::class, \App\Livewire\Dashboard\Panel::class] as $componente) {
            $metodo = new \ReflectionMethod($componente, 'alRegistrarseUnaVenta');
            $atributos = $metodo->getAttributes(\Livewire\Attributes\On::class);

            $this->assertNotEmpty($atributos, "{$componente} no escucha el evento.");
            $this->assertSame(
                'echo-private:ventas,.VentaRegistrada',
                $atributos[0]->getArguments()[0],
                "{$componente} escucha un nombre de evento que Echo nunca recibirá."
            );
        }
    }

    public function test_el_payload_es_liviano_y_no_expone_costos(): void
    {
        // Es un canal que escuchan varios usuarios: mandar el modelo entero
        // arrastraría costos y datos del cliente.
        $venta = $this->vender(1500, 1000);

        $payload = (new VentaRegistrada($venta))->broadcastWith();

        $this->assertSame(
            ['id', 'codigo', 'total', 'ganancia', 'unidades', 'vendedor', 'cliente', 'productos', 'hora'],
            array_keys($payload)
        );
        $this->assertSame($venta->codigo, $payload['codigo']);
        $this->assertSame(1500.0, $payload['total']);
        $this->assertArrayNotHasKey('costo_total', $payload);
    }

    public function test_el_panel_en_vivo_recibe_las_ventas_y_las_recorta(): void
    {
        $componente = Livewire::actingAs($this->admin())->test(ReportesIndex::class);

        foreach (range(1, 10) as $i) {
            $componente->call('alRegistrarseUnaVenta', [
                'id' => $i,
                'codigo' => "VTA-2026-00000{$i}",
                'total' => 100.0,
                'ganancia' => 20.0,
                'unidades' => 1,
                'vendedor' => 'Ana',
                'cliente' => 'Público general',
                'productos' => ['TV'],
                'hora' => '10:00',
            ]);
        }

        // Es un vistazo de lo que acaba de pasar, no un historial.
        $componente->assertCount('enVivo', 8);
        // La más reciente va arriba.
        $componente->assertSet('enVivo.0.id', 10);
    }

    public function test_solo_quien_ve_reportes_escucha_el_canal(): void
    {
        // El payload lleva importes y ganancias: un vendedor no tiene por qué
        // ver el resultado global de la tienda en tiempo real.
        $admin = $this->admin();
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        // El canal 'ventas' de routes/channels.php autoriza con este permiso.
        $this->assertTrue($admin->can('reportes.ver'));
        $this->assertFalse($vendedor->can('reportes.ver'));

        // Y la autorización real lo rechaza. En los tests el driver es 'null',
        // que no comprueba canales, así que hay que forzar uno real. Solo se
        // prueba el camino de denegación: el de permiso llegaría a firmar la
        // respuesta con el cliente de Pusher, que no existe en pruebas.
        config(['broadcasting.default' => 'reverb']);

        $this->actingAs($vendedor);

        $this->expectException(AccessDeniedHttpException::class);

        Broadcast::auth(Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'private-ventas',
            'socket_id' => '1234.5678',
        ]));
    }
}
