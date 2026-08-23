<?php

namespace Tests\Feature;

use App\Livewire\Trabajadores\Index;
use App\Models\Cargo;
use App\Models\Persona;
use App\Models\Trabajador;
use App\Models\User;
use App\Support\GeneradorCodigoTrabajador;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class TrabajadorCrudTest extends TestCase
{
    use RefreshDatabase;

    private Cargo $cargo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->cargo = Cargo::factory()->create(['nombre' => 'Vendedor']);
    }

    private function admin(): User
    {
        return User::factory()->create()->syncRoles('admin');
    }

    // ---- Generación del código -------------------------------------------

    public function test_el_primer_codigo_es_cod_0001(): void
    {
        $this->assertSame('COD-0001', app(GeneradorCodigoTrabajador::class)->siguiente());
    }

    public function test_los_codigos_son_correlativos(): void
    {
        $generador = app(GeneradorCodigoTrabajador::class);

        $primero = $generador->crearCon([
            'persona_id' => Persona::factory()->create()->id,
            'cargo_id' => $this->cargo->id,
            'fecha_ingreso' => now()->format('Y-m-d'),
        ]);

        $segundo = $generador->crearCon([
            'persona_id' => Persona::factory()->create()->id,
            'cargo_id' => $this->cargo->id,
            'fecha_ingreso' => now()->format('Y-m-d'),
        ]);

        $this->assertSame('COD-0001', $primero->codigo);
        $this->assertSame('COD-0002', $segundo->codigo);
    }

    public function test_el_codigo_de_un_trabajador_dado_de_baja_no_se_reutiliza(): void
    {
        $generador = app(GeneradorCodigoTrabajador::class);

        $trabajador = $generador->crearCon([
            'persona_id' => Persona::factory()->create()->id,
            'cargo_id' => $this->cargo->id,
            'fecha_ingreso' => now()->format('Y-m-d'),
        ]);

        $trabajador->delete();

        // Reutilizar COD-0001 rompería el histórico y chocaría con el índice único.
        $this->assertSame('COD-0002', $generador->siguiente());
    }

    // ---- Búsqueda de personas dentro del alta ----------------------------

    public function test_la_busqueda_exige_al_menos_dos_caracteres(): void
    {
        Persona::factory()->create(['nombres' => 'Ana']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('buscarPersona', 'A')
            ->assertCount('resultadosPersonas', 0)
            ->set('buscarPersona', 'An')
            ->assertCount('resultadosPersonas', 1);
    }

    public function test_la_busqueda_encuentra_por_carnet_y_por_nombre(): void
    {
        Persona::factory()->create(['carnet' => '7654321', 'nombres' => 'Rosa']);

        $componente = Livewire::actingAs($this->admin())->test(Index::class);

        $componente->set('buscarPersona', '7654321')->assertCount('resultadosPersonas', 1);
        $componente->set('buscarPersona', 'Rosa')->assertCount('resultadosPersonas', 1);
    }

    public function test_avisa_cuando_la_persona_no_existe(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('buscarPersona', 'Inexistente')
            ->assertSet('sinResultados', true);
    }

    // ---- Camino 1: la persona ya existe ----------------------------------

    public function test_asigna_como_trabajador_a_una_persona_existente(): void
    {
        $persona = Persona::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertSet('paso', 'buscar')
            ->call('seleccionarPersona', $persona->id)
            ->assertSet('paso', 'asignar')
            // La fecha de ingreso se prellena con hoy.
            ->assertSet('fecha_ingreso', now()->format('Y-m-d'))
            ->set('cargo_id', (string) $this->cargo->id)
            ->assertSet('formularioValido', true)
            ->call('asignar')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-trabajador')
            ->assertDispatched('toast', tipo: 'success');

        $this->assertDatabaseHas('trabajadores', [
            'persona_id' => $persona->id,
            'cargo_id' => $this->cargo->id,
            'codigo' => 'COD-0001',
        ]);
    }

    public function test_no_se_puede_asignar_dos_veces_a_la_misma_persona(): void
    {
        $persona = Persona::factory()->create();
        Trabajador::factory()->create(['persona_id' => $persona->id, 'cargo_id' => $this->cargo->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('seleccionarPersona', $persona->id)
            ->assertDispatched('toast', tipo: 'error')
            // No debe avanzar de paso.
            ->assertSet('paso', 'buscar');

        $this->assertSame(1, Trabajador::where('persona_id', $persona->id)->count());
    }

    public function test_el_cargo_y_la_fecha_de_ingreso_son_obligatorios(): void
    {
        $persona = Persona::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('seleccionarPersona', $persona->id)
            ->set('fecha_ingreso', '')
            ->call('asignar')
            ->assertHasErrors(['cargo_id' => 'required', 'fecha_ingreso' => 'required']);
    }

    public function test_la_fecha_de_ingreso_no_puede_ser_futura(): void
    {
        $persona = Persona::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('seleccionarPersona', $persona->id)
            ->set('cargo_id', (string) $this->cargo->id)
            ->set('fecha_ingreso', now()->addWeek()->format('Y-m-d'))
            ->assertHasErrors(['fecha_ingreso' => 'before_or_equal']);
    }

    // ---- Camino 2: la persona no existe ----------------------------------

    public function test_el_termino_buscado_se_reaprovecha_en_el_formulario(): void
    {
        // Si son solo números, es un carnet; si no, el nombre.
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('buscarPersona', '9090909')
            ->call('irARegistrarPersona')
            ->assertSet('paso', 'nueva')
            ->assertSet('carnet', '9090909')
            ->assertSet('nombres', '');

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->set('buscarPersona', 'Marcelo')
            ->call('irARegistrarPersona')
            ->assertSet('nombres', 'Marcelo')
            ->assertSet('carnet', '');
    }

    public function test_registra_persona_y_ficha_laboral_de_una_sola_vez(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirCrear')
            ->call('irARegistrarPersona')
            ->set('carnet', '9090909')
            ->set('nombres', 'Marcelo')
            ->set('apellido_paterno', 'Céspedes')
            ->set('apellido_materno', 'Vargas')
            ->set('cargo_id', (string) $this->cargo->id)
            ->set('fecha_ingreso', now()->format('Y-m-d'))
            ->assertSet('formularioValido', true)
            ->call('registrarPersonaYAsignar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success');

        $this->assertDatabaseHas('personas', ['carnet' => '9090909', 'nombres' => 'Marcelo']);
        $this->assertDatabaseHas('trabajadores', ['codigo' => 'COD-0001', 'cargo_id' => $this->cargo->id]);
    }

    public function test_el_alta_conjunta_valida_los_datos_de_la_persona(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('irARegistrarPersona')
            ->set('carnet', '12')
            ->assertHasErrors(['carnet' => 'regex'])
            ->set('nombres', 'Juan3')
            ->assertHasErrors(['nombres' => 'regex']);
    }

    public function test_no_deja_registrar_una_persona_con_carnet_repetido(): void
    {
        Persona::factory()->create(['carnet' => '9090909']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('irARegistrarPersona')
            ->set('carnet', '9090909')
            ->set('nombres', 'Marcelo')
            ->set('apellido_paterno', 'Céspedes')
            ->set('cargo_id', (string) $this->cargo->id)
            ->set('fecha_ingreso', now()->format('Y-m-d'))
            ->call('registrarPersonaYAsignar')
            ->assertHasErrors(['carnet' => 'unique']);

        $this->assertSame(0, Trabajador::count());
    }

    public function test_si_falla_la_ficha_no_queda_la_persona_suelta(): void
    {
        // Cargo inexistente: la validación lo rechaza y no debe crearse nada.
        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('irARegistrarPersona')
            ->set('carnet', '9090909')
            ->set('nombres', 'Marcelo')
            ->set('apellido_paterno', 'Céspedes')
            ->set('cargo_id', '99999')
            ->set('fecha_ingreso', now()->format('Y-m-d'))
            ->call('registrarPersonaYAsignar')
            ->assertHasErrors(['cargo_id' => 'exists']);

        $this->assertDatabaseMissing('personas', ['carnet' => '9090909']);
    }

    // ---- Edición y baja ---------------------------------------------------

    public function test_edita_solo_el_cargo_y_la_fecha(): void
    {
        $trabajador = Trabajador::factory()->create([
            'cargo_id' => $this->cargo->id,
            'codigo' => 'COD-0001',
        ]);
        $otroCargo = Cargo::factory()->create(['nombre' => 'Cajero']);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('abrirEditar', $trabajador->id)
            ->assertSet('paso', 'editar')
            ->assertSet('cargo_id', (string) $this->cargo->id)
            ->set('cargo_id', (string) $otroCargo->id)
            ->call('guardarEdicion')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success');

        $trabajador->refresh();

        $this->assertSame($otroCargo->id, $trabajador->cargo_id);
        // El código es la identidad del trabajador: no cambia al editar.
        $this->assertSame('COD-0001', $trabajador->codigo);
    }

    public function test_dar_de_baja_no_borra_la_ficha_solo_la_marca(): void
    {
        // El histórico de ventas y compras seguirá apuntando a esta ficha:
        // borrarla lo dejaría huérfano.
        $trabajador = Trabajador::factory()->create(['cargo_id' => $this->cargo->id]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarBaja', $trabajador->id)
            ->set('motivo_baja', 'Renuncia voluntaria')
            ->call('darDeBaja')
            ->assertDispatched('toast', tipo: 'success');

        $trabajador->refresh();

        $this->assertNotSoftDeleted('trabajadores', ['id' => $trabajador->id]);
        $this->assertDatabaseHas('trabajadores', [
            'id' => $trabajador->id,
            'motivo_baja' => 'Renuncia voluntaria',
        ]);
        $this->assertSame(now()->toDateString(), $trabajador->fecha_baja->toDateString());
        $this->assertFalse($trabajador->esta_activo);
    }

    public function test_el_listado_filtra_por_estado(): void
    {
        Trabajador::factory()->create(['cargo_id' => $this->cargo->id]);
        Trabajador::factory()->create([
            'cargo_id' => $this->cargo->id,
            'fecha_baja' => now()->subMonth()->toDateString(),
        ]);

        $componente = Livewire::actingAs($this->admin())->test(Index::class);

        // Por defecto solo se ve el personal vigente...
        $componente->assertViewHas('trabajadores', fn ($t) => $t->total() === 1);
        // ...pero los dados de baja siguen consultables.
        $componente->set('filtroEstado', 'baja')
            ->assertViewHas('trabajadores', fn ($t) => $t->total() === 1);
        $componente->set('filtroEstado', 'todos')
            ->assertViewHas('trabajadores', fn ($t) => $t->total() === 2);
    }

    public function test_se_puede_reincorporar_a_un_trabajador_conservando_su_codigo(): void
    {
        $trabajador = Trabajador::factory()->create([
            'cargo_id' => $this->cargo->id,
            'codigo' => 'COD-0007',
            'fecha_baja' => now()->subYear()->toDateString(),
            'motivo_baja' => 'Fin de contrato',
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('reactivar', $trabajador->id)
            ->assertDispatched('toast', tipo: 'success');

        $trabajador->refresh();

        $this->assertTrue($trabajador->esta_activo);
        $this->assertNull($trabajador->motivo_baja);
        $this->assertSame('COD-0007', $trabajador->codigo);
    }

    public function test_una_persona_dada_de_baja_no_genera_una_ficha_nueva(): void
    {
        $persona = Persona::factory()->create();
        Trabajador::factory()->create([
            'persona_id' => $persona->id,
            'cargo_id' => $this->cargo->id,
            'fecha_baja' => now()->subMonth()->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('seleccionarPersona', $persona->id)
            ->assertDispatched('toast', tipo: 'error')
            ->assertSet('paso', 'buscar');

        $this->assertSame(1, Trabajador::where('persona_id', $persona->id)->count());
    }

    // ---- Cuenta de acceso -------------------------------------------------

    public function test_dar_de_baja_bloquea_la_cuenta_del_trabajador(): void
    {
        $persona = Persona::factory()->create();
        $cuenta = User::factory()->create(['persona_id' => $persona->id, 'is_active' => true]);
        $trabajador = Trabajador::factory()->create([
            'persona_id' => $persona->id,
            'cargo_id' => $this->cargo->id,
            'fecha_baja' => null,
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarBaja', $trabajador->id)
            ->call('darDeBaja')
            ->assertDispatched('toast', tipo: 'success');

        $this->assertFalse($cuenta->fresh()->is_active);
        $this->assertNotNull($trabajador->fresh()->fecha_baja);
        // La cuenta se bloquea, no se borra: el histórico apunta a ella.
        $this->assertNotNull(User::find($cuenta->id));
    }

    public function test_reincorporar_reactiva_la_cuenta(): void
    {
        $persona = Persona::factory()->create();
        $cuenta = User::factory()->create(['persona_id' => $persona->id, 'is_active' => false]);
        $trabajador = Trabajador::factory()->create([
            'persona_id' => $persona->id,
            'cargo_id' => $this->cargo->id,
            'fecha_baja' => now()->subMonth()->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('reactivar', $trabajador->id);

        $this->assertTrue($cuenta->fresh()->is_active);
        $this->assertNull($trabajador->fresh()->fecha_baja);
    }

    public function test_no_puedes_darte_de_baja_a_ti_mismo(): void
    {
        $persona = Persona::factory()->create();
        $yo = User::factory()->create(['persona_id' => $persona->id]);
        $yo->syncRoles('admin');

        $trabajador = Trabajador::factory()->create([
            'persona_id' => $persona->id,
            'cargo_id' => $this->cargo->id,
            'fecha_baja' => null,
        ]);

        Livewire::actingAs($yo)
            ->test(Index::class)
            ->call('confirmarBaja', $trabajador->id)
            ->call('darDeBaja')
            ->assertDispatched('toast', tipo: 'error');

        $this->assertNull($trabajador->fresh()->fecha_baja);
        $this->assertTrue($yo->fresh()->is_active);
    }

    public function test_crear_cuenta_usa_la_convencion_de_usuario_y_carnet(): void
    {
        $persona = Persona::factory()->create([
            'carnet' => '8123456',
            'nombres' => 'Juan Carlos',
            'apellido_paterno' => 'Peña',
            'apellido_materno' => 'Ríos',
            'correo' => null,
        ]);
        $trabajador = Trabajador::factory()->create([
            'persona_id' => $persona->id,
            'cargo_id' => $this->cargo->id,
            'fecha_baja' => null,
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarCrearCuenta', $trabajador->id)
            ->assertSet('cuentaUsuario', 'jpenarios')
            ->assertSet('cuentaPassword', '8123456')
            ->call('crearCuenta')
            ->assertDispatched('toast', tipo: 'success');

        $cuenta = $persona->fresh()->user;

        $this->assertNotNull($cuenta);
        $this->assertSame('jpenarios', $cuenta->name);
        $this->assertTrue(Hash::check('8123456', $cuenta->password));
        $this->assertTrue($cuenta->hasRole('vendedor'));
    }

    public function test_reiniciar_password_devuelve_la_clave_al_carnet(): void
    {
        $persona = Persona::factory()->create(['carnet' => '7654321']);
        $cuenta = User::factory()->create([
            'persona_id' => $persona->id,
            'password' => 'otra-cosa-distinta',
        ]);
        $trabajador = Trabajador::factory()->create([
            'persona_id' => $persona->id,
            'cargo_id' => $this->cargo->id,
            'fecha_baja' => null,
        ]);

        $correoAntes = $cuenta->email;

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarReiniciarPassword', $trabajador->id)
            ->call('reiniciarPassword')
            ->assertDispatched('toast', tipo: 'success');

        $cuenta = $cuenta->fresh();

        $this->assertTrue(Hash::check('7654321', $cuenta->password));
        // El correo es la credencial con la que ya entra: no se toca.
        $this->assertSame($correoAntes, $cuenta->email);
    }

    public function test_no_se_crean_cuentas_para_trabajadores_dados_de_baja(): void
    {
        $persona = Persona::factory()->create();
        $trabajador = Trabajador::factory()->create([
            'persona_id' => $persona->id,
            'cargo_id' => $this->cargo->id,
            'fecha_baja' => now()->subMonth()->toDateString(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(Index::class)
            ->call('confirmarCrearCuenta', $trabajador->id)
            ->assertDispatched('toast', tipo: 'error');

        $this->assertNull($persona->fresh()->user);
    }

    // ---- Permisos ---------------------------------------------------------

    public function test_un_vendedor_no_puede_registrar_trabajadores(): void
    {
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        $this->actingAs($vendedor)->get('/trabajadores')->assertForbidden();

        Livewire::actingAs($vendedor)
            ->test(Index::class)
            ->call('abrirCrear')
            ->assertForbidden();
    }
}
