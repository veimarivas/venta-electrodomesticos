<?php

namespace Tests\Feature;

use App\Livewire\Entregas\Index as TableroEntregas;
use App\Livewire\Ventas\Show as FichaVenta;
use App\Models\Cliente;
use App\Models\Entrega;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\ProgramacionDeEntregas;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Entrega a domicilio.
 *
 * Lo delicado no es la máquina de estados sino lo que la rodea: un aparato no
 * puede estar en dos entregas vivas, devolverlo tiene que sacarlo del camión, y
 * anular la venta tiene que caerse el reparto entero.
 */
class EntregaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function supervisor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('supervisor');
    }

    private function servicio(): ProgramacionDeEntregas
    {
        return app(ProgramacionDeEntregas::class);
    }

    /** Una venta con [$aparatos] líneas, a nombre de un cliente. */
    private function venta(int $aparatos = 2): Venta
    {
        $lineas = collect(range(1, $aparatos))->map(function (): array {
            $unidad = Unidad::factory()->create([
                'producto_id' => Producto::factory()->create([
                    'precio_venta' => 1000,
                    'stock_minimo' => 0,
                    'descuento_maximo' => 0,
                ])->id,
                'estado' => 'en_stock',
                'costo_unitario' => 500,
                'precio_venta' => 1000,
            ]);

            return ['unidad_id' => $unidad->id, 'precio_unitario' => 1000, 'descuento' => 0];
        })->all();

        return app(RegistroDeVenta::class)->registrar(
            lineas: $lineas,
            cabecera: [
                'cliente_id' => Cliente::factory()->create()->id,
                'metodo_pago' => 'efectivo',
            ],
            userId: $this->supervisor()->id,
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function programar(Venta $venta, ?array $lineas = null, array $datos = []): Entrega
    {
        return $this->servicio()->programar(
            $venta,
            $lineas ?? $venta->detalles->pluck('id')->all(),
            array_merge(['direccion' => 'Av. Siempre Viva 742'], $datos),
            $this->supervisor()->id,
        );
    }

    // ---- Programar ----------------------------------------------------------

    public function test_se_programa_la_entrega_de_una_venta(): void
    {
        $venta = $this->venta();

        $entrega = $this->programar($venta, datos: [
            'referencia' => 'Portón verde',
            'programada_para' => today()->addDays(2)->format('Y-m-d'),
            'con_instalacion' => true,
        ]);

        $this->assertSame('pendiente', $entrega->estado);
        $this->assertSame('Av. Siempre Viva 742', $entrega->direccion);
        $this->assertTrue($entrega->con_instalacion);
        $this->assertCount(2, $entrega->detalles);
        $this->assertSame($venta->cliente_id, $entrega->cliente_id);
    }

    public function test_se_puede_partir_una_venta_en_dos_entregas(): void
    {
        // Tres aparatos que no caben en un viaje.
        $venta = $this->venta(3);
        $lineas = $venta->detalles->pluck('id')->all();

        $primera = $this->programar($venta, [$lineas[0], $lineas[1]]);
        $segunda = $this->programar($venta, [$lineas[2]]);

        $this->assertCount(2, $primera->detalles);
        $this->assertCount(1, $segunda->detalles);
        $this->assertSame(2, $venta->entregas()->count());
    }

    public function test_un_aparato_no_puede_estar_en_dos_entregas_vivas(): void
    {
        $venta = $this->venta(1);
        $this->programar($venta);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya está en otra entrega');

        $this->programar($venta);
    }

    public function test_cancelar_una_entrega_libera_sus_aparatos(): void
    {
        $venta = $this->venta(1);
        $entrega = $this->programar($venta);

        $this->servicio()->cancelar($entrega, 'El cliente pasa a recogerlo');

        // Con la guardia soltada, el mismo aparato se puede volver a programar.
        $segunda = $this->programar($venta);

        $this->assertSame('cancelada', $entrega->refresh()->estado);
        $this->assertSame('pendiente', $segunda->estado);
    }

    public function test_no_se_programa_la_entrega_de_una_venta_anulada(): void
    {
        $venta = $this->venta(1);
        app(RegistroDeVenta::class)->anular($venta, 'Prueba');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('está anulada');

        $this->programar($venta->refresh());
    }

    public function test_hace_falta_una_direccion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dirección');

        $this->programar($this->venta(1), datos: ['direccion' => '   ']);
    }

    public function test_no_se_pueden_colar_lineas_de_otra_venta(): void
    {
        $venta = $this->venta(1);
        $ajena = $this->venta(1);

        // El componente es un endpoint invocable: los ids llegan del navegador.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya no pertenece a esta venta');

        $this->programar($venta, [$ajena->detalles->first()->id]);
    }

    // ---- La ruta ------------------------------------------------------------

    public function test_el_recorrido_completo_hasta_entregada(): void
    {
        $venta = $this->venta(1);
        $repartidor = $this->supervisor();
        $entrega = $this->programar($venta, datos: ['con_instalacion' => true]);

        $entrega = $this->servicio()->despachar($entrega, $repartidor->id, $repartidor->id);

        $this->assertSame('en_ruta', $entrega->estado);
        $this->assertNotNull($entrega->salio_en);
        $this->assertSame($repartidor->id, $entrega->repartidor_id);

        $entrega = $this->servicio()->confirmar($entrega, 'Doña Rosa, la vecina', instalada: true);

        $this->assertSame('entregada', $entrega->estado);
        $this->assertSame('Doña Rosa, la vecina', $entrega->recibida_por);
        $this->assertNotNull($entrega->entregada_en);
        $this->assertNotNull($entrega->instalada_en);
    }

    public function test_no_se_despacha_sin_saber_quien_la_lleva(): void
    {
        $entrega = $this->programar($this->venta(1));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('quién lleva');

        $this->servicio()->despachar($entrega, null, $this->supervisor()->id);
    }

    public function test_confirmar_exige_el_nombre_de_quien_recibio(): void
    {
        $entrega = $this->programar($this->venta(1));

        // «Entregada» sin nombre no sirve de nada el día que el cliente dice
        // que nunca le llegó.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('quién recibió');

        $this->servicio()->confirmar($entrega, '  ');
    }

    public function test_una_entrega_fallida_se_reprograma(): void
    {
        $repartidor = $this->supervisor();
        $entrega = $this->programar($this->venta(1), datos: [
            'programada_para' => today()->format('Y-m-d'),
        ]);

        $entrega = $this->servicio()->despachar($entrega, $repartidor->id, $repartidor->id);
        $entrega = $this->servicio()->fallar($entrega, 'No había nadie en la casa');

        $this->assertSame('fallida', $entrega->estado);
        $this->assertSame('No había nadie en la casa', $entrega->motivo_fallo);
        // Vuelve a estar en la tienda: la salida anterior ya no cuenta.
        $this->assertNull($entrega->salio_en);

        $entrega = $this->servicio()->reprogramar($entrega, today()->addDays(3)->format('Y-m-d'));

        $this->assertSame('pendiente', $entrega->estado);
        $this->assertSame(today()->addDays(3)->toDateString(), $entrega->programada_para->toDateString());
    }

    public function test_una_entrega_hecha_ya_no_se_toca(): void
    {
        $entrega = $this->programar($this->venta(1));
        $entrega = $this->servicio()->confirmar($entrega, 'El cliente');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya se hizo');

        $this->servicio()->cancelar($entrega);
    }

    public function test_la_instalacion_no_se_marca_si_no_se_pacto(): void
    {
        // Dar por instalado algo que nadie instaló cierra un trabajo pendiente
        // sin hacerlo.
        $entrega = $this->programar($this->venta(1), datos: ['con_instalacion' => false]);

        $entrega = $this->servicio()->confirmar($entrega, 'El cliente', instalada: true);

        $this->assertNull($entrega->instalada_en);
    }

    // ---- Con la venta -------------------------------------------------------

    public function test_devolver_un_aparato_lo_saca_de_la_entrega(): void
    {
        $venta = $this->venta(2);
        $entrega = $this->programar($venta);

        app(RegistroDeVenta::class)->devolver(
            $venta->detalles()->with(['venta', 'unidad'])->first(),
            'Salió defectuoso'
        );

        $vivas = $entrega->detalles()->whereNotNull('venta_detalle_activo_id')->count();

        $this->assertSame(1, $vivas);
        // Queda otro aparato que llevar, así que la entrega sigue en pie.
        $this->assertSame('pendiente', $entrega->refresh()->estado);
    }

    public function test_si_se_devuelven_todos_la_entrega_se_cancela(): void
    {
        $venta = $this->venta(1);
        $entrega = $this->programar($venta);

        app(RegistroDeVenta::class)->devolver(
            $venta->detalles()->with(['venta', 'unidad'])->first(),
            'Salió defectuoso'
        );

        // Un camión saliendo con la caja vacía es peor que no salir.
        $this->assertSame('cancelada', $entrega->refresh()->estado);
    }

    public function test_anular_la_venta_cancela_sus_entregas_pendientes(): void
    {
        $venta = $this->venta(2);
        $entrega = $this->programar($venta);

        app(RegistroDeVenta::class)->anular($venta, 'El cliente se arrepintió');

        $this->assertSame('cancelada', $entrega->refresh()->estado);
    }

    public function test_una_entrega_ya_hecha_sobrevive_a_la_devolucion(): void
    {
        $venta = $this->venta(1);
        $entrega = $this->programar($venta);
        $this->servicio()->confirmar($entrega, 'El cliente');

        app(RegistroDeVenta::class)->devolver(
            $venta->detalles()->with(['venta', 'unidad'])->first(),
            'Salió defectuoso'
        );

        // El aparato se entregó y después volvió: las dos cosas pasaron.
        $this->assertSame('entregada', $entrega->refresh()->estado);
    }

    // ---- Las pantallas ------------------------------------------------------

    public function test_desde_la_ficha_de_la_venta_se_programa(): void
    {
        $venta = $this->venta(1);

        Livewire::actingAs($this->supervisor())
            ->test(FichaVenta::class, ['venta' => $venta])
            ->call('abrirEntrega')
            ->set('direccion', 'Calle Falsa 123')
            ->set('programadaPara', today()->addDay()->format('Y-m-d'))
            ->call('programarEntrega')
            ->assertDispatched('cerrar-modal-programar-entrega');

        $this->assertSame(1, $venta->entregas()->count());
        $this->assertSame('Calle Falsa 123', $venta->entregas()->first()->direccion);
    }

    public function test_la_ficha_no_ofrece_aparatos_ya_programados(): void
    {
        $venta = $this->venta(2);
        $lineas = $venta->detalles->pluck('id')->all();

        $this->programar($venta, [$lineas[0]]);

        Livewire::actingAs($this->supervisor())
            ->test(FichaVenta::class, ['venta' => $venta->refresh()])
            ->call('abrirEntrega')
            // Solo queda uno por llevar; ofrecer el otro invita a un error que
            // después hay que explicar.
            ->assertSet('lineasAEntregar', [$lineas[1]]);
    }

    public function test_el_tablero_se_abre_y_lista(): void
    {
        $venta = $this->venta(1);
        $this->programar($venta, datos: ['referencia' => 'Portón verde']);

        $this->actingAs($this->supervisor())
            ->get('/entregas')
            ->assertOk()
            ->assertSee('Av. Siempre Viva 742')
            ->assertSee('Portón verde')
            ->assertSee($venta->codigo);
    }

    public function test_desde_el_tablero_se_confirma_la_entrega(): void
    {
        $entrega = $this->programar($this->venta(1));

        Livewire::actingAs($this->supervisor())
            ->test(TableroEntregas::class)
            ->call('abrirConfirmacion', $entrega->id)
            // En blanco a propósito: proponer el nombre del cliente convertiría
            // la constancia en un «aceptar».
            ->assertSet('recibidaPor', '')
            ->set('recibidaPor', 'El portero')
            ->call('confirmar')
            ->assertDispatched('cerrar-modal-confirmar-entrega');

        $this->assertSame('entregada', $entrega->refresh()->estado);
    }

    public function test_sin_permiso_no_se_ve_el_tablero(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]))
            ->get('/entregas')
            ->assertForbidden();
    }

    public function test_sin_permiso_de_gestionar_no_se_mueve_una_entrega(): void
    {
        $entrega = $this->programar($this->venta(1));

        $miron = User::factory()->create(['is_active' => true]);
        $miron->givePermissionTo('entregas.ver');

        // El componente es un endpoint invocable: esconder los botones no basta.
        Livewire::actingAs($miron)
            ->test(TableroEntregas::class)
            ->set('entregaId', $entrega->id)
            ->set('recibidaPor', 'Quien sea')
            ->call('confirmar')
            ->assertForbidden();

        $this->assertSame('pendiente', $entrega->refresh()->estado);
    }
}
