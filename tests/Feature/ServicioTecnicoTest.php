<?php

namespace Tests\Feature;

use App\Livewire\Reparaciones\Index as Taller;
use App\Models\Cliente;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Reparacion;
use App\Models\Unidad;
use App\Models\User;
use App\Support\RegistroDeVenta;
use App\Support\ServicioTecnico;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Servicio técnico.
 *
 * La pieza es pequeña porque el kardex ya existía: una reparación es otro tipo
 * de movimiento sobre una unidad ya identificada por serial. Lo que hay que
 * fijar es que el aparato vuelva al estado del que salió y que la cobertura de
 * garantía no se mueva después de aceptada.
 */
class ServicioTecnicoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function tecnico(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('supervisor');
    }

    /** Una unidad en stock de un producto con [$meses] de garantía. */
    private function unidad(int $meses = 12, string $estado = 'en_stock'): Unidad
    {
        return Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => 1000,
                'stock_minimo' => 0,
                'descuento_maximo' => 0,
                'meses_garantia' => $meses,
            ])->id,
            'estado' => $estado,
            'costo_unitario' => 500,
            'precio_venta' => 1000,
            'ingresado_en' => now()->subMonths(2),
        ]);
    }

    /** Un aparato vendido hoy, que es como llega casi siempre al taller. */
    private function unidadVendida(int $meses = 12): Unidad
    {
        $unidad = $this->unidad($meses);

        app(RegistroDeVenta::class)->registrar(
            lineas: [['unidad_id' => $unidad->id, 'precio_unitario' => 1000, 'descuento' => 0]],
            cabecera: ['cliente_id' => Cliente::factory()->create()->id, 'metodo_pago' => 'efectivo'],
            userId: $this->tecnico()->id,
        );

        return $unidad->refresh();
    }

    private function servicio(): ServicioTecnico
    {
        return app(ServicioTecnico::class);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function recibir(Unidad $unidad, array $datos = []): Reparacion
    {
        return $this->servicio()->recibir(
            $unidad,
            array_merge(['falla_reportada' => 'No enciende'], $datos),
            $this->tecnico()->id,
        );
    }

    // ---- La garantía --------------------------------------------------------

    public function test_la_garantia_cuenta_desde_la_venta_y_no_desde_que_entro_al_almacen(): void
    {
        Carbon::setTestNow('2026-08-30 10:00:00');

        // Entró al depósito hace 2 meses y se vende hoy, con 12 de garantía.
        $unidad = $this->unidadVendida(12);

        // Contarla desde `ingresado_en` le habría quitado al cliente los dos
        // meses que el aparato pasó en la bodega, y esa fecha recortada es la
        // que se imprime en su recibo.
        $this->assertSame('2027-08-30', $unidad->garantia_hasta->toDateString());
        $this->assertTrue($unidad->en_garantia);

        Carbon::setTestNow();
    }

    public function test_sin_vender_la_garantia_es_la_del_proveedor(): void
    {
        Carbon::setTestNow('2026-08-30 10:00:00');

        $unidad = $this->unidad(12);

        // Mientras no se vende, se cuenta desde que entró: es la garantía que
        // el proveedor le dio a la tienda.
        $this->assertSame('2027-06-30', $unidad->garantia_hasta->toDateString());

        Carbon::setTestNow();
    }

    public function test_un_producto_sin_meses_de_garantia_no_tiene_fecha(): void
    {
        $unidad = $this->unidad(0);

        $this->assertNull($unidad->garantia_hasta);
        $this->assertFalse($unidad->en_garantia);
    }

    public function test_la_cobertura_se_congela_al_recibir_el_aparato(): void
    {
        $unidad = $this->unidadVendida(12);
        $reparacion = $this->recibir($unidad);

        $this->assertTrue($reparacion->en_garantia);

        // Alguien cambia los meses del producto al día siguiente.
        $unidad->producto->update(['meses_garantia' => 0]);

        // La orden aceptada como garantía no puede volverse cobrable sola.
        $this->assertTrue($reparacion->refresh()->en_garantia);
        $this->assertSame('0.00', $reparacion->costo);
    }

    public function test_fuera_de_garantia_se_cobra(): void
    {
        $unidad = $this->unidad(0);

        $reparacion = $this->recibir($unidad, ['costo' => 250]);

        $this->assertFalse($reparacion->en_garantia);
        $this->assertSame('250.00', $reparacion->costo);
    }

    public function test_en_garantia_no_se_acepta_un_costo(): void
    {
        $unidad = $this->unidadVendida(12);

        // Un importe aceptado en una orden de garantía sería una promesa rota
        // por escrito.
        $reparacion = $this->recibir($unidad, ['costo' => 300]);

        $this->assertSame('0.00', $reparacion->costo);
    }

    // ---- Entrada y salida del taller ---------------------------------------

    public function test_recibir_saca_el_aparato_del_estado_en_que_estaba_y_lo_deja_en_el_kardex(): void
    {
        $unidad = $this->unidadVendida();

        $reparacion = $this->recibir($unidad);

        $this->assertSame('garantia', $unidad->refresh()->estado);
        $this->assertSame('vendido', $reparacion->estado_unidad_origen);
        $this->assertStringStartsWith('REP-', $reparacion->codigo);

        // El kardex ya existía: una reparación es otro movimiento sobre la
        // misma unidad.
        $movimiento = MovimientoInventario::query()
            ->where('unidad_id', $unidad->id)
            ->latest('id')
            ->first();

        $this->assertSame('garantia', $movimiento->estado_nuevo);
        $this->assertSame('vendido', $movimiento->estado_anterior);
        $this->assertStringContainsString($reparacion->codigo, $movimiento->notas);
    }

    public function test_al_entregar_vuelve_al_estado_del_que_salio(): void
    {
        $unidad = $this->unidadVendida();
        $reparacion = $this->recibir($unidad);

        $reparacion = $this->servicio()->diagnosticar($reparacion, 'Bomba trabada');
        $reparacion = $this->servicio()->marcarLista($reparacion, 'Se cambió la bomba');
        $reparacion = $this->servicio()->entregar($reparacion, 'El cliente');

        $this->assertSame('entregada', $reparacion->estado);
        // Vuelve a `vendido` y no a `en_stock`: el aparato tiene dueño.
        $this->assertSame('vendido', $unidad->refresh()->estado);
    }

    public function test_un_aparato_de_stock_vuelve_al_stock(): void
    {
        $unidad = $this->unidad(0);
        $reparacion = $this->recibir($unidad);

        $reparacion = $this->servicio()->marcarLista($reparacion, 'Se ajustó la puerta');
        $this->servicio()->entregar($reparacion, 'Bodega');

        // Devolverlo a `vendido` habría sacado del catálogo un aparato que
        // nadie compró.
        $this->assertSame('en_stock', $unidad->refresh()->estado);
    }

    public function test_cancelar_devuelve_el_aparato_a_su_estado(): void
    {
        $unidad = $this->unidadVendida();
        $reparacion = $this->recibir($unidad);

        $this->servicio()->cancelar($reparacion, 'Se recibió por error');

        $this->assertSame('cancelada', $reparacion->refresh()->estado);
        $this->assertSame('vendido', $unidad->refresh()->estado);
    }

    public function test_un_aparato_no_entra_dos_veces_al_taller(): void
    {
        $unidad = $this->unidadVendida();
        $this->recibir($unidad);

        // La segunda orden partiría el historial de una misma reparación.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya está en el taller');

        $this->recibir($unidad->refresh());
    }

    public function test_puede_volver_al_taller_una_vez_cerrada_la_anterior(): void
    {
        $unidad = $this->unidadVendida();
        $primera = $this->recibir($unidad);
        $primera = $this->servicio()->marcarLista($primera, 'Arreglado');
        $this->servicio()->entregar($primera, 'El cliente');

        $segunda = $this->recibir($unidad->refresh(), ['falla_reportada' => 'Volvió a fallar']);

        $this->assertSame('recibida', $segunda->estado);
        $this->assertSame(2, Reparacion::count());
    }

    // ---- La ruta del taller -------------------------------------------------

    public function test_el_recorrido_con_espera_de_repuesto(): void
    {
        $reparacion = $this->recibir($this->unidadVendida());

        $reparacion = $this->servicio()->diagnosticar($reparacion, 'Tarjeta quemada');
        $this->assertSame('en_reparacion', $reparacion->estado);

        $reparacion = $this->servicio()->esperarRepuesto($reparacion, 'Tarjeta pedida al proveedor');
        $this->assertSame('esperando_repuesto', $reparacion->estado);
        $this->assertStringContainsString('Tarjeta pedida', $reparacion->notas);

        $reparacion = $this->servicio()->marcarLista($reparacion, 'Se cambió la tarjeta');
        $this->assertSame('lista', $reparacion->estado);
        $this->assertNotNull($reparacion->lista_en);
    }

    public function test_lo_irreparable_no_se_cobra(): void
    {
        $reparacion = $this->recibir($this->unidad(0), ['costo' => 400]);

        $reparacion = $this->servicio()->declararIrreparable($reparacion, 'Tambor partido');

        $this->assertSame('irreparable', $reparacion->estado);
        // No se cobra mano de obra que no arregló nada.
        $this->assertSame('0.00', $reparacion->costo);
    }

    public function test_lo_irreparable_se_puede_entregar_igual(): void
    {
        $unidad = $this->unidadVendida();
        $reparacion = $this->recibir($unidad);
        $reparacion = $this->servicio()->declararIrreparable($reparacion, 'Sin repuesto');

        // El aparato sigue siendo del cliente: viene a recogerlo aunque no
        // tenga arreglo.
        $reparacion = $this->servicio()->entregar($reparacion, 'El cliente');

        $this->assertSame('entregada', $reparacion->estado);
        $this->assertSame('vendido', $unidad->refresh()->estado);
    }

    public function test_entregar_exige_saber_quien_se_lo_lleva(): void
    {
        $reparacion = $this->recibir($this->unidadVendida());
        $reparacion = $this->servicio()->marcarLista($reparacion, 'Arreglado');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('quién se lleva');

        $this->servicio()->entregar($reparacion, '   ');
    }

    public function test_no_se_da_por_lista_una_orden_ya_entregada(): void
    {
        $reparacion = $this->recibir($this->unidadVendida());
        $reparacion = $this->servicio()->marcarLista($reparacion, 'Arreglado');
        $reparacion = $this->servicio()->entregar($reparacion, 'El cliente');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se puede dar por lista');

        $this->servicio()->marcarLista($reparacion, 'Otra vez');
    }

    public function test_hace_falta_decir_que_le_pasa(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('qué le pasa');

        $this->recibir($this->unidadVendida(), ['falla_reportada' => '  ']);
    }

    public function test_lo_atrasado_es_lo_prometido_y_no_hecho(): void
    {
        $reparacion = $this->recibir($this->unidadVendida(), [
            'prometida_para' => today()->addDay()->format('Y-m-d'),
        ]);

        Carbon::setTestNow(today()->addDays(3));

        $this->assertTrue($reparacion->refresh()->esta_atrasada);
        $this->assertSame(1, Reparacion::query()->atrasadas()->count());

        // Ya lista deja de estar atrasada aunque el cliente no venga: el
        // taller cumplió.
        $this->servicio()->marcarLista($reparacion, 'Arreglado');

        $this->assertFalse($reparacion->refresh()->esta_atrasada);
        $this->assertSame(0, Reparacion::query()->atrasadas()->count());

        Carbon::setTestNow();
    }

    // ---- La pantalla --------------------------------------------------------

    public function test_desde_el_taller_se_recibe_un_aparato_por_su_serial(): void
    {
        $unidad = $this->unidadVendida();
        $unidad->update(['serial' => 'SN-TALLER-1']);

        Livewire::actingAs($this->tecnico())
            ->test(Taller::class)
            ->call('abrirRecepcion')
            ->set('buscarUnidad', 'SN-TALLER-1')
            ->assertSee('SN-TALLER-1')
            ->call('elegirUnidad', $unidad->id)
            ->set('fallaReportada', 'No centrifuga')
            ->call('recibir')
            ->assertDispatched('cerrar-modal-recibir-aparato');

        $this->assertSame(1, Reparacion::count());
        $this->assertSame('garantia', $unidad->refresh()->estado);
    }

    public function test_el_tablero_se_abre_y_lista(): void
    {
        $reparacion = $this->recibir($this->unidadVendida());

        $this->actingAs($this->tecnico())
            ->get('/reparaciones')
            ->assertOk()
            ->assertSee($reparacion->codigo)
            ->assertSee('No enciende');
    }

    public function test_sin_permiso_no_se_ve_el_taller(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get('/reparaciones')
            ->assertForbidden();
    }

    public function test_el_vendedor_recibe_aparatos_pero_no_firma_el_trabajo(): void
    {
        // Recibir en el mostrador sí; diagnosticar y dar por lista lo firma el
        // técnico.
        $vendedor = User::factory()->create(['is_active' => true])->syncRoles('vendedor');

        $this->assertTrue($vendedor->can('reparaciones.recibir'));
        $this->assertFalse($vendedor->can('reparaciones.atender'));

        $reparacion = $this->recibir($this->unidadVendida());

        Livewire::actingAs($vendedor)
            ->test(Taller::class)
            ->set('reparacionId', $reparacion->id)
            ->set('trabajoRealizado', 'Lo que sea')
            ->call('marcarLista')
            ->assertForbidden();
    }

    public function test_el_vendedor_si_puede_entregar_el_aparato_al_cliente(): void
    {
        $reparacion = $this->recibir($this->unidadVendida());
        $this->servicio()->marcarLista($reparacion, 'Arreglado');

        // El cliente viene a recoger y no siempre hay alguien del taller
        // delante.
        $vendedor = User::factory()->create(['is_active' => true])->syncRoles('vendedor');

        Livewire::actingAs($vendedor)
            ->test(Taller::class)
            ->call('abrirEntrega', $reparacion->id)
            ->set('entregadaA', 'El cliente')
            ->call('entregar')
            ->assertDispatched('cerrar-modal-entregar-reparacion');

        $this->assertSame('entregada', $reparacion->refresh()->estado);
    }
}
