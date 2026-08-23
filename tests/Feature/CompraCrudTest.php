<?php

namespace Tests\Feature;

use App\Livewire\Compras\Index;
use App\Models\Categoria;
use App\Models\Unidad;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompraCrudTest extends TestCase
{
    use RefreshDatabase;

    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->proveedor = Proveedor::factory()->create(['activo' => true]);
    }

    private function admin(): User
    {
        return User::factory()->create()->syncRoles('admin');
    }

    /**
     * @return array<string, string>
     */
    private function cabeceraValida(array $sobrescribir = []): array
    {
        return array_merge([
            'proveedor_id' => (string) $this->proveedor->id,
            'numero_factura' => 'F-00123',
            'fecha_compra' => now()->toDateString(),
            'total_pagado' => '1000',
            'notas' => '',
        ], $sobrescribir);
    }

    /**
     * Compra completa lista para registrar: cabecera + un producto cuyo
     * importe cuadra exactamente con el total pagado.
     *
     * @return array{0: \Livewire\Features\SupportTesting\Testable, 1: Producto}
     */
    private function compraLista(string $totalPagado = '1000', int $cantidad = 4): array
    {
        $producto = Producto::factory()->create(['activo' => true]);

        $componente = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->cabeceraValida(['total_pagado' => $totalPagado]))
            ->call('agregarLinea', $producto->id)
            ->set('lineas.0.cantidad', (string) $cantidad)
            ->set('lineas.0.costo_total', $totalPagado);

        return [$componente, $producto];
    }

    // ---- Cabecera ---------------------------------------------------------

    public function test_registra_la_compra_y_genera_sus_unidades_de_una_vez(): void
    {
        [$componente, $producto] = $this->compraLista('1000', 4);

        $componente
            ->assertSet('compraValida', true)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success');

        $compra = Compra::first();

        $this->assertSame('COM-'.now()->format('Y').'-0001', $compra->codigo);
        // No hay paso intermedio: nace recepcionada, con su inventario.
        $this->assertSame('recepcionada', $compra->estado);
        $this->assertSame('1000.00', $compra->total);
        $this->assertSame(4, Unidad::where('compra_id', $compra->id)->count());
        $this->assertSame(
            4,
            Unidad::where('compra_id', $compra->id)->where('producto_id', $producto->id)->count()
        );
    }

    public function test_al_registrar_se_abre_el_detalle_para_los_seriales(): void
    {
        [$componente] = $this->compraLista();

        $componente
            ->call('guardar')
            ->assertSet('detalleCompraId', fn ($id) => $id === Compra::first()->id);
    }

    public function test_los_codigos_de_compra_son_correlativos(): void
    {
        [$primera] = $this->compraLista();
        $primera->call('guardar');

        [$segunda] = $this->compraLista();
        $segunda->call('guardar');

        $this->assertSame(
            ['COM-'.now()->format('Y').'-0001', 'COM-'.now()->format('Y').'-0002'],
            Compra::orderBy('id')->pluck('codigo')->all()
        );
    }

    public function test_el_proveedor_y_la_fecha_son_obligatorios(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->cabeceraValida(['proveedor_id' => '', 'fecha_compra' => '']))
            ->call('guardar')
            ->assertHasErrors(['proveedor_id' => 'required', 'fecha_compra' => 'required']);
    }

    public function test_la_fecha_de_compra_no_puede_ser_futura(): void
    {
        // La fecha se toca al final a propósito: assertHasErrors comprobando
        // el nombre de la regla solo funciona sobre la última validación
        // ejecutada. El mensaje sí sobrevive a peticiones posteriores, pero
        // la metadata de qué regla falló, no.
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->cabeceraValida())
            ->set('fecha_compra', now()->addWeek()->toDateString())
            ->assertHasErrors(['fecha_compra' => 'before_or_equal'])
            ->assertSet('compraValida', false);
    }

    public function test_una_compra_sin_productos_no_se_registra(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->cabeceraValida())
            ->assertSet('compraValida', false)
            ->call('guardar')
            ->assertHasErrors('lineas');

        $this->assertSame(0, Compra::count());
    }

    // ---- Cuadre con el total pagado ---------------------------------------

    public function test_no_se_registra_si_el_detalle_no_suma_el_total_pagado(): void
    {
        $producto = Producto::factory()->create(['activo' => true]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->cabeceraValida(['total_pagado' => '1000']))
            ->call('agregarLinea', $producto->id)
            ->set('lineas.0.cantidad', '2')
            // Falta asignar 200: dejaría un costo que no carga nadie.
            ->set('lineas.0.costo_total', '800')
            ->assertSet('cuadra', false)
            ->assertSet('compraValida', false)
            ->call('guardar')
            ->assertHasErrors('total_pagado');

        $this->assertSame(0, Compra::count());
    }

    public function test_no_se_puede_asignar_mas_que_el_total_pagado(): void
    {
        $producto = Producto::factory()->create(['activo' => true]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->cabeceraValida(['total_pagado' => '1000']))
            ->call('agregarLinea', $producto->id)
            ->set('lineas.0.cantidad', '2')
            ->set('lineas.0.costo_total', '1200')
            ->assertSet('saldoEnCentavos', -20000)
            ->assertSet('cuadra', false)
            ->call('guardar')
            ->assertHasErrors('total_pagado');

        $this->assertSame(0, Compra::count());
    }

    public function test_el_saldo_pendiente_se_calcula_en_vivo(): void
    {
        $tv = Producto::factory()->create(['activo' => true]);
        $lavadora = Producto::factory()->create(['activo' => true]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->cabeceraValida(['total_pagado' => '50000']))
            ->call('agregarLinea', $tv->id)
            ->set('lineas.0.cantidad', '10')
            ->set('lineas.0.costo_total', '35000')
            // Con una sola línea todavía faltan 15.000.
            ->assertSet('saldoEnCentavos', 1500000)
            ->assertSet('cuadra', false)
            ->call('agregarLinea', $lavadora->id)
            ->set('lineas.1.cantidad', '10')
            ->set('lineas.1.costo_total', '15000')
            ->assertSet('saldoEnCentavos', 0)
            ->assertSet('cuadra', true);
    }

    public function test_registra_varios_productos_con_sus_costos_unitarios(): void
    {
        // El caso real: 10 TV y 10 lavadoras en una misma factura.
        $tv = Producto::factory()->create(['activo' => true, 'precio_venta' => 4500]);
        $lavadora = Producto::factory()->create(['activo' => true, 'precio_venta' => 2200]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->cabeceraValida(['total_pagado' => '50000']))
            ->call('agregarLinea', $tv->id)
            ->set('lineas.0.cantidad', '10')
            ->set('lineas.0.costo_total', '35000')
            ->call('agregarLinea', $lavadora->id)
            ->set('lineas.1.cantidad', '10')
            ->set('lineas.1.costo_total', '15000')
            ->call('guardar')
            ->assertHasNoErrors();

        $compra = Compra::first();

        $this->assertSame(20, Unidad::where('compra_id', $compra->id)->count());

        $lineaTv = $compra->detalles()->where('producto_id', $tv->id)->first();
        $lineaLav = $compra->detalles()->where('producto_id', $lavadora->id)->first();

        // Costo unitario = lo pagado por el producto ÷ cantidad.
        $this->assertSame('3500.00', $lineaTv->costo_unitario);
        $this->assertSame('1500.00', $lineaLav->costo_unitario);
        // Precio de venta = productos.precio_venta.
        $this->assertSame('4500.00', $lineaTv->precio_venta);
        $this->assertSame('2200.00', $lineaLav->precio_venta);

        // Cada unidad carga su costo y su precio de lista.
        $unidadTv = Unidad::where('compra_id', $compra->id)->where('producto_id', $tv->id)->first();
        $this->assertSame('3500.00', $unidadTv->costo_unitario);
        $this->assertSame('4500.00', $unidadTv->precio_venta);
        $this->assertSame('en_stock', $unidadTv->estado);
    }

    public function test_quitar_un_producto_lo_saca_del_cuadre(): void
    {
        $producto = Producto::factory()->create(['activo' => true]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->cabeceraValida(['total_pagado' => '1000']))
            ->call('agregarLinea', $producto->id)
            ->set('lineas.0.cantidad', '2')
            ->set('lineas.0.costo_total', '1000')
            ->assertSet('cuadra', true)
            ->call('quitarLinea', 0)
            ->assertCount('lineas', 0)
            ->assertSet('cuadra', false);
    }

    // ---- Eliminación ------------------------------------------------------

    /**
     * Compra en borrador. Las compras nuevas ya no pasan por este estado
     * (nacen recepcionadas), pero el listado sigue mostrando las heredadas.
     */
    private function compraBorrador(): Compra
    {
        return Compra::factory()->create([
            'proveedor_id' => $this->proveedor->id,
            'estado' => 'borrador',
        ]);
    }

    public function test_una_compra_recepcionada_no_se_puede_eliminar(): void
    {
        $compra = Compra::factory()->recepcionada()->create([
            'proveedor_id' => $this->proveedor->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $compra->id)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'error');

        $this->assertDatabaseHas('compras', ['id' => $compra->id, 'deleted_at' => null]);
    }

    public function test_elimina_un_borrador_con_sus_lineas(): void
    {
        $compra = $this->compraBorrador();
        CompraDetalle::factory()->create(['compra_id' => $compra->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $compra->id)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'success');

        $this->assertSoftDeleted('compras', ['id' => $compra->id]);
    }

    // ---- Rentabilidad -----------------------------------------------------

    public function test_la_rentabilidad_refleja_lo_vendido_y_lo_pendiente(): void
    {
        $producto = Producto::factory()->create(['activo' => true, 'precio_venta' => 1500]);

        $componente = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->cabeceraValida(['total_pagado' => '3300']))
            ->call('agregarLinea', $producto->id)
            ->set('lineas.0.cantidad', '3')
            ->set('lineas.0.costo_total', '3300')
            ->call('guardar')
            ->assertHasNoErrors();

        $compra = Compra::first();

        // Se vende una unidad de verdad: el ingreso realizado sale de
        // venta_detalles (lo realmente cobrado), no de unidades.precio_venta.
        $unidad = Unidad::where('compra_id', $compra->id)->first();

        app(\App\Support\RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        $r = $componente->call('abrirDetalle', $compra->id)->instance()->rentabilidad();

        $this->assertSame(3, $r['unidades']);
        $this->assertSame(1, $r['vendidas']);
        $this->assertSame(2, $r['en_stock']);
        // Cada unidad costó 3300 / 3 = 1100; se vendió en 1500.
        $this->assertSame('1500.00', $r['ingreso']);
        $this->assertSame('400.00', $r['ganancia']);
        // Las 2 que quedan darían 400 cada una.
        $this->assertSame('800.00', $r['potencial']);
    }

    public function test_la_rentabilidad_no_cuenta_las_ventas_anuladas(): void
    {
        // Una venta anulada no es ingreso: sumarla inflaría la rentabilidad de
        // la compra y el aparato ya volvió al stock.
        $producto = Producto::factory()->create(['activo' => true, 'precio_venta' => 1500]);

        $componente = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->cabeceraValida(['total_pagado' => '1100']))
            ->call('agregarLinea', $producto->id)
            ->set('lineas.0.cantidad', '1')
            ->set('lineas.0.costo_total', '1100')
            ->call('guardar')
            ->assertHasNoErrors();

        $compra = Compra::first();
        $unidad = Unidad::where('compra_id', $compra->id)->first();
        $servicio = app(\App\Support\RegistroDeVenta::class);

        $venta = $servicio->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => '1500', 'descuento' => '0']],
            [],
            $this->admin()->id
        );

        $servicio->anular($venta, 'Devolución del cliente');

        $r = $componente->call('abrirDetalle', $compra->id)->instance()->rentabilidad();

        $this->assertSame('0.00', $r['ingreso']);
        $this->assertSame('0.00', $r['ganancia']);
    }

    // ---- Selector de producto en cascada ----------------------------------

    public function test_el_selector_solo_ofrece_categorias_con_productos_agregables(): void
    {
        $electronica = Categoria::factory()->create(['nombre' => 'Electrónica', 'padre_id' => null]);
        $audio = Categoria::factory()->create(['nombre' => 'Audio', 'padre_id' => $electronica->id]);
        $vacia = Categoria::factory()->create(['nombre' => 'Sin nada', 'padre_id' => null]);

        Producto::factory()->create(['categoria_id' => $audio->id, 'activo' => true]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            // «Audio» tiene el producto y «Electrónica» sale por ser su
            // ancestro: sin ella no se podría llegar a la hija.
            ->assertSee('Audio')
            ->assertSee('Electrónica')
            // Una categoría sin nada que agregar sería un callejón sin salida.
            ->assertDontSee($vacia->nombre);
    }

    public function test_elegir_categoria_padre_incluye_los_productos_de_sus_hijas(): void
    {
        $electronica = Categoria::factory()->create(['padre_id' => null]);
        $audio = Categoria::factory()->create(['padre_id' => $electronica->id]);

        $enHija = Producto::factory()->create(['categoria_id' => $audio->id, 'activo' => true]);
        $enOtra = Producto::factory()->create(['activo' => true]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('categoriaLinea', $electronica->id)
            ->assertSee($enHija->nombre)
            ->assertDontSee($enOtra->nombre);
    }

    public function test_la_marca_se_suelta_si_no_tiene_productos_en_la_nueva_categoria(): void
    {
        $catA = Categoria::factory()->create(['padre_id' => null]);
        $catB = Categoria::factory()->create(['padre_id' => null]);
        $marcaA = Marca::factory()->create();

        Producto::factory()->create(['categoria_id' => $catA->id, 'marca_id' => $marcaA->id, 'activo' => true]);
        Producto::factory()->create(['categoria_id' => $catB->id, 'marca_id' => null, 'activo' => true]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('categoriaLinea', $catA->id)
            ->set('marcaLinea', $marcaA->id)
            ->assertSet('marcaLinea', $marcaA->id)
            // En catB esa marca no tiene nada: dejarla puesta daría una lista
            // vacía sin que se vea por qué.
            ->set('categoriaLinea', $catB->id)
            ->assertSet('marcaLinea', null);
    }

    public function test_el_selector_no_ofrece_productos_ya_agregados_a_la_compra(): void
    {
        $yaEsta = Producto::factory()->create(['nombre' => 'Ya agregado', 'activo' => true]);
        Producto::factory()->create(['nombre' => 'Todavia libre', 'activo' => true]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('agregarLinea', $yaEsta->id)
            ->assertCount('lineas', 1)
            ->assertSee('Todavia libre')
            // Un producto repetido haría ambiguo el reparto y chocaría con el
            // índice único de compra_detalles.
            ->call('agregarLinea', $yaEsta->id)
            ->assertDispatched('toast', tipo: 'error')
            ->assertCount('lineas', 1);
    }

    // ---- Seriales de las unidades generadas -------------------------------

    /**
     * Compra ya recepcionada con N unidades de un producto.
     */
    private function compraRecepcionada(int $cantidad = 3, string $costoTotal = '1000.00'): Compra
    {
        $compra = $this->compraBorrador();
        $producto = Producto::factory()->create(['meses_garantia' => 0]);

        CompraDetalle::factory()->create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'cantidad' => $cantidad,
            'costo_unitario' => bcdiv($costoTotal, (string) $cantidad, 2),
            'subtotal' => $costoTotal,
            'precio_venta' => $producto->precio_venta,
        ]);

        $compra->update(['subtotal' => $costoTotal, 'total' => $costoTotal]);

        app(\App\Support\RecepcionDeCompra::class)->recepcionar($compra->fresh());

        return $compra->fresh();
    }

    public function test_el_costo_de_las_unidades_suma_exacto_aunque_la_division_no_sea_exacta(): void
    {
        // 1000 entre 3 no da un decimal exacto: sin reparto se perderían o
        // inventarían centavos frente a la factura del proveedor.
        $compra = $this->compraRecepcionada(3, '1000.00');

        $costos = Unidad::where('compra_id', $compra->id)
            ->pluck('costo_unitario')
            ->map(fn ($c) => (int) round((float) $c * 100))
            ->all();

        $this->assertSame(100000, array_sum($costos));
        sort($costos);
        $this->assertSame([33333, 33333, 33334], $costos);
    }

    public function test_registra_los_seriales_de_las_unidades_desde_la_compra(): void
    {
        $compra = $this->compraRecepcionada(3);
        $unidades = Unidad::where('compra_id', $compra->id)->orderBy('id')->get();

        $componente = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirDetalle', $compra->id)
            ->call('abrirSeriales');

        // El código interno ya venía generado por la recepción.
        $this->assertNotEmpty($unidades->first()->codigo_interno);

        $componente
            ->set('seriales.'.$unidades[0]->id, 'SER-AAA')
            ->set('seriales.'.$unidades[1]->id, 'SER-BBB')
            // El tercero se deja vacío: se puede completar después.
            ->set('seriales.'.$unidades[2]->id, '')
            ->call('guardarSeriales')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success');

        $this->assertSame('SER-AAA', $unidades[0]->fresh()->serial);
        $this->assertSame('SER-BBB', $unidades[1]->fresh()->serial);
        $this->assertNull($unidades[2]->fresh()->serial);
    }

    public function test_no_admite_dos_seriales_iguales_en_el_mismo_formulario(): void
    {
        $compra = $this->compraRecepcionada(2);
        $unidades = Unidad::where('compra_id', $compra->id)->orderBy('id')->get();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirDetalle', $compra->id)
            ->call('abrirSeriales')
            ->set('seriales.'.$unidades[0]->id, 'REPETIDO')
            ->set('seriales.'.$unidades[1]->id, 'REPETIDO')
            ->call('guardarSeriales')
            ->assertHasErrors('seriales');

        // Nada se guarda si el lote es inválido.
        $this->assertNull($unidades[0]->fresh()->serial);
        $this->assertNull($unidades[1]->fresh()->serial);
    }

    public function test_no_admite_un_serial_que_ya_usa_otra_unidad(): void
    {
        $compra = $this->compraRecepcionada(1);
        $unidad = Unidad::where('compra_id', $compra->id)->first();

        Unidad::factory()->create(['serial' => 'YA-EXISTE']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirDetalle', $compra->id)
            ->call('abrirSeriales')
            ->set('seriales.'.$unidad->id, 'YA-EXISTE')
            ->call('guardarSeriales')
            ->assertHasErrors('seriales');

        $this->assertNull($unidad->fresh()->serial);
    }

    public function test_no_se_pueden_registrar_seriales_de_una_compra_en_borrador(): void
    {
        $compra = $this->compraBorrador();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirDetalle', $compra->id)
            ->call('abrirSeriales')
            ->assertDispatched('toast', tipo: 'error');
    }

    // ---- Permisos ---------------------------------------------------------

    public function test_un_vendedor_no_entra_al_modulo_de_compras(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        $this->actingAs($vendedor)->get('/compras')->assertForbidden();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();
    }
}
