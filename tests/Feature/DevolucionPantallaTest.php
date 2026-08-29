<?php

namespace Tests\Feature;

use App\Livewire\Ventas\Show;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La devolución desde la ficha de la venta.
 *
 * El componente Livewire es un endpoint invocable: cualquiera puede llamar a
 * sus métodos desde el navegador, así que el permiso se comprueba en cada uno
 * y no solo escondiendo el botón.
 */
class DevolucionPantallaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function venta(int $aparatos = 2): Venta
    {
        $producto = Producto::factory()->create(['precio_venta' => 1000]);

        $unidades = collect(range(1, $aparatos))->map(fn (): Unidad => Unidad::factory()->create([
            'producto_id' => $producto->id,
            'estado' => 'en_stock',
            'costo_unitario' => 600,
            'precio_venta' => 1000,
        ]));

        return app(RegistroDeVenta::class)->registrar(
            lineas: $unidades->map(fn (Unidad $u): array => [
                'unidad_id' => $u->id,
                'precio_unitario' => 1000.0,
                'descuento' => 0,
            ])->all(),
            cabecera: ['metodo_pago' => 'efectivo'],
            userId: User::factory()->create(['is_active' => true])->syncRoles('vendedor')->id,
        );
    }

    private function supervisor(): User
    {
        // `supervisor` tiene `ventas.anular`, que es el permiso con el que va
        // la devolución.
        return User::factory()->create(['is_active' => true])->syncRoles('supervisor');
    }

    // ---- Camino feliz ------------------------------------------------------

    public function test_devuelve_un_aparato_y_actualiza_la_venta_en_pantalla(): void
    {
        $venta = $this->venta(2);
        $detalle = $venta->detalles()->orderBy('id')->first();

        Livewire::actingAs($this->supervisor())
            ->test(Show::class, ['venta' => $venta])
            ->call('confirmarDevolucion', $detalle->id)
            ->set('motivoDevolucion', 'Vino con la puerta rota')
            ->call('devolver')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-devolucion');

        $venta->refresh();

        $this->assertSame('1000.00', $venta->total);
        $this->assertSame('1000.00', $venta->total_devuelto);
        $this->assertNotNull($detalle->fresh()->devuelto_en);
        $this->assertSame('en_stock', $detalle->fresh()->unidad->estado);
    }

    public function test_el_motivo_es_obligatorio(): void
    {
        $venta = $this->venta(2);
        $detalle = $venta->detalles()->orderBy('id')->first();

        Livewire::actingAs($this->supervisor())
            ->test(Show::class, ['venta' => $venta])
            ->call('confirmarDevolucion', $detalle->id)
            ->set('motivoDevolucion', '')
            ->call('devolver')
            ->assertHasErrors(['motivoDevolucion' => 'required']);

        // Y no se tocó nada.
        $this->assertNull($detalle->fresh()->devuelto_en);
        $this->assertSame('2000.00', $venta->fresh()->total);
    }

    // ---- Permisos ----------------------------------------------------------

    public function test_sin_permiso_de_anular_no_se_puede_devolver(): void
    {
        $venta = $this->venta(2);
        $detalle = $venta->detalles()->orderBy('id')->first();

        // El vendedor puede ver la venta pero no deshacerla.
        $vendedor = User::factory()->create(['is_active' => true])->syncRoles('vendedor');

        Livewire::actingAs($vendedor)
            ->test(Show::class, ['venta' => $venta])
            ->call('confirmarDevolucion', $detalle->id)
            ->assertForbidden();
    }

    public function test_el_permiso_se_comprueba_tambien_al_devolver(): void
    {
        $venta = $this->venta(2);
        $detalle = $venta->detalles()->orderBy('id')->first();
        $vendedor = User::factory()->create(['is_active' => true])->syncRoles('vendedor');

        // Saltándose el paso de confirmar: el componente es un endpoint
        // invocable y esconder el botón no protege nada.
        Livewire::actingAs($vendedor)
            ->test(Show::class, ['venta' => $venta])
            ->set('detalleADevolver', $detalle->id)
            ->set('motivoDevolucion', 'Da igual el motivo')
            ->call('devolver')
            ->assertForbidden();

        $this->assertNull($detalle->fresh()->devuelto_en);
    }

    // ---- Estados en los que no se ofrece -----------------------------------

    public function test_en_una_venta_anulada_no_se_ofrece_devolver(): void
    {
        $venta = $this->venta(2);
        app(RegistroDeVenta::class)->anular($venta, 'Error de cobro');

        Livewire::actingAs($this->supervisor())
            ->test(Show::class, ['venta' => $venta->fresh()])
            ->assertViewHas('puedeDevolver', false);
    }

    public function test_un_aparato_ya_devuelto_no_se_puede_volver_a_elegir(): void
    {
        $venta = $this->venta(2);
        $detalle = $venta->detalles()->orderBy('id')->first();

        app(RegistroDeVenta::class)->devolver(
            $detalle->load(['venta', 'unidad']),
            'Vino fallado'
        );

        Livewire::actingAs($this->supervisor())
            ->test(Show::class, ['venta' => $venta->fresh()])
            ->call('confirmarDevolucion', $detalle->id)
            ->assertSet('detalleADevolver', null);
    }
}
