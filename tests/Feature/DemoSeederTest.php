<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Unidad;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Support\ProrrateoDeGastos;
use Database\Seeders\CargoSeeder;
use Database\Seeders\CategoriaSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\MarcaSeeder;
use Database\Seeders\ProductoSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * La demo no es decorado: es la base con la que se enseña el sistema y con la
 * que se revisan los reportes antes de entregarlo. Si sus números no cuadran,
 * la primera impresión del cliente es la de un sistema que se equivoca.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            CargoSeeder::class,
            CategoriaSeeder::class,
            MarcaSeeder::class,
            ProductoSeeder::class,
        ]);
    }

    /** Corre la demo con dos meses en vez de doce: la prueba es la misma y tarda una décima parte. */
    private function correrDemo(int $meses = 2): void
    {
        $seeder = app(DemoSeeder::class);
        $seeder->meses = $meses;
        $seeder->setContainer(app())->run();
    }

    public function test_genera_compras_unidades_y_ventas(): void
    {
        $this->correrDemo();

        $this->assertGreaterThan(0, Compra::count());
        $this->assertGreaterThan(0, Unidad::count());
        $this->assertGreaterThan(0, Venta::where('estado', 'completada')->count());
    }

    /**
     * Toda unidad tiene su entrada en el kardex, igual que en la operación
     * real: una demo con inventario sin origen enseñaría justo lo contrario de
     * lo que garantiza el sistema.
     */
    public function test_ninguna_unidad_queda_sin_rastro_en_el_kardex(): void
    {
        $this->correrDemo();

        $sinEntrada = Unidad::whereDoesntHave('movimientos', fn ($consulta) => $consulta->where('tipo', 'entrada'))->count();

        $this->assertSame(0, $sinEntrada);
        $this->assertGreaterThan(0, MovimientoInventario::count());
    }

    /**
     * El prorrateo del landed cost tiene que cuadrar también aquí: la suma de
     * lo que costó cada unidad es exactamente lo que se pagó por la compra.
     */
    public function test_el_costo_de_las_unidades_cuadra_con_lo_pagado(): void
    {
        $this->correrDemo();

        foreach (Compra::where('estado', 'recepcionada')->get() as $compra) {
            $pagado = ProrrateoDeGastos::aCentavos($compra->subtotal)
                + ProrrateoDeGastos::aCentavos($compra->gastos_prorrateables);

            $costoUnidades = Unidad::where('compra_id', $compra->id)
                ->get()
                ->sum(fn (Unidad $unidad) => ProrrateoDeGastos::aCentavos($unidad->costo_unitario));

            $this->assertSame($pagado, (int) $costoUnidades, "La compra {$compra->codigo} no cuadra con sus unidades.");
        }
    }

    /**
     * La ganancia de cada venta es la suma de la de sus líneas. Si no lo
     * fuera, los reportes y el dashboard mostrarían dos cifras distintas para
     * lo mismo.
     */
    public function test_la_ganancia_de_cada_venta_cuadra_con_sus_lineas(): void
    {
        $this->correrDemo();

        foreach (Venta::with('detalles')->get() as $venta) {
            $lineas = $venta->detalles->sum(fn (VentaDetalle $linea) => ProrrateoDeGastos::aCentavos($linea->ganancia));

            $this->assertSame(
                ProrrateoDeGastos::aCentavos($venta->ganancia),
                (int) $lineas,
                "La venta {$venta->codigo} no cuadra con sus líneas."
            );
        }
    }

    /**
     * Las ventas se registran en el pasado, no todas hoy: con todo apilado en
     * la fecha de hoy la gráfica de evolución sería una sola barra y no habría
     * nada que enseñar.
     */
    public function test_las_ventas_estan_repartidas_en_el_tiempo(): void
    {
        $this->correrDemo();

        $dias = Venta::selectRaw('DATE(vendida_en) as dia')->distinct()->count();

        $this->assertGreaterThan(5, $dias);
        $this->assertTrue(Venta::where('vendida_en', '<', now()->startOfMonth())->exists());
        $this->assertFalse(Venta::where('vendida_en', '>', now())->exists(), 'Hay ventas con fecha futura.');
    }

    /**
     * Ninguna unidad se vende antes de haber entrado al almacén: el kardex
     * contaría al revés.
     */
    public function test_nada_se_vende_antes_de_haberse_comprado(): void
    {
        $this->correrDemo();

        $this->assertFalse(
            Unidad::whereColumn('vendido_en', '<', 'ingresado_en')->exists(),
            'Hay unidades vendidas antes de ingresar al almacén.'
        );
    }

    /**
     * Las anuladas devuelven su mercadería: sus unidades no pueden seguir
     * marcadas como vendidas ni conservar la guardia de la doble venta.
     */
    public function test_las_ventas_anuladas_devolvieron_su_mercaderia(): void
    {
        $this->correrDemo(4);

        $anuladas = Venta::where('estado', 'anulada')->with('detalles.unidad')->get();

        $this->assertGreaterThan(0, $anuladas->count(), 'La demo no generó ninguna venta anulada.');

        foreach ($anuladas as $venta) {
            foreach ($venta->detalles as $linea) {
                // Se soltó la guardia de la doble venta: el aparato vuelve a
                // poder venderse. La línea sigue ahí, porque el histórico no
                // se borra. (Su estado actual puede ser 'vendido' otra vez si
                // la demo lo revendió más adelante, que es justo lo que esta
                // columna tiene que permitir.)
                $this->assertNull($linea->unidad_vendida_id);

                $this->assertTrue(
                    $linea->unidad->movimientos()->where('tipo', 'devolucion')->exists(),
                    "La unidad {$linea->unidad->codigo_interno} no tiene la devolución en el kardex."
                );
            }
        }
    }

    /** Correrla dos veces duplicaría la historia; se para antes de tocar nada. */
    public function test_se_niega_a_correr_sobre_una_base_con_movimiento(): void
    {
        $this->correrDemo(1);

        $compras = Compra::count();

        $this->expectException(RuntimeException::class);

        try {
            $this->correrDemo(1);
        } finally {
            $this->assertSame($compras, Compra::count());
        }
    }
}
