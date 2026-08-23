<?php

namespace Tests\Feature;

use App\Livewire\Unidades\Index;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Unidad;
use App\Models\Producto;
use App\Models\User;
use App\Support\GeneradorCodigoUnidad;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ItemCrudTest extends TestCase
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

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(array $sobrescribir = []): array
    {
        return array_merge([
            'productoId' => null,
            'serial' => 'SER-001',
            'costo' => '3000.00',
            'precio' => '4299.00',
            'estado' => 'en_stock',
            'ubicacion' => 'Bodega A / Estante 3',
            'fechaIngreso' => '2026-08-15',
            'notas' => '',
        ], $sobrescribir);
    }

    public function test_el_listado_muestra_diez_registros_por_pagina(): void
    {
        Unidad::factory()->count(23)->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('unidades', fn ($unidades) => $unidades->count() === 10
                && $unidades->total() === 23
                && $unidades->lastPage() === 3);
    }

    public function test_el_buscador_filtra_por_codigo_interno_serial_y_producto(): void
    {
        Unidad::factory()->count(15)->create();
        Unidad::factory()->create(['codigo_interno' => 'ITM-9999', 'serial' => 'SER-ABC']);
        $producto = Producto::factory()->create(['nombre' => 'Buscada']);
        Unidad::factory()->create(['producto_id' => $producto->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('buscar', 'ITM-9999')
            ->assertViewHas('unidades', fn ($unidades) => $unidades->total() === 1)
            ->set('buscar', 'SER-ABC')
            ->assertViewHas('unidades', fn ($unidades) => $unidades->total() === 1)
            ->set('buscar', 'Buscada')
            ->assertViewHas('unidades', fn ($unidades) => $unidades->total() === 1);
    }

    public function test_el_filtro_de_producto_acota_el_listado(): void
    {
        $prodA = Producto::factory()->create();
        $prodB = Producto::factory()->create();
        Unidad::factory()->count(5)->create(['producto_id' => $prodA->id]);
        Unidad::factory()->count(3)->create(['producto_id' => $prodB->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('productoFiltro', $prodA->id)
            ->assertViewHas('unidades', fn ($unidades) => $unidades->total() === 5);
    }

    public function test_el_filtro_de_estado_acota_el_listado(): void
    {
        Unidad::factory()->count(3)->vendido()->create();
        Unidad::factory()->count(2)->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('estadoFiltro', 'vendido')
            ->assertViewHas('unidades', fn ($unidades) => $unidades->total() === 3
                && $unidades->every(fn (Unidad $i) => $i->estado === 'vendido'));
    }

    public function test_al_llegar_desde_productos_el_listado_se_abre_filtrado_sin_exponer_la_url(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Televisores']);
        $marca = Marca::factory()->create(['nombre' => 'Samsung']);
        $producto = Producto::factory()->create([
            'nombre' => 'TV Samsung',
            'sku' => 'TV-SAM-55',
            'categoria_id' => $categoria->id,
            'marca_id' => $marca->id,
            'meses_garantia' => 24,
        ]);
        Unidad::factory()->count(3)->create(['producto_id' => $producto->id]);
        Unidad::factory()->count(2)->create();

        // El producto viaja por sesión, nunca en el query string de la URL.
        $respuesta = $this->withSession(['producto_activo' => $producto->id])
            ->actingAs($this->admin())
            ->get('/inventario/unidades');

        // La cabecera del listado muestra la ficha completa del producto, no
        // solo su nombre: categoría, SKU, marca y garantía.
        $respuesta
            ->assertOk()
            ->assertSee('TV Samsung')
            ->assertSee('TV-SAM-55')
            ->assertSee('Televisores')
            ->assertSee('Samsung')
            ->assertSee('24 meses');

        $this->assertStringNotContainsString('producto_activo', $respuesta->getContent());
    }

    public function test_la_ficha_del_producto_solo_sale_dentro_de_un_producto(): void
    {
        // Regresión: el selector de productos del filtro recorría la lista con
        // la misma variable `$producto` de la ficha y la dejaba apuntando al
        // último de la lista, así que la ficha salía siempre.
        Producto::factory()->count(3)->create();
        Unidad::factory()->count(2)->create();

        $this->actingAs($this->admin())
            ->get('/inventario/unidades')
            ->assertOk()
            ->assertDontSee('Todo el inventario');
    }

    public function test_al_crear_desde_un_producto_la_unidad_nace_en_ese_producto(): void
    {
        $producto = Producto::factory()->create(['precio_venta' => 1500]);

        // mount() consume 'producto_activo' de la sesión con pull().
        session(['producto_activo' => $producto->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSet('productoFiltro', $producto->id)
            ->call('abrirCrear')
            ->assertSet('productoId', $producto->id)
            // El precio de lista del producto se sugiere como precio de salida.
            ->assertSet('precio', '1500.00');
    }

    public function test_toda_unidad_nueva_se_registra_en_stock(): void
    {
        $producto = Producto::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertSet('estado', 'en_stock')
            // Aunque se fuerce otro estado desde fuera (el componente es un
            // endpoint invocable), al guardar se vuelve a fijar en_stock.
            ->set('productoId', $producto->id)
            ->set('serial', 'S-123')
            ->set('costo', '900.00')
            ->set('precio', '1500.00')
            ->set('estado', 'vendido')
            ->call('guardar')
            ->assertHasNoErrors();

        $unidad = Unidad::first();

        $this->assertSame('en_stock', $unidad->estado);
        $this->assertNull($unidad->vendido_en);
    }

    public function test_al_editar_si_se_puede_cambiar_el_estado(): void
    {
        $unidad = Unidad::factory()->create(['estado' => 'en_stock']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $unidad->id)
            ->set('estado', 'danado')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('danado', $unidad->fresh()->estado);
    }

    public function test_el_formulario_no_es_valido_mientras_falten_campos_obligatorios(): void
    {
        $producto = Producto::factory()->create(['precio_venta' => 0]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSet('formularioValido', false)
            ->set('productoId', $producto->id)
            ->assertSet('formularioValido', false)
            ->set('costo', '100')
            ->assertSet('formularioValido', false)
            ->set('precio', '100')
            ->assertSet('formularioValido', false)
            ->set('fechaIngreso', '2026-08-15')
            ->assertSet('formularioValido', true);
    }

    public function test_valida_cada_campo_apenas_cambia(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('costo', '-5')
            ->assertHasErrors(['costo' => 'min'])
            ->set('precio', 'abc')
            ->assertHasErrors(['precio' => 'numeric'])
            ->set('estado', 'hack')
            ->assertHasErrors(['estado' => 'in'])
            ->set('productoId', 99999)
            ->assertHasErrors(['productoId' => 'exists'])
            ->set('fechaIngreso', 'no-es-fecha')
            ->assertHasErrors(['fechaIngreso' => 'date']);
    }

    public function test_al_crear_la_unidad_se_sugiere_el_precio_del_producto(): void
    {
        $producto = Producto::factory()->create(['precio_venta' => 4299]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('productoId', $producto->id)
            ->assertSet('precio', '4299.00');
    }

    public function test_el_codigo_interno_se_genera_con_el_formato_del_plan(): void
    {
        $producto = Producto::factory()->create(['sku' => 'TVSAM55']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos(['productoId' => $producto->id]))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertMatchesRegularExpression('/^TVSAM55-\d{4}-\d{4}$/', Unidad::first()->codigo_interno);
    }

    public function test_el_correlativo_del_codigo_avanza_por_producto_y_mes(): void
    {
        $producto = Producto::factory()->create(['sku' => 'AAA']);
        $generador = app(GeneradorCodigoUnidad::class);
        $mes = now()->format('ym');

        $a = $generador->crearCon(['producto_id' => $producto->id]);
        $b = $generador->crearCon(['producto_id' => $producto->id]);

        $this->assertSame("AAA-{$mes}-0001", $a->codigo_interno);
        $this->assertSame("AAA-{$mes}-0002", $b->codigo_interno);
    }

    public function test_no_permite_dos_unidades_con_el_mismo_serial(): void
    {
        Unidad::factory()->create(['serial' => 'SER-001']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos(['productoId' => Producto::factory()->create()->id]))
            ->call('guardar')
            ->assertHasErrors(['serial' => 'unique']);

        $this->assertSame(1, Unidad::where('serial', 'SER-001')->count());
    }

    public function test_registra_una_unidad_y_notifica(): void
    {
        $producto = Producto::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->datosValidos(['productoId' => $producto->id]))
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-item')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Unidad registrada correctamente.');

        $this->assertDatabaseHas('unidades', [
            'producto_id' => $producto->id,
            'serial' => 'SER-001',
            'costo_unitario' => 3000.00,
            'precio_venta' => 4299.00,
            'estado' => 'en_stock',
            'ubicacion' => 'Bodega A / Estante 3',
        ]);
    }

    public function test_al_editar_el_serial_propio_no_choca_consigo_mismo(): void
    {
        $unidad = Unidad::factory()->create(['serial' => 'SER-001']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $unidad->id)
            ->assertSet('serial', 'SER-001')
            ->assertSet('codigoActual', $unidad->codigo_interno)
            ->set('costo', '3500')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Unidad actualizada correctamente.');

        // El cast decimal:2 devuelve cadena con dos decimales fijos.
        $this->assertSame('3500.00', $unidad->fresh()->costo_unitario);
        $this->assertSame($unidad->codigo_interno, $unidad->fresh()->codigo_interno);
    }

    public function test_elimina_una_unidad(): void
    {
        $unidad = Unidad::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $unidad->id)
            ->assertSet('eliminarId', $unidad->id)
            ->call('eliminar')
            ->assertDispatched('cerrar-modal-eliminar-item')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Unidad eliminada correctamente.');

        $this->assertSoftDeleted('unidades', ['id' => $unidad->id]);
    }

    public function test_no_permite_eliminar_una_unidad_vendida(): void
    {
        $unidad = Unidad::factory()->vendido()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $unidad->id)
            ->assertDispatched('toast', tipo: 'error', mensaje: 'No se puede eliminar una unidad vendida.')
            ->assertSet('eliminarId', null);

        $this->assertDatabaseHas('unidades', ['id' => $unidad->id]);
    }

    public function test_un_vendedor_puede_ver_pero_no_modificar(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        $this->actingAs($vendedor)->get('/inventario/unidades')->assertOk();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();
    }

    public function test_un_usuario_sin_permiso_no_entra_al_listado(): void
    {
        $sinRol = User::factory()->create();

        $this->actingAs($sinRol)->get('/inventario/unidades')->assertForbidden();
    }
}
