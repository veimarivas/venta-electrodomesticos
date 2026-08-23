<?php

namespace Tests\Feature;

use App\Livewire\Proveedores\Index;
use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProveedorCrudTest extends TestCase
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
            'nombre' => 'Importadora Andina SRL',
            'nit' => '1023456789',
            'contacto' => 'Carlos Mendoza',
            'telefono' => '71234567',
            'correo' => 'ventas@andina.com',
            'direccion' => 'Av. Comercio 123',
            'notas' => '',
            'activo' => true,
        ], $sobrescribir);
    }

    public function test_el_listado_muestra_diez_por_pagina(): void
    {
        Proveedor::factory()->count(14)->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('proveedores', fn ($p) => $p->count() === 10 && $p->total() === 14);
    }

    public function test_el_boton_de_guardar_sigue_a_la_validacion(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertSet('formularioValido', false)
            ->set('nombre', 'Im')
            ->assertHasErrors(['nombre' => 'min'])
            ->set('nombre', 'Importadora Andina')
            ->assertSet('formularioValido', true);
    }

    public function test_registra_un_proveedor(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set($this->datosValidos())
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success', mensaje: 'Proveedor registrado correctamente.');

        $this->assertDatabaseHas('proveedores', ['nombre' => 'Importadora Andina SRL']);
    }

    public function test_no_permite_dos_proveedores_con_el_mismo_nit(): void
    {
        Proveedor::factory()->create(['nit' => '1023456789']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set($this->datosValidos())
            ->call('guardar')
            ->assertHasErrors(['nit' => 'unique']);
    }

    public function test_dos_proveedores_pueden_no_tener_nit(): void
    {
        // El NIT vacío se guarda como NULL: si fuera cadena vacía, el segundo
        // chocaría contra el índice único.
        $componente = Livewire::actingAs($this->admin())->test(Index::class);

        $componente->set($this->datosValidos(['nit' => '', 'correo' => 'a@a.com']))->call('guardar');
        $componente->call('abrirCrear')
            ->set($this->datosValidos(['nombre' => 'Otra Empresa SRL', 'nit' => '', 'correo' => 'b@b.com']))
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(2, Proveedor::whereNull('nit')->count());
    }

    public function test_el_filtro_de_estado_funciona(): void
    {
        Proveedor::factory()->create(['activo' => true]);
        Proveedor::factory()->create(['activo' => false]);

        $componente = Livewire::actingAs($this->admin())->test(Index::class);

        $componente->set('filtroEstado', 'activos')
            ->assertViewHas('proveedores', fn ($p) => $p->total() === 1);
        $componente->set('filtroEstado', 'inactivos')
            ->assertViewHas('proveedores', fn ($p) => $p->total() === 1);
    }

    public function test_alternar_estado_lo_saca_del_selector_de_compras(): void
    {
        $proveedor = Proveedor::factory()->create(['activo' => true]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('alternarEstado', $proveedor->id)
            ->assertDispatched('toast', tipo: 'success');

        $this->assertFalse($proveedor->fresh()->activo);
    }

    public function test_elimina_un_proveedor_sin_compras(): void
    {
        $proveedor = Proveedor::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $proveedor->id)
            ->assertSet('eliminarCompras', 0)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'success');

        $this->assertSoftDeleted('proveedores', ['id' => $proveedor->id]);
    }

    public function test_no_elimina_un_proveedor_con_compras(): void
    {
        // Dejaría sin origen el costo de las unidades que trajo.
        $proveedor = Proveedor::factory()->create();
        Compra::factory()->count(2)->create(['proveedor_id' => $proveedor->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarEliminar', $proveedor->id)
            ->assertSet('eliminarCompras', 2)
            ->call('eliminar')
            ->assertDispatched('toast', tipo: 'error');

        $this->assertDatabaseHas('proveedores', ['id' => $proveedor->id, 'deleted_at' => null]);
    }

    public function test_un_vendedor_no_entra_al_modulo(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        $this->actingAs($vendedor)->get('/proveedores')->assertForbidden();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();
    }
}
