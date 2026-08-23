<?php

namespace Tests\Feature;

use App\Livewire\Marcas\Index;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MarcaCrudTest extends TestCase
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
            'nombre' => 'Samsung',
            'slug' => 'samsung',
            'isActive' => true,
        ], $sobrescribir);
    }

    public function test_el_listado_muestra_diez_registros_por_pagina(): void
    {
        Marca::factory()->count(23)->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('marcas', fn ($marcas) => $marcas->count() === 10
                && $marcas->total() === 23
                && $marcas->lastPage() === 3);
    }

    public function test_el_buscador_filtra_por_nombre(): void
    {
        Marca::factory()->count(15)->create();
        Marca::factory()->create(['nombre' => 'Buscada', 'slug' => 'buscada']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('buscar', 'Buscada')
            ->assertViewHas('marcas', fn ($marcas) => $marcas->total() === 1
                && $marcas->first()->nombre === 'Buscada');
    }

    public function test_el_formulario_no_es_valido_mientras_falte_el_nombre(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSet('formularioValido', false)
            ->set('nombre', 'S')
            ->assertSet('formularioValido', false)
            ->set('nombre', 'Samsung')
            ->assertSet('formularioValido', true);
    }

    public function test_el_slug_se_genera_desde_el_nombre(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombre', 'Samsung Electronics')
            ->assertSet('slug', 'samsung-electronics')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('marcas', [
            'nombre' => 'Samsung Electronics',
            'slug' => 'samsung-electronics',
        ]);
    }

    public function test_el_slug_manual_no_se_sobrescribe_al_cambiar_el_nombre(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombre', 'Samsung')
            ->set('slug', 'samsung-electronics')
            ->set('nombre', 'Samsung Electronic')
            ->assertSet('slug', 'samsung-electronics');
    }

    public function test_no_permite_dos_marcas_con_el_mismo_nombre_ni_slug(): void
    {
        Marca::factory()->create(['nombre' => 'Samsung', 'slug' => 'samsung']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos())
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'unique']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos(['nombre' => 'Otra', 'slug' => 'samsung']))
            ->call('guardar')
            ->assertHasErrors(['slug' => 'unique']);
    }

    public function test_registra_una_marca_y_notifica(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->datosValidos())
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-marca')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Marca creada correctamente.');

        $this->assertDatabaseHas('marcas', ['nombre' => 'Samsung', 'slug' => 'samsung']);
    }

    public function test_al_editar_el_nombre_propio_no_choca_consigo_mismo(): void
    {
        $marca = Marca::factory()->create(['nombre' => 'Samsung', 'slug' => 'samsung']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $marca->id)
            ->set('nombre', 'Samsung Electronics')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Marca actualizada correctamente.');

        $this->assertSame('Samsung Electronics', $marca->fresh()->nombre);
    }

    public function test_subir_logo_guarda_el_archivo_en_publico(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos())
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('guardar')
            ->assertHasNoErrors();

        $logo = Marca::where('slug', 'samsung')->first()->logo_ruta;

        $this->assertNotNull($logo);
        Storage::disk('public')->assertExists($logo);
    }

    public function test_quitar_logo_al_editar_borra_el_archivo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('marcas/x.png', 'contenido');
        $marca = Marca::factory()->create(['logo_ruta' => 'marcas/x.png']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $marca->id)
            ->set('quitarLogo', true)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertNull($marca->fresh()->logo_ruta);
        Storage::disk('public')->assertMissing('marcas/x.png');
    }

    public function test_no_elimina_una_marca_con_productos(): void
    {
        $marca = Marca::factory()->create();
        Producto::factory()->create(['marca_id' => $marca->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $marca->id)
            ->assertSet('eliminarProductos', 1)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'error', mensaje: 'No se puede eliminar: 1 producto(s) usan esta marca.');

        $this->assertDatabaseHas('marcas', ['id' => $marca->id]);
    }

    public function test_elimina_una_marca_sin_productos(): void
    {
        $marca = Marca::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $marca->id)
            ->call('eliminar')
            ->assertDispatched('cerrar-modal-eliminar-marca')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Marca eliminada correctamente.');

        $this->assertDatabaseMissing('marcas', ['id' => $marca->id]);
    }

    public function test_un_vendedor_puede_ver_pero_no_modificar(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        $this->actingAs($vendedor)->get('/marcas')->assertOk();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();
    }

    public function test_un_usuario_sin_permiso_no_entra_al_listado(): void
    {
        $sinRol = User::factory()->create();

        $this->actingAs($sinRol)->get('/marcas')->assertForbidden();
    }
}
