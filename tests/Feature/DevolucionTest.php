<?php

namespace Tests\Feature;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\Reportes;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\TestCase;

/**
 * Devolución de un aparato suelto, sin anular la venta entera.
 *
 * Lo delicado no es marcar la línea: es que **los reportes sigan cuadrando**.
 * `total` pasa a ser el neto para que las consultas de siempre sumen bien sin
 * tocarlas, y lo cobrado en su día se reconstruye con `total_devuelto`.
 */
class DevolucionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function cajero(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    /**
     * Vende [$cuantos] aparatos de 1000 que costaron 600 cada uno.
     *
     * @return array{0: Venta, 1: Collection<int, Unidad>}
     */
    private function venderVarios(int $cuantos): array
    {
        $producto = Producto::factory()->create(['precio_venta' => 1000]);

        $unidades = collect(range(1, $cuantos))->map(fn (): Unidad => Unidad::factory()->create([
            'producto_id' => $producto->id,
            'estado' => 'en_stock',
            'costo_unitario' => 600,
            'precio_venta' => 1000,
        ]));

        $venta = app(RegistroDeVenta::class)->registrar(
            lineas: $unidades->map(fn (Unidad $u): array => [
                'unidad_id' => $u->id,
                'precio_unitario' => 1000.0,
                'descuento' => 0,
            ])->all(),
            cabecera: ['metodo_pago' => 'efectivo'],
            userId: $this->cajero()->id,
        );

        return [$venta, $unidades];
    }

    private function devolver(Venta $venta, int $indice, string $motivo = 'Vino fallado'): void
    {
        $detalle = $venta->detalles()->orderBy('id')->get()[$indice];

        app(RegistroDeVenta::class)->devolver($detalle, $motivo);
    }

    // ---- El aparato vuelve al stock ----------------------------------------

    public function test_el_aparato_devuelto_vuelve_al_stock(): void
    {
        [$venta, $unidades] = $this->venderVarios(3);

        $this->devolver($venta, 0);

        $this->assertSame('en_stock', $unidades[0]->fresh()->estado);
        $this->assertNull($unidades[0]->fresh()->vendido_en);

        // Los otros dos siguen vendidos: se devolvió uno, no la venta.
        $this->assertSame('vendido', $unidades[1]->fresh()->estado);
        $this->assertSame('vendido', $unidades[2]->fresh()->estado);
    }

    public function test_el_kardex_cuenta_la_devolucion_y_el_retorno(): void
    {
        [$venta, $unidades] = $this->venderVarios(2);

        $this->devolver($venta, 0);

        $movimientos = MovimientoInventario::where('unidad_id', $unidades[0]->id)
            ->orderBy('id')
            ->pluck('tipo')
            ->all();

        // Tres movimientos: la venta y los DOS de la devolución. El kardex
        // cuenta lo que pasó —salió de vendido y volvió al stock—, no solo
        // dónde acabó.
        //
        // No hay 'entrada' porque la unidad se creó con factory; en la vida
        // real la pone la recepción de la compra.
        $this->assertSame(['salida', 'devolucion', 'ajuste'], $movimientos);
    }

    public function test_el_aparato_devuelto_se_puede_volver_a_vender(): void
    {
        [$venta, $unidades] = $this->venderVarios(2);

        $this->devolver($venta, 0);

        // La guardia de la doble venta se suelta solo en esa línea.
        $segunda = app(RegistroDeVenta::class)->registrar(
            lineas: [['unidad_id' => $unidades[0]->id, 'precio_unitario' => 900.0, 'descuento' => 0]],
            cabecera: ['metodo_pago' => 'efectivo'],
            userId: $this->cajero()->id,
        );

        $this->assertTrue($segunda->esta_completada);
        $this->assertSame('vendido', $unidades[0]->fresh()->estado);
    }

    // ---- Los importes ------------------------------------------------------

    public function test_la_venta_se_queda_con_el_importe_de_lo_que_sigue_vendido(): void
    {
        [$venta] = $this->venderVarios(3);

        $this->assertSame('3000.00', $venta->total);

        $this->devolver($venta, 0);
        $venta->refresh();

        $this->assertSame('2000.00', $venta->total, 'El total no bajó al devolver.');
        $this->assertSame('1000.00', $venta->total_devuelto);
        // Costo y ganancia acompañan: 2 aparatos a 600 de costo.
        $this->assertSame('1200.00', $venta->costo_total);
        $this->assertSame('800.00', $venta->ganancia);
    }

    public function test_lo_cobrado_en_su_dia_no_se_pierde(): void
    {
        [$venta] = $this->venderVarios(3);

        $this->devolver($venta, 0);
        $venta->refresh();

        // `total` guarda el neto para que los reportes sumen sin tocar ninguna
        // consulta; el importe original se reconstruye.
        $this->assertSame('3000.00', $venta->total_original);
    }

    public function test_los_reportes_cuadran_despues_de_una_devolucion(): void
    {
        [$venta] = $this->venderVarios(3);

        $this->devolver($venta, 0);

        $resumen = app(Reportes::class)->resumen(now()->startOfDay(), now()->endOfDay());

        // Es la razón de ser del diseño: ni una consulta de reportes se tocó.
        $this->assertSame(2000.0, $resumen['ingreso']);
        $this->assertSame(800.0, $resumen['ganancia']);
        $this->assertSame(1, $resumen['ventas']);
    }

    // ---- Devolver todo ------------------------------------------------------

    public function test_devolver_todos_los_aparatos_anula_la_venta(): void
    {
        [$venta] = $this->venderVarios(2);

        $this->devolver($venta, 0);
        $this->devolver($venta, 1, 'El cliente se arrepintió');

        $venta->refresh();

        // Una venta sin ningún aparato no es una venta: seguiría contando como
        // viva, de importe cero, en todos los listados.
        $this->assertTrue($venta->esta_anulada);
        $this->assertSame('0.00', $venta->total);
        $this->assertStringContainsString('todos los aparatos', $venta->motivo_anulacion);
    }

    public function test_una_venta_con_todo_devuelto_sale_de_los_reportes(): void
    {
        [$venta] = $this->venderVarios(2);

        $this->devolver($venta, 0);
        $this->devolver($venta, 1);

        $resumen = app(Reportes::class)->resumen(now()->startOfDay(), now()->endOfDay());

        $this->assertSame(0, $resumen['ventas']);
        $this->assertSame(0.0, $resumen['ingreso']);
    }

    // ---- Lo que no deja hacer -----------------------------------------------

    public function test_no_se_puede_devolver_dos_veces_el_mismo_aparato(): void
    {
        [$venta] = $this->venderVarios(2);

        $this->devolver($venta, 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ya se había devuelto');

        $this->devolver($venta, 0);
    }

    public function test_no_se_puede_devolver_de_una_venta_anulada(): void
    {
        [$venta] = $this->venderVarios(2);

        app(RegistroDeVenta::class)->anular($venta, 'Error de cobro');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('está anulada');

        $this->devolver($venta->refresh(), 0);
    }

    public function test_hace_falta_decir_por_que_se_devuelve(): void
    {
        [$venta] = $this->venderVarios(2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('por qué');

        // Sin motivo, dentro de un mes nadie sabrá si volvió fallado o si el
        // cliente cambió de idea, que son dos cosas muy distintas.
        $this->devolver($venta, 0, '   ');
    }

    public function test_una_devolucion_fallida_no_deja_nada_a_medias(): void
    {
        [$venta, $unidades] = $this->venderVarios(2);

        try {
            $this->devolver($venta, 0, '');
        } catch (RuntimeException) {
            // Esperado.
        }

        // Ni el aparato se movió ni los importes cambiaron.
        $this->assertSame('vendido', $unidades[0]->fresh()->estado);
        $this->assertSame('2000.00', $venta->fresh()->total);
        $this->assertSame('0.00', $venta->fresh()->total_devuelto);
    }
}
