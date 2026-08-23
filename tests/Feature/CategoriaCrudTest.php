<?php

namespace Tests\Feature;

use App\Livewire\Categorias\Index;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CategoriaCrudTest extends TestCase
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
            'nombre' => 'Audio',
            'slug' => 'audio',
            'padreId' => null,
            'descripcion' => 'Equipos de sonido.',
            'posicion' => 0,
            'isActive' => true,
        ], $sobrescribir);
    }

    public function test_el_listado_muestra_el_arbol_aplanado_en_orden(): void
    {
        $electronica = Categoria::factory()->create(['nombre' => 'Electrónica', 'slug' => 'electronica', 'posicion' => 0]);
        $audio = Categoria::factory()->create(['nombre' => 'Audio', 'slug' => 'audio', 'padre_id' => $electronica->id, 'posicion' => 0]);
        Categoria::factory()->create(['nombre' => 'Televisores', 'slug' => 'televisores', 'padre_id' => $electronica->id, 'posicion' => 1]);
        Categoria::factory()->create(['nombre' => 'Parlantes', 'slug' => 'parlantes', 'padre_id' => $audio->id, 'posicion' => 0]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('filas', fn ($filas) => collect($filas)
                ->map(fn ($fila) => $fila['categoria']->slug)
                ->all() === ['electronica', 'audio', 'parlantes', 'televisores'])
            ->assertViewHas('filas', fn ($filas) => array_column($filas, 'profundidad') === [0, 1, 2, 1])
            ->assertViewHas('totalCategorias', 4);
    }

    public function test_el_listado_muestra_el_conteo_de_productos_y_el_boton_para_verlos(): void
    {
        $categoria = Categoria::factory()->create();
        Producto::factory()->count(2)->create(['categoria_id' => $categoria->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('filas', fn ($filas) => $filas[0]['numProductos'] === 2)
            ->assertSeeHtml('wire:click="verProductos('.$categoria->id.')"');
    }

    public function test_ver_productos_guarda_la_categoria_en_sesion_y_redirige_sin_exponerla(): void
    {
        $categoria = Categoria::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('verProductos', $categoria->id)
            ->assertRedirect(route('productos.index'));

        $this->assertSame($categoria->id, session('categoria_activa'));
    }

    public function test_el_buscador_filtra_y_muestra_la_ruta_de_ancestros(): void
    {
        $electronica = Categoria::factory()->create(['nombre' => 'Electrónica', 'slug' => 'electronica']);
        $audio = Categoria::factory()->create(['nombre' => 'Audio', 'slug' => 'audio', 'padre_id' => $electronica->id]);
        $parlantes = Categoria::factory()->create(['nombre' => 'Parlantes', 'slug' => 'parlantes', 'padre_id' => $audio->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('buscar', 'Parlantes')
            ->assertViewHas('filas', fn ($filas) => count($filas) === 1
                && $filas[0]['categoria']['id'] === $parlantes->id
                && $filas[0]['ruta'] === 'Electrónica / Audio');
    }

    public function test_el_formulario_no_es_valido_mientras_falte_el_nombre(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSet('formularioValido', false)
            ->set('nombre', 'Au')
            ->assertSet('formularioValido', true);
    }

    public function test_valida_cada_campo_apenas_cambia(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombre', 'A')
            ->assertHasErrors(['nombre' => 'min'])
            ->set('slug', 'Slug inválido!')
            ->assertHasErrors(['slug' => 'regex'])
            ->set('posicion', -1)
            ->assertHasErrors(['posicion' => 'min'])
            ->set('descripcion', str_repeat('a', 501))
            ->assertHasErrors(['descripcion' => 'max']);
    }

    public function test_el_slug_se_genera_desde_el_nombre(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombre', 'Audio y Video')
            ->assertSet('slug', 'audio-y-video')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categorias', [
            'nombre' => 'Audio y Video',
            'slug' => 'audio-y-video',
        ]);
    }

    public function test_el_slug_manual_no_se_sobrescribe_al_cambiar_el_nombre(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombre', 'Audio')
            ->set('slug', 'sonido')
            ->set('nombre', 'Audio Pro')
            ->assertSet('slug', 'sonido');
    }

    public function test_no_permite_dos_categorias_con_el_mismo_slug(): void
    {
        Categoria::factory()->create(['slug' => 'audio']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos())
            ->call('guardar')
            ->assertHasErrors(['slug' => 'unique']);

        $this->assertSame(1, Categoria::where('slug', 'audio')->count());
    }

    public function test_registra_una_categoria_padre_y_una_hija(): void
    {
        $electronica = Categoria::factory()->create(['nombre' => 'Electrónica', 'slug' => 'electronica']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->datosValidos([
                'nombre' => 'Audio',
                'slug' => 'audio',
                'padreId' => $electronica->id,
                'isActive' => false,
            ]))
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-categoria')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Categoría creada correctamente.');

        $this->assertDatabaseHas('categorias', [
            'nombre' => 'Audio',
            'slug' => 'audio',
            'padre_id' => $electronica->id,
            'activo' => false,
        ]);
    }

    public function test_al_editar_el_slug_propio_no_choca_consigo_mismo(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Audio', 'slug' => 'audio']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $categoria->id)
            ->assertSet('slug', 'audio')
            ->set('slug', 'sonido')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Categoría actualizada correctamente.');

        $this->assertSame('sonido', $categoria->fresh()->slug);
    }

    public function test_no_permite_colgar_una_categoria_de_si_misma_ni_de_una_hija(): void
    {
        $electronica = Categoria::factory()->create(['nombre' => 'Electrónica', 'slug' => 'electronica']);
        $audio = Categoria::factory()->create(['nombre' => 'Audio', 'slug' => 'audio', 'padre_id' => $electronica->id]);
        Categoria::factory()->create(['nombre' => 'Parlantes', 'slug' => 'parlantes', 'padre_id' => $audio->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $electronica->id)
            ->set('padreId', $electronica->id)
            ->assertHasErrors(['padreId' => 'not_in'])
            ->set('padreId', $audio->id)
            ->assertHasErrors(['padreId' => 'not_in']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('padreId', 99999)
            ->assertHasErrors(['padreId' => 'exists']);
    }

    public function test_no_elimina_una_categoria_que_tiene_subcategorias(): void
    {
        $electronica = Categoria::factory()->create(['nombre' => 'Electrónica', 'slug' => 'electronica']);
        Categoria::factory()->create(['nombre' => 'Audio', 'slug' => 'audio', 'padre_id' => $electronica->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $electronica->id)
            ->assertSet('eliminarId', null)
            ->assertDispatched('toast', tipo: 'warning', mensaje: 'Esta categoría tiene subcategorías. Muévelas o elimínalas primero.');

        $this->assertNotSoftDeleted('categorias', ['id' => $electronica->id]);
    }

    public function test_elimina_una_categoria_sin_hijos(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Audio', 'slug' => 'audio']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $categoria->id)
            ->assertSet('eliminarId', $categoria->id)
            ->call('eliminar')
            ->assertDispatched('cerrar-modal-eliminar-categoria')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Categoría eliminada correctamente.');

        $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);
    }

    public function test_eliminar_una_categoria_con_hijos_directamente_devuelve_warning(): void
    {
        $electronica = Categoria::factory()->create(['nombre' => 'Electrónica', 'slug' => 'electronica']);
        Categoria::factory()->create(['nombre' => 'Audio', 'slug' => 'audio', 'padre_id' => $electronica->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('eliminarId', $electronica->id)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'warning', mensaje: 'Esta categoría tiene subcategorías. Muévelas o elimínalas primero.');

        $this->assertNotSoftDeleted('categorias', ['id' => $electronica->id]);
    }

    public function test_un_vendedor_puede_ver_pero_no_modificar(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');
        $categoria = Categoria::factory()->create();

        $this->actingAs($vendedor)->get('/categorias')->assertOk();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('confirmarEliminar', $categoria->id)
            ->assertForbidden();
    }

    public function test_un_usuario_sin_permiso_no_entra_al_listado(): void
    {
        $sinRol = User::factory()->create();

        $this->actingAs($sinRol)->get('/categorias')->assertForbidden();
    }
}
