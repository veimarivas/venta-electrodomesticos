<?php

namespace Tests\Feature;

use App\Livewire\Cargos\Index;
use App\Models\Cargo;
use App\Models\Trabajador;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CargoCrudTest extends TestCase
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

    public function test_el_listado_muestra_diez_cargos_por_pagina(): void
    {
        Cargo::factory()->count(14)->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('cargos', fn ($cargos) => $cargos->count() === 10 && $cargos->total() === 14);
    }

    public function test_el_boton_de_guardar_sigue_a_la_validacion(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSet('formularioValido', false)
            ->set('nombre', 'Ca')
            ->assertHasErrors(['nombre' => 'min'])
            ->assertSet('formularioValido', false)
            ->set('nombre', 'Cajero')
            ->assertHasNoErrors()
            ->assertSet('formularioValido', true);
    }

    public function test_registra_un_cargo_y_notifica(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombre', 'Encargado de almacén')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-cargo')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Cargo registrado correctamente.');

        $this->assertDatabaseHas('cargos', ['nombre' => 'Encargado de almacén']);
    }

    public function test_no_permite_dos_cargos_con_el_mismo_nombre(): void
    {
        Cargo::factory()->create(['nombre' => 'Vendedor']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('nombre', 'Vendedor')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'unique']);
    }

    public function test_al_editar_el_nombre_propio_no_choca_consigo_mismo(): void
    {
        $cargo = Cargo::factory()->create(['nombre' => 'Vendedor']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $cargo->id)
            ->assertSet('nombre', 'Vendedor')
            ->set('nombre', 'Vendedor senior')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Vendedor senior', $cargo->fresh()->nombre);
    }

    public function test_elimina_un_cargo_sin_trabajadores(): void
    {
        $cargo = Cargo::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $cargo->id)
            ->assertSet('eliminarTrabajadoresActivos', 0)
            ->assertSet('eliminarTrabajadoresTotal', 0)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Cargo eliminado correctamente.');

        $this->assertDatabaseMissing('cargos', ['id' => $cargo->id]);
    }

    public function test_no_elimina_un_cargo_que_tiene_trabajadores(): void
    {
        // La FK es restrictOnDelete: sin la comprobación previa esto sería un
        // error 500 de base de datos en lugar de un aviso al usuario.
        $cargo = Cargo::factory()->create();
        Trabajador::factory()->count(2)->create(['cargo_id' => $cargo->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $cargo->id)
            ->assertSet('eliminarTrabajadoresActivos', 2)
            ->assertSet('eliminarTrabajadoresTotal', 2)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'error');

        $this->assertDatabaseHas('cargos', ['id' => $cargo->id]);
    }

    public function test_no_elimina_un_cargo_referenciado_solo_por_trabajadores_dados_de_baja(): void
    {
        // Los trabajadores con SoftDeletes siguen apuntando al cargo en la base
        // de datos, así que la FK restrictOnDelete impide borrarlo aunque ya no
        // haya personal vigente. El aviso debe bloquear el borrado sin que la
        // base de datos lance una QueryException.
        $cargo = Cargo::factory()->create();
        $trabajador = Trabajador::factory()->create(['cargo_id' => $cargo->id]);
        $trabajador->delete();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $cargo->id)
            ->assertSet('eliminarTrabajadoresActivos', 0)
            ->assertSet('eliminarTrabajadoresTotal', 1)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'error');

        $this->assertDatabaseHas('cargos', ['id' => $cargo->id]);
    }

    public function test_un_vendedor_no_puede_modificar_cargos(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        // El rol vendedor no tiene ni siquiera cargos.ver.
        $this->actingAs($vendedor)->get('/cargos')->assertForbidden();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();
    }
}
