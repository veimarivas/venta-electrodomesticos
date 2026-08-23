<?php

namespace Tests\Feature;

use App\Livewire\Clientes\Index;
use App\Models\Cliente;
use App\Models\Persona;
use App\Models\User;
use App\Support\GeneradorCodigoCliente;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClienteCrudTest extends TestCase
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
     * @return array<string, string>
     */
    private function personaValida(array $sobrescribir = []): array
    {
        return array_merge([
            'carnet' => '8123456',
            'nombres' => 'Juan Carlos',
            'apellido_paterno' => 'Rivas',
            'apellido_materno' => 'Quispe',
            'celular' => '71234567',
            'direccion' => 'Av. Siempre Viva 742',
            'correo' => 'juan@correo.com',
            'fecha_nacimiento' => '1990-05-12',
        ], $sobrescribir);
    }

    // ---- Generación del código -------------------------------------------

    public function test_el_primer_codigo_es_cli_0001(): void
    {
        $this->assertSame('CLI-0001', app(GeneradorCodigoCliente::class)->siguiente());
    }

    public function test_los_codigos_son_correlativos(): void
    {
        $generador = app(GeneradorCodigoCliente::class);

        $generador->crearCon(['persona_id' => Persona::factory()->create()->id]);
        $generador->crearCon(['persona_id' => Persona::factory()->create()->id]);

        $this->assertSame(['CLI-0001', 'CLI-0002'], Cliente::orderBy('id')->pluck('codigo')->all());
    }

    public function test_el_codigo_de_un_cliente_archivado_no_se_reutiliza(): void
    {
        // Reutilizarlo rompería el histórico de ventas y chocaría con el
        // índice único de la columna.
        $generador = app(GeneradorCodigoCliente::class);

        $primero = $generador->crearCon(['persona_id' => Persona::factory()->create()->id]);
        $primero->delete();

        $this->assertSame('CLI-0002', $generador->siguiente());
    }

    // ---- Relación 1 a 1 con personas --------------------------------------

    public function test_el_cliente_toma_sus_datos_de_la_persona(): void
    {
        $persona = Persona::factory()->create([
            'nombres' => 'Ana',
            'apellido_paterno' => 'Lopez',
            'apellido_materno' => 'Caro',
        ]);
        $cliente = Cliente::factory()->create(['persona_id' => $persona->id]);

        // La ficha de cliente no guarda datos personales: los lee de personas.
        $this->assertSame('Ana Lopez Caro', $cliente->persona->nombre_completo);
        $this->assertTrue($persona->fresh()->cliente->is($cliente));
    }

    public function test_una_persona_no_puede_ser_cliente_dos_veces(): void
    {
        $persona = Persona::factory()->create();
        Cliente::factory()->create(['persona_id' => $persona->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('seleccionarPersona', $persona->id)
            ->assertDispatched('toast', tipo: 'error')
            ->assertSet('paso', 'buscar');

        $this->assertSame(1, Cliente::where('persona_id', $persona->id)->count());
    }

    public function test_una_persona_con_ficha_archivada_no_recibe_otra(): void
    {
        // El índice único de persona_id lo rechazaría, y además perdería su
        // código: el camino correcto es restaurar.
        $persona = Persona::factory()->create();
        Cliente::factory()->create(['persona_id' => $persona->id])->delete();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('seleccionarPersona', $persona->id)
            ->assertDispatched('toast', tipo: 'error')
            ->assertSet('paso', 'buscar');

        $this->assertSame(1, Cliente::withTrashed()->where('persona_id', $persona->id)->count());
    }

    public function test_una_persona_puede_ser_trabajador_y_cliente_a_la_vez(): void
    {
        // Son fichas independientes sobre la misma persona.
        $persona = Persona::factory()->create();
        \App\Models\Trabajador::factory()->create([
            'persona_id' => $persona->id,
            'cargo_id' => \App\Models\Cargo::factory()->create()->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('seleccionarPersona', $persona->id)
            ->assertSet('paso', 'asignar')
            ->call('asignar')
            ->assertDispatched('toast', tipo: 'success');

        $this->assertNotNull($persona->fresh()->cliente);
        $this->assertNotNull($persona->fresh()->trabajador);
    }

    // ---- Alta -------------------------------------------------------------

    public function test_asigna_como_cliente_a_una_persona_existente(): void
    {
        $persona = Persona::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('seleccionarPersona', $persona->id)
            ->assertSet('paso', 'asignar')
            ->assertSet('formularioValido', true)
            ->call('asignar')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-cliente')
            ->assertDispatched('toast', tipo: 'success');

        $cliente = Cliente::first();

        $this->assertSame($persona->id, $cliente->persona_id);
        $this->assertSame('CLI-0001', $cliente->codigo);
    }

    public function test_registra_persona_y_ficha_de_cliente_de_una_vez(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('irARegistrarPersona')
            ->assertSet('paso', 'nueva')
            ->set($this->personaValida())
            ->assertSet('formularioValido', true)
            ->call('registrarPersonaYAsignar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success');

        $this->assertDatabaseHas('personas', ['carnet' => '8123456', 'nombres' => 'Juan Carlos']);
        $this->assertSame('CLI-0001', Cliente::first()->codigo);
    }

    public function test_si_falla_la_ficha_no_queda_la_persona_suelta(): void
    {
        // Persona y ficha se crean en una transacción: media alta sería peor
        // que ninguna, porque el usuario creería que no registró nada.
        $admin = $this->admin();
        $personasAntes = Persona::count();

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('irARegistrarPersona')
            ->set($this->personaValida())
            ->set('carnet', '123')
            ->call('registrarPersonaYAsignar')
            ->assertHasErrors('carnet');

        $this->assertSame($personasAntes, Persona::count());
        $this->assertSame(0, Cliente::count());
    }

    public function test_el_buscador_del_alta_aprovecha_lo_tecleado(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('buscarPersona', '8123456')
            ->call('irARegistrarPersona')
            // Si son solo números es un carnet; si no, el nombre.
            ->assertSet('carnet', '8123456')
            ->assertSet('nombres', '');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->set('buscarPersona', 'Mariela')
            ->call('irARegistrarPersona')
            ->assertSet('nombres', 'Mariela')
            ->assertSet('carnet', '');
    }

    // ---- Validación -------------------------------------------------------

    public function test_las_reglas_de_persona_son_las_mismas_que_en_su_modulo(): void
    {
        $componente = Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('irARegistrarPersona');

        // El campo con el error se toca al final a propósito: assertHasErrors
        // comprobando el nombre de la regla solo funciona sobre la última
        // validación ejecutada.
        $componente->set($this->personaValida())
            ->set('carnet', 'ABC')
            ->assertHasErrors(['carnet' => 'regex']);

        $componente->set($this->personaValida())
            ->set('nombres', 'Juan9')
            ->assertHasErrors(['nombres' => 'regex']);

        $componente->set($this->personaValida())
            ->set('celular', '123')
            ->assertHasErrors(['celular' => 'regex']);
    }

    public function test_hace_falta_al_menos_un_apellido(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('irARegistrarPersona')
            ->set($this->personaValida(['apellido_paterno' => '', 'apellido_materno' => '']))
            ->call('registrarPersonaYAsignar')
            ->assertHasErrors(['apellido_paterno', 'apellido_materno']);

        $this->assertSame(0, Cliente::count());
    }

    public function test_no_se_repite_el_carnet_de_una_persona_ya_registrada(): void
    {
        Persona::factory()->create(['carnet' => '8123456']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('irARegistrarPersona')
            ->set($this->personaValida())
            ->call('registrarPersonaYAsignar')
            ->assertHasErrors(['carnet' => 'unique']);
    }

    // ---- Listado ----------------------------------------------------------

    public function test_el_listado_muestra_diez_registros_por_pagina(): void
    {
        Cliente::factory()->count(23)->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->assertViewHas('clientes', fn ($clientes) => $clientes->count() === 10
                && $clientes->total() === 23);
    }

    public function test_busca_por_codigo_y_por_los_datos_de_la_persona(): void
    {
        $persona = Persona::factory()->create(['nombres' => 'Mariela', 'carnet' => '9998887']);
        Cliente::factory()->create(['persona_id' => $persona->id, 'codigo' => 'CLI-0042']);
        Cliente::factory()->count(3)->create();

        $componente = Livewire::actingAs($this->admin())->test(Index::class);

        $componente->set('buscar', 'Mariela')
            ->assertViewHas('clientes', fn ($c) => $c->total() === 1);

        $componente->set('buscar', 'CLI-0042')
            ->assertViewHas('clientes', fn ($c) => $c->total() === 1);

        // La búsqueda por carnet pasa por el scope de personas.
        $componente->set('buscar', '9998887')
            ->assertViewHas('clientes', fn ($c) => $c->total() === 1);
    }

    // ---- Archivado y restauración ------------------------------------------

    public function test_archivar_conserva_la_ficha_y_a_la_persona(): void
    {
        $cliente = Cliente::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarArchivar', $cliente->id)
            ->assertSet('archivarId', $cliente->id)
            ->call('archivar')
            ->assertDispatched('toast', tipo: 'success');

        // El histórico de ventas seguirá apuntando aquí: nada se borra.
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
        $this->assertDatabaseHas('personas', ['id' => $cliente->persona_id, 'deleted_at' => null]);
    }

    public function test_restaurar_devuelve_al_cliente_con_su_codigo(): void
    {
        $cliente = Cliente::factory()->create(['codigo' => 'CLI-0007']);
        $cliente->delete();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('restaurar', $cliente->id)
            ->assertDispatched('toast', tipo: 'success');

        $cliente = $cliente->fresh();

        $this->assertFalse($cliente->trashed());
        $this->assertSame('CLI-0007', $cliente->codigo);
    }

    public function test_el_filtro_de_estado_separa_activos_y_archivados(): void
    {
        Cliente::factory()->count(2)->create();
        Cliente::factory()->count(3)->create()->each(fn ($c) => $c->delete());

        $componente = Livewire::actingAs($this->admin())->test(Index::class);

        $componente->set('filtroEstado', 'activos')
            ->assertViewHas('clientes', fn ($c) => $c->total() === 2);

        $componente->set('filtroEstado', 'archivados')
            ->assertViewHas('clientes', fn ($c) => $c->total() === 3);

        $componente->set('filtroEstado', 'todos')
            ->assertViewHas('clientes', fn ($c) => $c->total() === 5);
    }

    // ---- Permisos ---------------------------------------------------------

    public function test_un_vendedor_registra_clientes_pero_no_los_archiva(): void
    {
        // El vendedor tiene clientes.ver y clientes.crear (registrar a quien
        // compra es parte de vender), pero no clientes.eliminar.
        $vendedor = User::factory()->create()->syncRoles('vendedor');
        $cliente = Cliente::factory()->create();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertSet('paso', 'buscar');

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('confirmarArchivar', $cliente->id)
            ->assertForbidden();
    }

    public function test_quien_no_tiene_permiso_no_registra_clientes(): void
    {
        $sinPermiso = User::factory()->create();

        Livewire::actingAs($sinPermiso)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();
    }

    public function test_la_ruta_exige_el_permiso_de_ver_clientes(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)->get('/clientes')->assertForbidden();
        $this->actingAs($this->admin())->get('/clientes')->assertOk();
    }
}
