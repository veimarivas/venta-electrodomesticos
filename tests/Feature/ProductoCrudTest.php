<?php

namespace Tests\Feature;

use App\Livewire\Productos\Index;
use App\Models\Marca;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductoCrudTest extends TestCase
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

    private function categoria(): Categoria
    {
        return Categoria::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function datosValidos(array $sobrescribir = []): array
    {
        return array_merge([
            'nombre' => 'Smart TV 55" 4K',
            'slug' => 'smart-tv-55-4k',
            'sku' => 'TV-55-4K',
            'categoriaId' => null,
            'marcaId' => null,
            'modelo' => 'UN55CU8000',
            'descripcion' => 'Pantalla 55 pulgadas con resolución 4K.',
            // Las especificaciones son filas clave/valor, no un textarea de líneas.
            'especificaciones' => [],
            'precio' => '4299.00',
            'minStock' => 3,
            'mesesGarantia' => 12,
            'isActive' => true,
        ], $sobrescribir);
    }

    public function test_el_listado_muestra_diez_registros_por_pagina(): void
    {
        Producto::factory()->count(23)->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('productos', fn ($productos) => $productos->count() === 10
                && $productos->total() === 23
                && $productos->lastPage() === 3);
    }

    public function test_el_buscador_filtra_por_nombre_sku_y_modelo(): void
    {
        Producto::factory()->count(15)->create();
        Producto::factory()->create(['sku' => 'ZZZ-0001', 'nombre' => 'Buscada']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('buscar', 'ZZZ-0001')
            ->assertViewHas('productos', fn ($productos) => $productos->total() === 1
                && $productos->first()->nombre === 'Buscada');
    }

    public function test_el_filtro_de_categoria_acota_el_listado(): void
    {
        $catA = Categoria::factory()->create();
        $catB = Categoria::factory()->create();
        Producto::factory()->count(5)->create(['categoria_id' => $catA->id]);
        Producto::factory()->count(3)->create(['categoria_id' => $catB->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('categoriaFiltro', $catA->id)
            ->assertViewHas('productos', fn ($productos) => $productos->total() === 5);
    }

    public function test_el_filtro_de_marca_acota_el_listado(): void
    {
        $marca = Marca::factory()->create();
        Producto::factory()->count(4)->create(['marca_id' => $marca->id]);
        Producto::factory()->count(2)->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('marcaFiltro', $marca->id)
            ->assertViewHas('productos', fn ($productos) => $productos->total() === 4);
    }

    public function test_al_llegar_desde_categorias_el_listado_se_abre_filtrado_sin_exponer_la_url(): void
    {
        $catA = Categoria::factory()->create(['nombre' => 'Audio']);
        $catB = Categoria::factory()->create(['nombre' => 'Televisores']);
        Producto::factory()->create(['nombre' => 'Parlante X', 'categoria_id' => $catA->id]);
        Producto::factory()->create(['nombre' => 'Parlante Y', 'categoria_id' => $catA->id]);
        Producto::factory()->create(['nombre' => 'TV Z', 'categoria_id' => $catB->id]);

        // La categoría viaja por sesión, no en el query string de la URL.
        $respuesta = $this->withSession(['categoria_activa' => $catA->id])
            ->actingAs($this->admin())
            ->get('/productos');

        $respuesta
            ->assertOk()
            ->assertSee('Explorando la categoría', false)
            ->assertSee('Audio')
            ->assertSee('Parlante X')
            ->assertSee('Parlante Y')
            ->assertDontSee('TV Z');

        $this->assertStringNotContainsString('categoria=', $respuesta->getContent());
    }

    public function test_el_formulario_no_es_valido_mientras_falten_campos_obligatorios(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSet('formularioValido', false)
            ->set('nombre', 'TV')
            ->assertSet('formularioValido', false)
            ->set('sku', 'TV-01')
            ->assertSet('formularioValido', false)
            ->set('categoriaId', $this->categoria()->id)
            ->assertSet('formularioValido', false)
            ->set('precio', '100')
            ->assertSet('formularioValido', true);
    }

    public function test_valida_cada_campo_apenas_cambia(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('sku', 'a!')
            ->assertHasErrors(['sku' => 'regex'])
            ->set('precio', '-5')
            ->assertHasErrors(['precio' => 'min'])
            ->set('mesesGarantia', 999)
            ->assertHasErrors(['mesesGarantia' => 'max'])
            ->set('categoriaId', 99999)
            ->assertHasErrors(['categoriaId' => 'exists']);
    }

    public function test_el_slug_se_genera_desde_el_nombre(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombre', 'Barra de Sonido 2.1')
            ->assertSet('slug', 'barra-de-sonido-21')
            ->set('sku', 'BAR-21')
            ->set('categoriaId', $this->categoria()->id)
            ->set('precio', '100')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('productos', ['slug' => 'barra-de-sonido-21']);
    }

    public function test_el_slug_manual_no_se_sobrescribe_al_cambiar_el_nombre(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombre', 'Barra de Sonido')
            ->set('slug', 'barra-sonido')
            ->set('nombre', 'Barra de Sonido Pro')
            ->assertSet('slug', 'barra-sonido');
    }

    public function test_no_permite_dos_productos_con_el_mismo_sku(): void
    {
        Producto::factory()->create(['sku' => 'TV-55-4K']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos())
            ->call('guardar')
            ->assertHasErrors(['sku' => 'unique']);

        $this->assertSame(1, Producto::where('sku', 'TV-55-4K')->count());
    }

    public function test_registra_un_producto_con_categoria_marca_y_especificaciones(): void
    {
        $categoria = $this->categoria();
        $marca = Marca::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->datosValidos([
                'categoriaId' => $categoria->id,
                'marcaId' => $marca->id,
                // Una fila por característica. La última va sin valor: se
                // guarda como bandera (HDR => true).
                'especificaciones' => [
                    ['clave' => 'Pantalla', 'valor' => '55 pulgadas'],
                    ['clave' => 'Resolución', 'valor' => '4K'],
                    ['clave' => 'HDR', 'valor' => ''],
                ],
            ]))
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-producto')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Producto creado correctamente.');

        $this->assertDatabaseHas('productos', [
            'sku' => 'TV-55-4K',
            'categoria_id' => $categoria->id,
            'marca_id' => $marca->id,
            'precio_venta' => 4299.00,
        ]);

        $producto = Producto::first();

        $this->assertSame(
            ['Pantalla' => '55 pulgadas', 'Resolución' => '4K', 'HDR' => true],
            $producto->especificaciones
        );
    }

    public function test_agregar_y_quitar_especificaciones_no_guarda_el_producto(): void
    {
        // El repetidor solo toca el arreglo en memoria: el producto se
        // registra una sola vez, al pulsar guardar.
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertCount('especificaciones', 1)
            ->call('agregarEspecificacion')
            ->call('agregarEspecificacion')
            ->assertCount('especificaciones', 3)
            ->set('especificaciones.0.clave', 'Pantalla')
            ->set('especificaciones.0.valor', '55 pulgadas')
            ->call('quitarEspecificacion', 1)
            ->assertCount('especificaciones', 2)
            // Al quitar una fila del medio las demás se reindexan, si no
            // Livewire deja de casar cada fila con sus inputs.
            ->assertSet('especificaciones.0.clave', 'Pantalla');

        $this->assertSame(0, Producto::count());
    }

    public function test_al_editar_el_slug_y_sku_propios_no_chocan_consigo_mismo(): void
    {
        $categoria = $this->categoria();
        $producto = Producto::factory()->create(['sku' => 'TV-55-4K', 'categoria_id' => $categoria->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $producto->id)
            ->assertSet('sku', 'TV-55-4K')
            ->set('nombre', 'Smart TV 55" QLED')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Producto actualizado correctamente.');

        $this->assertSame('Smart TV 55" QLED', $producto->fresh()->nombre);
    }

    public function test_elimina_un_producto(): void
    {
        $producto = Producto::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $producto->id)
            ->assertSet('eliminarId', $producto->id)
            ->call('eliminar')
            ->assertDispatched('cerrar-modal-eliminar-producto')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Producto eliminado correctamente.');

        $this->assertSoftDeleted('productos', ['id' => $producto->id]);
    }

    public function test_un_vendedor_puede_ver_pero_no_modificar(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        $this->actingAs($vendedor)->get('/productos')->assertOk();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();
    }

    public function test_un_usuario_sin_permiso_no_entra_al_listado(): void
    {
        $sinRol = User::factory()->create();

        $this->actingAs($sinRol)->get('/productos')->assertForbidden();
    }
}
