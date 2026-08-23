<?php

namespace Tests\Feature;

use App\Livewire\Ventas\Index as VentasIndex;
use App\Livewire\Ventas\Pos;
use App\Models\Cliente;
use App\Models\MovimientoInventario;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\QrCobro;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Support\GeneradorCodigoVenta;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class VentaCrudTest extends TestCase
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

    /** Unidad en stock, lista para vender. */
    private function unidadEnStock(float $costo = 1000, float $precio = 1500, float $descuentoMaximo = 0): Unidad
    {
        return Unidad::factory()->create([
            'producto_id' => Producto::factory()->create([
                'precio_venta' => $precio,
                'descuento_maximo' => $descuentoMaximo,
            ])->id,
            'estado' => 'en_stock',
            'costo_unitario' => $costo,
            'precio_venta' => $precio,
        ]);
    }

    // ---- Código correlativo -----------------------------------------------

    public function test_el_primer_codigo_lleva_el_año_y_seis_digitos(): void
    {
        $this->assertSame(
            'VTA-'.now()->format('Y').'-000001',
            app(GeneradorCodigoVenta::class)->siguiente()
        );
    }

    public function test_el_codigo_de_una_venta_anulada_no_se_reutiliza(): void
    {
        // Reutilizarlo rompería el histórico y el índice único lo rechazaría.
        $unidad = $this->unidadEnStock();

        $venta = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        app(RegistroDeVenta::class)->anular($venta, 'Prueba');

        $this->assertSame(
            'VTA-'.now()->format('Y').'-000002',
            app(GeneradorCodigoVenta::class)->siguiente()
        );
    }

    // ---- Venta atómica -----------------------------------------------------

    public function test_registra_la_venta_y_descuenta_el_stock(): void
    {
        $unidad = $this->unidadEnStock(1000, 1500);
        $admin = $this->admin();

        $venta = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            ['metodo_pago' => 'efectivo'],
            $admin->id
        );

        $this->assertSame('completada', $venta->estado);
        $this->assertSame('1500.00', $venta->total);
        $this->assertSame('1000.00', $venta->costo_total);
        $this->assertSame('500.00', $venta->ganancia);

        // El aparato sale del stock.
        $this->assertSame('vendido', $unidad->fresh()->estado);
        $this->assertNotNull($unidad->fresh()->vendido_en);
    }

    public function test_el_costo_se_congela_al_vender(): void
    {
        // Si mañana cambia el costo del aparato, la ganancia histórica no se
        // debe mover.
        $unidad = $this->unidadEnStock(1000, 1500);

        $venta = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        $unidad->update(['costo_unitario' => 9999]);

        $this->assertSame('1000.00', $venta->detalles()->first()->costo_unitario);
        $this->assertSame('500.00', $venta->fresh()->ganancia);
    }

    public function test_el_descuento_baja_el_total_y_la_ganancia(): void
    {
        // El producto autoriza rebajar hasta 300; sin ese tope la venta se
        // rechazaría, que es lo que comprueba el test del descuento no
        // autorizado.
        $unidad = $this->unidadEnStock(1000, 1500, 300);

        $venta = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '200']],
            [],
            $this->admin()->id
        );

        $this->assertSame('1500.00', $venta->subtotal);
        $this->assertSame('200.00', $venta->descuento);
        $this->assertSame('1300.00', $venta->total);
        $this->assertSame('300.00', $venta->ganancia);
        $this->assertSame('300.00', $venta->detalles()->first()->ganancia);
    }

    public function test_no_se_puede_vender_una_unidad_que_no_esta_en_stock(): void
    {
        $unidad = $this->unidadEnStock();
        $unidad->update(['estado' => 'danado']);

        $this->expectException(RuntimeException::class);

        app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );
    }

    public function test_no_se_puede_vender_dos_veces_la_misma_unidad(): void
    {
        $unidad = $this->unidadEnStock();
        $admin = $this->admin();

        app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $admin->id
        );

        try {
            app(RegistroDeVenta::class)->registrar(
                [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
                [],
                $admin->id
            );
            $this->fail('Se vendió dos veces el mismo aparato.');
        } catch (RuntimeException $e) {
            // Esperado.
        }

        // Solo queda la primera venta: la segunda no dejó nada a medias.
        $this->assertSame(1, Venta::count());
        $this->assertSame(1, VentaDetalle::where('unidad_id', $unidad->id)->count());
    }

    public function test_el_indice_unico_impide_la_doble_venta_a_nivel_de_base(): void
    {
        // La comprobación en PHP no basta: dos cajeros escaneando a la vez la
        // pasarían los dos. Esta es la última línea de defensa.
        $unidad = $this->unidadEnStock();
        $venta = Venta::factory()->create();
        $otra = Venta::factory()->create();

        VentaDetalle::factory()->create([
            'venta_id' => $venta->id,
            'unidad_id' => $unidad->id,
            'unidad_vendida_id' => $unidad->id,
            'producto_id' => $unidad->producto_id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        VentaDetalle::factory()->create([
            'venta_id' => $otra->id,
            'unidad_id' => $unidad->id,
            'unidad_vendida_id' => $unidad->id,
            'producto_id' => $unidad->producto_id,
        ]);
    }

    public function test_si_una_unidad_falla_no_queda_media_venta(): void
    {
        // La venta es atómica: o entran todos los aparatos o ninguno.
        $buena = $this->unidadEnStock();
        $mala = $this->unidadEnStock();
        $mala->update(['estado' => 'vendido']);

        try {
            app(RegistroDeVenta::class)->registrar(
                [
                    ['unidad_id' => $buena->id, 'precio_unitario' => '1500', 'descuento' => '0'],
                    ['unidad_id' => $mala->id, 'precio_unitario' => '1500', 'descuento' => '0'],
                ],
                [],
                $this->admin()->id
            );
            $this->fail('Se registró una venta con un aparato no disponible.');
        } catch (RuntimeException $e) {
            // Esperado.
        }

        $this->assertSame(0, Venta::count());
        $this->assertSame(0, VentaDetalle::count());
        // El aparato bueno sigue disponible.
        $this->assertSame('en_stock', $buena->fresh()->estado);
    }

    public function test_el_descuento_no_puede_superar_el_precio(): void
    {
        $unidad = $this->unidadEnStock();

        $this->expectException(RuntimeException::class);

        app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1000', 'descuento' => '1500']],
            [],
            $this->admin()->id
        );
    }

    // ---- Kardex ------------------------------------------------------------

    public function test_la_venta_deja_una_salida_en_el_kardex(): void
    {
        $unidad = $this->unidadEnStock();

        $venta = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        $movimiento = MovimientoInventario::where('unidad_id', $unidad->id)
            ->where('tipo', 'salida')
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertSame('en_stock', $movimiento->estado_anterior);
        $this->assertSame('vendido', $movimiento->estado_nuevo);
        $this->assertSame(Venta::class, $movimiento->origen_type);
        $this->assertSame($venta->id, $movimiento->origen_id);
    }

    // ---- Anulación ---------------------------------------------------------

    public function test_anular_devuelve_los_aparatos_al_stock(): void
    {
        $unidad = $this->unidadEnStock();

        $venta = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        $devueltas = app(RegistroDeVenta::class)->anular($venta, 'El cliente devolvió el equipo');

        $this->assertSame(1, $devueltas);
        $this->assertSame('en_stock', $unidad->fresh()->estado);
        $this->assertNull($unidad->fresh()->vendido_en);

        $venta = $venta->fresh();

        // La venta NO se borra: queda con su motivo.
        $this->assertSame('anulada', $venta->estado);
        $this->assertNotNull($venta->anulada_en);
        $this->assertSame('El cliente devolvió el equipo', $venta->motivo_anulacion);
        $this->assertSame(1, $venta->detalles()->count());
    }

    public function test_la_anulacion_deja_su_rastro_en_el_kardex(): void
    {
        $unidad = $this->unidadEnStock();

        $venta = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        app(RegistroDeVenta::class)->anular($venta, 'Devolución');

        $tipos = MovimientoInventario::where('unidad_id', $unidad->id)
            ->orderBy('id')
            ->pluck('tipo')
            ->all();

        // Salida al venderla, devolución al anular y vuelta al stock. El
        // kardex cuenta lo que pasó, no solo dónde acabó el aparato.
        $this->assertSame(['salida', 'devolucion', 'ajuste'], $tipos);
    }

    public function test_una_venta_anulada_no_se_puede_anular_dos_veces(): void
    {
        $unidad = $this->unidadEnStock();

        $venta = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        app(RegistroDeVenta::class)->anular($venta, 'Primera');

        $this->expectException(RuntimeException::class);

        app(RegistroDeVenta::class)->anular($venta->fresh(), 'Segunda');
    }

    public function test_una_unidad_devuelta_se_puede_volver_a_vender(): void
    {
        // El aparato vuelve al stock: tiene que poder venderse otra vez. La
        // guardia de la doble venta se suelta al anular (unidad_vendida_id
        // pasa a NULL) sin borrar la línea del histórico.
        $unidad = $this->unidadEnStock();
        $admin = $this->admin();

        $primera = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $admin->id
        );

        app(RegistroDeVenta::class)->anular($primera, 'Devolución');

        $segunda = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1400', 'descuento' => '0']],
            [],
            $admin->id
        );

        $this->assertSame('completada', $segunda->estado);
        $this->assertSame('vendido', $unidad->fresh()->estado);

        // Las dos líneas conviven: el histórico conserva ambas ventas.
        $this->assertSame(2, VentaDetalle::where('unidad_id', $unidad->id)->count());
        // Pero solo la viva mantiene la guardia puesta.
        $this->assertSame(
            1,
            VentaDetalle::where('unidad_id', $unidad->id)->whereNotNull('unidad_vendida_id')->count()
        );
    }

    // ---- Punto de venta (Livewire) ----------------------------------------

    public function test_el_pos_agrega_al_carrito_y_cobra(): void
    {
        $unidad = $this->unidadEnStock(1000, 1500);

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->assertCount('carrito', 1)
            ->assertSet('carrito.0.precio', '1500.00')
            ->assertSet('ventaValida', true)
            ->call('cobrar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success')
            ->assertCount('carrito', 0);

        $this->assertSame(1, Venta::count());
        $this->assertSame('vendido', $unidad->fresh()->estado);
    }

    public function test_el_pos_no_ofrece_aparatos_que_no_estan_en_stock(): void
    {
        $disponible = $this->unidadEnStock();
        $vendido = $this->unidadEnStock();
        $vendido->update(['estado' => 'vendido']);

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->set('buscar', $disponible->codigo_interno)
            ->assertSee($disponible->codigo_interno);

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $vendido->id)
            ->assertDispatched('toast', tipo: 'error')
            ->assertCount('carrito', 0);
    }

    public function test_el_pos_no_repite_el_mismo_aparato_en_el_carrito(): void
    {
        $unidad = $this->unidadEnStock();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->call('agregar', $unidad->id)
            ->assertCount('carrito', 1);
    }

    public function test_el_pos_registra_la_venta_con_su_cliente(): void
    {
        Storage::fake('public');

        $unidad = $this->unidadEnStock();
        $cliente = Cliente::factory()->create();
        $qr = QrCobro::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->call('elegirCliente', $cliente->id)
            ->set('metodoPago', 'qr')
            ->set('comprobante', UploadedFile::fake()->image('respaldo.jpg'))
            ->call('cobrar')
            ->assertHasNoErrors();

        $venta = Venta::first();

        $this->assertSame($cliente->id, $venta->cliente_id);
        $this->assertSame('qr', $venta->metodo_pago);
        $this->assertSame($qr->id, $venta->qr_cobro_id);
        // Todo el importe entró por el banco, nada por caja.
        $this->assertSame($venta->total, $venta->monto_qr);
        $this->assertSame('0.00', $venta->monto_efectivo);
        Storage::disk('public')->assertExists($venta->comprobante_qr);
    }

    // ---- Búsqueda del mostrador --------------------------------------------

    public function test_el_buscador_muestra_los_aparatos_ya_vendidos_con_su_venta(): void
    {
        // Con la etiqueta delante, «sin resultados» no dice si se tecleó mal
        // o si el aparato salió esta mañana.
        $unidad = $this->unidadEnStock();
        $unidad->update(['serial' => 'SN-VENDIDO']);

        $venta = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->set('buscar', 'SN-VENDIDO')
            ->assertCount('coincidencias', 1)
            ->assertSee($venta->codigo)
            ->assertSee('Vendido el')
            // Y no se puede agregar: sigue sin ser vendible.
            ->call('agregar', $unidad->id)
            ->assertDispatched('toast', tipo: 'error')
            ->assertCount('carrito', 0);
    }

    public function test_si_el_codigo_no_existe_el_buscador_lo_dice(): void
    {
        $this->unidadEnStock();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->set('buscar', 'NO-EXISTE-9999')
            ->assertSet('busquedaSinResultados', true)
            ->assertSee('No existe ningún aparato');
    }

    // ---- Confirmaciones ----------------------------------------------------

    public function test_quitar_un_aparato_pasa_por_el_modal_de_confirmacion(): void
    {
        $unidad = $this->unidadEnStock();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->call('confirmarQuitar', 0)
            ->assertDispatched('abrir-modal-quitar-linea')
            // Preguntar no quita nada todavía.
            ->assertCount('carrito', 1)
            ->call('quitar')
            ->assertDispatched('cerrar-modal-quitar-linea')
            ->assertCount('carrito', 0);
    }

    public function test_vaciar_el_carrito_pasa_por_el_modal_de_confirmacion(): void
    {
        $unidad = $this->unidadEnStock();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->call('confirmarVaciar')
            ->assertDispatched('abrir-modal-vaciar-carrito')
            ->assertCount('carrito', 1)
            ->call('vaciarCarrito')
            ->assertDispatched('cerrar-modal-vaciar-carrito')
            ->assertCount('carrito', 0);
    }

    public function test_cobrar_pasa_por_un_repaso_antes_de_registrar(): void
    {
        $unidad = $this->unidadEnStock();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->call('confirmarCobro')
            ->assertDispatched('abrir-modal-confirmar-cobro');

        // El repaso no cobra: la venta se registra al confirmar.
        $this->assertSame(0, Venta::count());
    }

    public function test_el_repaso_no_se_abre_con_la_venta_incompleta(): void
    {
        $unidad = $this->unidadEnStock();
        QrCobro::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            // Por QR sin respaldo: no hay nada que confirmar todavía.
            ->set('metodoPago', 'qr')
            ->call('confirmarCobro')
            ->assertNotDispatched('abrir-modal-confirmar-cobro')
            ->assertHasErrors('comprobante');
    }

    // ---- Métodos de pago del mostrador -------------------------------------

    public function test_el_mostrador_solo_cobra_en_efectivo_qr_o_mixto(): void
    {
        // Tarjeta y transferencia siguen en el enum por el histórico, pero ya
        // no se ofrecen ni se aceptan al cobrar.
        $unidad = $this->unidadEnStock();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->set('metodoPago', 'tarjeta')
            ->call('confirmarCobro')
            ->assertHasErrors('metodoPago')
            ->assertNotDispatched('abrir-modal-confirmar-cobro');

        $this->assertSame(0, Venta::count());
    }

    // ---- Precio de referencia y tope de descuento -------------------------

    public function test_el_carrito_arranca_con_el_precio_de_lista_y_sin_descuento(): void
    {
        $unidad = $this->unidadEnStock(1000, 1500, 200);

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->assertSet('carrito.0.precio_lista', '1500.00')
            ->assertSet('carrito.0.precio', '1500.00')
            ->assertSet('carrito.0.tope_descuento', '200.00')
            ->assertSet('descuentoEnCentavos', 0);
    }

    public function test_bajar_el_precio_se_registra_como_descuento(): void
    {
        // El caso del mostrador: lista 400, se pacta 350, quedan 50 de rebaja.
        $unidad = $this->unidadEnStock(200, 400, 100);

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->set('carrito.0.precio', '350')
            ->assertSet('descuentoEnCentavos', 5000)
            ->assertSet('totalEnCentavos', 35000)
            ->call('cobrar')
            ->assertHasNoErrors();

        $detalle = VentaDetalle::first();

        $this->assertSame('400.00', $detalle->precio_unitario);
        $this->assertSame('50.00', $detalle->descuento);
        $this->assertSame('350.00', Venta::first()->total);
    }

    public function test_el_pos_no_deja_rebajar_mas_alla_del_tope_del_producto(): void
    {
        $unidad = $this->unidadEnStock(200, 400, 50);

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->set('carrito.0.precio', '300')
            ->assertHasErrors('carrito.0.precio')
            ->assertSet('ventaValida', false);

        $this->assertSame(0, Venta::count());
    }

    public function test_el_pos_no_deja_cobrar_por_encima_del_precio_de_referencia(): void
    {
        $unidad = $this->unidadEnStock(200, 400, 50);

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->set('carrito.0.precio', '450')
            ->assertHasErrors('carrito.0.precio')
            ->assertSet('ventaValida', false);
    }

    public function test_el_servicio_rechaza_un_descuento_no_autorizado(): void
    {
        // Defensa en el servidor: el POS ya lo impide, pero el servicio es la
        // puerta que también usa la API.
        $unidad = $this->unidadEnStock(200, 400, 50);

        $this->expectException(RuntimeException::class);

        app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '400', 'descuento' => '100']],
            [],
            $this->admin()->id
        );
    }

    // ---- Cobro por QR y pago mixto ----------------------------------------

    public function test_el_pos_no_ofrece_un_qr_caducado(): void
    {
        $vigente = QrCobro::factory()->create(['nombre' => 'QR vigente']);
        QrCobro::factory()->caducado()->create(['nombre' => 'QR viejo']);

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->set('metodoPago', 'qr')
            ->assertSet('qrCobroId', $vigente->id)
            ->assertSee('QR vigente')
            ->assertDontSee('QR viejo');
    }

    public function test_no_se_puede_cobrar_por_qr_sin_respaldo(): void
    {
        $unidad = $this->unidadEnStock();
        QrCobro::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->set('metodoPago', 'qr')
            ->assertSet('ventaValida', false)
            ->call('cobrar')
            ->assertHasErrors('comprobante');

        $this->assertSame(0, Venta::count());
    }

    public function test_el_pago_mixto_reparte_el_total_entre_caja_y_banco(): void
    {
        Storage::fake('public');

        $unidad = $this->unidadEnStock(600, 1000);
        QrCobro::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->set('metodoPago', 'mixto')
            ->set('montoEfectivo', '400')
            // Teclear una parte completa la otra con la diferencia.
            ->assertSet('montoQr', '600.00')
            ->assertSet('diferenciaMixtoEnCentavos', 0)
            ->set('comprobante', UploadedFile::fake()->image('respaldo.jpg'))
            ->call('cobrar')
            ->assertHasNoErrors();

        $venta = Venta::first();

        $this->assertSame('mixto', $venta->metodo_pago);
        $this->assertSame('400.00', $venta->monto_efectivo);
        $this->assertSame('600.00', $venta->monto_qr);
    }

    public function test_el_mixto_arranca_sin_repartir(): void
    {
        // Un reparto propuesto por el sistema sería dinero que nadie contó.
        $unidad = $this->unidadEnStock(600, 1000);
        QrCobro::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->set('metodoPago', 'mixto')
            ->assertSet('montoEfectivo', '')
            ->assertSet('montoQr', '')
            ->assertSet('ventaValida', false);
    }

    public function test_el_reparto_del_mixto_se_puede_corregir_por_cualquiera_de_los_dos_campos(): void
    {
        $unidad = $this->unidadEnStock(600, 1000);
        QrCobro::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->set('metodoPago', 'mixto')
            ->set('montoEfectivo', '200')
            ->assertSet('montoQr', '800.00')
            // Corregir el otro campo rehace el reparto en sentido contrario.
            ->set('montoQr', '250')
            ->assertSet('montoEfectivo', '750.00')
            // Y vaciar uno deja el reparto otra vez sin hacer.
            ->set('montoEfectivo', '')
            ->assertSet('montoQr', '')
            ->assertSet('ventaValida', false);
    }

    public function test_el_reparto_del_mixto_no_puede_superar_el_total(): void
    {
        $unidad = $this->unidadEnStock(600, 1000);
        QrCobro::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->set('metodoPago', 'mixto')
            // Se recorta al total: cobrar de más es cambio, no venta.
            ->set('montoEfectivo', '1500')
            ->assertSet('montoEfectivo', '1000.00')
            ->assertSet('montoQr', '0.00');
    }

    public function test_el_servicio_rechaza_un_mixto_que_no_cuadra(): void
    {
        $unidad = $this->unidadEnStock(600, 1000);
        $qr = QrCobro::factory()->create();

        $this->expectException(RuntimeException::class);

        app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1000', 'descuento' => '0']],
            [
                'metodo_pago' => 'mixto',
                'qr_cobro_id' => $qr->id,
                'comprobante_qr' => 'comprobantes-qr/x.jpg',
                'monto_efectivo' => '400',
                'monto_qr' => '400',
            ],
            $this->admin()->id
        );
    }

    // ---- Alta rápida de cliente -------------------------------------------

    public function test_el_pos_registra_un_cliente_nuevo_y_lo_deja_en_la_venta(): void
    {
        $unidad = $this->unidadEnStock();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->set('buscarCliente', '9876543')
            ->call('abrirNuevoCliente')
            // El carnet tecleado en el buscador se aprovecha.
            ->assertSet('nuevoCarnet', '9876543')
            ->set('nuevoNombres', 'Rosa')
            ->set('nuevoApellidoPaterno', 'Quispe')
            ->call('guardarCliente')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-cliente-pos')
            ->call('cobrar')
            ->assertHasNoErrors();

        $cliente = Cliente::firstOrFail();

        $this->assertSame('9876543', $cliente->persona->carnet);
        $this->assertSame($cliente->id, Venta::first()->cliente_id);
    }

    public function test_si_no_es_cliente_el_pos_lo_busca_entre_las_personas(): void
    {
        // Mucha gente ya está en `personas` porque trabaja aquí: teclear otra
        // vez su carnet la duplicaría, y el índice único lo rechazaría.
        $persona = Persona::factory()->create(['nombres' => 'Rosario', 'carnet' => '9876543']);

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->set('buscarCliente', '9876543')
            ->assertCount('clientesEncontrados', 0)
            ->assertCount('personasEncontradas', 1)
            // Con una persona a mano, el alta de cero no se ofrece.
            ->assertSet('clienteSinResultados', false)
            ->assertSee($persona->nombre_completo);
    }

    public function test_elegir_una_persona_la_registra_como_cliente_y_la_deja_en_la_venta(): void
    {
        $unidad = $this->unidadEnStock();
        $persona = Persona::factory()->create(['carnet' => '9876543']);

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('agregar', $unidad->id)
            ->set('buscarCliente', '9876543')
            ->call('registrarPersonaComoCliente', $persona->id)
            ->assertDispatched('toast', tipo: 'success')
            ->call('cobrar')
            ->assertHasNoErrors();

        $cliente = Cliente::firstOrFail();

        // La ficha usa los datos que la persona ya tenía: no se duplica nada.
        $this->assertSame($persona->id, $cliente->persona_id);
        $this->assertSame(1, Persona::where('carnet', '9876543')->count());
        $this->assertSame($cliente->id, Venta::firstOrFail()->cliente_id);
    }

    public function test_una_persona_con_ficha_archivada_se_restaura_en_vez_de_duplicarse(): void
    {
        $cliente = Cliente::factory()->create();
        $codigo = $cliente->codigo;
        $cliente->delete();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->set('buscarCliente', $cliente->persona->carnet)
            ->call('registrarPersonaComoCliente', $cliente->persona_id)
            ->assertSet('clienteId', $cliente->id);

        // Misma ficha, mismo código: su historial de compras sigue colgando
        // de ella, y el índice único de persona_id rechazaría una segunda.
        $this->assertSame(1, Cliente::withTrashed()->count());
        $this->assertSame($codigo, $cliente->fresh()->codigo);
        $this->assertNull($cliente->fresh()->deleted_at);
    }

    public function test_quien_ya_es_cliente_no_aparece_como_persona_suelta(): void
    {
        $cliente = Cliente::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->set('buscarCliente', $cliente->persona->carnet)
            ->assertCount('clientesEncontrados', 1)
            ->assertCount('personasEncontradas', 0);
    }

    public function test_el_alta_rapida_exige_haber_buscado_al_cliente_antes(): void
    {
        // Sin búsqueda previa: el modal no se abre.
        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->call('abrirNuevoCliente')
            ->assertDispatched('toast', tipo: 'warning')
            ->assertNotDispatched('abrir-modal-cliente-pos');

        // Y tampoco si el cliente sí aparece en la búsqueda.
        $cliente = Cliente::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->set('buscarCliente', $cliente->persona->carnet)
            ->assertSet('clienteSinResultados', false)
            ->call('abrirNuevoCliente')
            ->assertNotDispatched('abrir-modal-cliente-pos');
    }

    public function test_el_alta_rapida_rechaza_un_carnet_repetido(): void
    {
        $cliente = Cliente::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->set('nuevoCarnet', $cliente->persona->carnet)
            ->set('nuevoNombres', 'Rosa')
            ->set('nuevoApellidoPaterno', 'Quispe')
            ->call('guardarCliente')
            ->assertHasErrors('nuevoCarnet');
    }

    public function test_no_se_puede_cobrar_un_carrito_vacio(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Pos::class)
            ->assertSet('ventaValida', false)
            ->call('cobrar')
            ->assertHasErrors('carrito');

        $this->assertSame(0, Venta::count());
    }

    // ---- Historial (Livewire) ---------------------------------------------

    public function test_los_totales_no_cuentan_las_ventas_anuladas(): void
    {
        // Sumar una anulada inflaría todos los indicadores.
        Venta::factory()->create(['total' => 1000, 'ganancia' => 300]);
        Venta::factory()->anulada()->create(['total' => 5000, 'ganancia' => 2000]);

        Livewire::actingAs($this->admin())
            ->test(VentasIndex::class)
            ->assertViewHas('totalVentas', 1)
            ->assertViewHas('ingresoTotal', 1000.0)
            ->assertViewHas('gananciaTotal', 300.0)
            ->assertViewHas('anuladas', 1);
    }

    public function test_anular_desde_el_historial_exige_motivo(): void
    {
        $unidad = $this->unidadEnStock();

        $venta = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        Livewire::actingAs($this->admin())
            ->test(VentasIndex::class)
            ->call('confirmarAnular', $venta->id)
            ->set('motivoAnulacion', '')
            ->call('anular')
            ->assertHasErrors('motivoAnulacion');

        $this->assertSame('completada', $venta->fresh()->estado);
        $this->assertSame('vendido', $unidad->fresh()->estado);
    }

    public function test_busca_una_venta_por_el_serial_del_aparato(): void
    {
        // En la tienda se pregunta por el serial mucho más que por el número
        // de venta.
        $unidad = $this->unidadEnStock();
        $unidad->update(['serial' => 'SER-VENDIDO']);

        app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        Livewire::actingAs($this->admin())
            ->test(VentasIndex::class)
            ->set('buscar', 'SER-VENDIDO')
            ->assertViewHas('ventas', fn ($v) => $v->total() === 1);
    }

    // ---- Permisos ----------------------------------------------------------

    public function test_el_pos_exige_el_permiso_de_crear_ventas(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)->get('/ventas/nueva')->assertForbidden();
        $this->actingAs($this->admin())->get('/ventas/nueva')->assertOk();
    }

    public function test_un_vendedor_no_puede_anular_ventas(): void
    {
        // Anular mueve dinero y stock: es cosa de supervisión.
        $unidad = $this->unidadEnStock();
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        $venta = app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        Livewire::actingAs($vendedor)
            ->test(VentasIndex::class)
            ->call('confirmarAnular', $venta->id)
            ->assertForbidden();

        $this->assertSame('completada', $venta->fresh()->estado);
    }
}
