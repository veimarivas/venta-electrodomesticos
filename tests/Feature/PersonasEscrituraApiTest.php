<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Cliente;
use App\Models\Persona;
use App\Models\Trabajador;
use App\Models\User;
use App\Support\GeneradorCodigoCliente;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Alta, edición y baja de cargos, trabajadores y clientes desde la app.
 *
 * Lo que más se comprueba aquí no es el camino feliz sino las **guardas**: un
 * cargo con historial no se puede borrar, un trabajador no se borra sino que se
 * da de baja, y nadie puede darse de baja a sí mismo.
 */
class PersonasEscrituraApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('admin');
    }

    // ---- Cargos -------------------------------------------------------------

    public function test_crea_y_edita_un_cargo(): void
    {
        Sanctum::actingAs($this->admin());

        $respuesta = $this->postJson('/api/v1/personal/cargos', [
            'nombre' => 'Técnico de instalación',
        ])->assertCreated();

        $id = $respuesta->json('data.id');

        $this->postJson("/api/v1/personal/cargos/{$id}", [
            'nombre' => 'Técnico de instalación y soporte',
        ])->assertOk();

        $this->assertDatabaseHas('cargos', ['nombre' => 'Técnico de instalación y soporte']);
    }

    public function test_no_se_repite_el_nombre_de_un_cargo(): void
    {
        Cargo::factory()->create(['nombre' => 'Vendedor']);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/personal/cargos', ['nombre' => 'Vendedor'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    public function test_no_se_elimina_un_cargo_que_tuvo_trabajadores_aunque_esten_de_baja(): void
    {
        $cargo = Cargo::factory()->create();
        $trabajador = Trabajador::factory()->create(['cargo_id' => $cargo->id]);

        // Dado de baja: el cargo ya no tiene personal vigente, pero la ficha
        // histórica sigue apuntando a él y la FK es restrictOnDelete.
        $trabajador->delete();

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/personal/cargos/{$cargo->id}")->assertStatus(422);

        $this->assertDatabaseHas('cargos', ['id' => $cargo->id]);
    }

    public function test_elimina_un_cargo_que_nunca_tuvo_a_nadie(): void
    {
        $cargo = Cargo::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/personal/cargos/{$cargo->id}")->assertOk();

        $this->assertDatabaseMissing('cargos', ['id' => $cargo->id]);
    }

    // ---- Trabajadores -------------------------------------------------------

    public function test_da_de_alta_a_alguien_que_ya_esta_en_personas(): void
    {
        $persona = Persona::factory()->create();
        $cargo = Cargo::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/personal/trabajadores', [
            'persona_id' => $persona->id,
            'cargo_id' => $cargo->id,
            'fecha_ingreso' => now()->subYear()->toDateString(),
        ])->assertCreated();

        // No se duplicó a la persona: repetir su carnet lo rechazaría el índice.
        $this->assertSame(1, Persona::where('carnet', $persona->carnet)->count());
        $this->assertDatabaseHas('trabajadores', ['persona_id' => $persona->id]);
    }

    public function test_registra_persona_y_ficha_laboral_de_una_vez(): void
    {
        $cargo = Cargo::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/personal/trabajadores', [
            'carnet' => '9876543',
            'nombres' => 'Rosa María',
            'apellido_paterno' => 'Quispe',
            'cargo_id' => $cargo->id,
            'fecha_ingreso' => now()->subMonths(3)->toDateString(),
        ])->assertCreated();

        $this->assertDatabaseHas('personas', ['carnet' => '9876543']);
        $this->assertSame(1, Trabajador::count());
    }

    public function test_no_se_registra_dos_veces_como_trabajador_a_la_misma_persona(): void
    {
        $trabajador = Trabajador::factory()->create();
        $cargo = Cargo::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/personal/trabajadores', [
            'persona_id' => $trabajador->persona_id,
            'cargo_id' => $cargo->id,
            'fecha_ingreso' => now()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('persona_id');
    }

    public function test_la_fecha_de_ingreso_no_puede_ser_futura(): void
    {
        $persona = Persona::factory()->create();
        $cargo = Cargo::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/personal/trabajadores', [
            'persona_id' => $persona->id,
            'cargo_id' => $cargo->id,
            'fecha_ingreso' => now()->addDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('fecha_ingreso');
    }

    public function test_la_baja_cierra_la_ficha_y_desactiva_su_cuenta(): void
    {
        $trabajador = Trabajador::factory()->create();
        $cuenta = User::factory()->create([
            'persona_id' => $trabajador->persona_id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/personal/trabajadores/{$trabajador->id}/baja", [
            'motivo' => 'Renuncia voluntaria',
        ])->assertOk();

        // Ficha y cuenta se cierran juntas: alguien dado de baja que sigue
        // pudiendo entrar es justo lo que la baja pretende evitar.
        $this->assertNotNull($trabajador->fresh()->fecha_baja);
        $this->assertSame('Renuncia voluntaria', $trabajador->fresh()->motivo_baja);
        $this->assertFalse((bool) $cuenta->fresh()->is_active);
    }

    public function test_nadie_puede_darse_de_baja_a_si_mismo(): void
    {
        $trabajador = Trabajador::factory()->create();
        $yo = User::factory()->create([
            'persona_id' => $trabajador->persona_id,
            'is_active' => true,
        ]);
        $yo->syncRoles('admin');

        Sanctum::actingAs($yo);

        // Cerraría la sesión en el acto y dejaría al administrador fuera a
        // mitad de la operación.
        $this->postJson("/api/v1/personal/trabajadores/{$trabajador->id}/baja")
            ->assertStatus(422);

        $this->assertNull($trabajador->fresh()->fecha_baja);
        $this->assertTrue((bool) $yo->fresh()->is_active);
    }

    public function test_reincorporar_reactiva_su_cuenta(): void
    {
        $trabajador = Trabajador::factory()->create([
            'fecha_baja' => now()->subMonth()->toDateString(),
        ]);
        $cuenta = User::factory()->create([
            'persona_id' => $trabajador->persona_id,
            'is_active' => false,
        ]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/personal/trabajadores/{$trabajador->id}/reactivar")
            ->assertOk();

        // Simétrico a la baja: sin esto habría que acordarse de reactivar la
        // cuenta a mano desde el módulo de usuarios.
        $this->assertNull($trabajador->fresh()->fecha_baja);
        $this->assertTrue((bool) $cuenta->fresh()->is_active);
    }

    // ---- Personas -----------------------------------------------------------

    public function test_edita_los_datos_de_una_persona(): void
    {
        $persona = Persona::factory()->create(['celular' => '70000000']);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/personas/{$persona->id}", [
            'carnet' => $persona->carnet,
            'nombres' => $persona->nombres,
            'apellido_paterno' => $persona->apellido_paterno ?? 'Quispe',
            'celular' => '71234567',
        ])->assertOk();

        $this->assertSame('71234567', $persona->fresh()->celular);
    }

    public function test_editar_una_persona_no_choca_con_su_propio_carnet(): void
    {
        $persona = Persona::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/personas/{$persona->id}", [
            'carnet' => $persona->carnet,
            'nombres' => 'Nombre corregido',
            'apellido_paterno' => 'Apellido',
        ])->assertOk();

        $this->assertSame('Nombre corregido', $persona->fresh()->nombres);
    }

    public function test_un_correo_vacio_se_guarda_como_nulo(): void
    {
        Persona::factory()->create(['correo' => null]);
        $persona = Persona::factory()->create(['correo' => 'algo@test.com']);

        Sanctum::actingAs($this->admin());

        // Cadena vacía en una columna única bloquearía a la segunda persona
        // sin correo.
        $this->postJson("/api/v1/personas/{$persona->id}", [
            'carnet' => $persona->carnet,
            'nombres' => $persona->nombres,
            'apellido_paterno' => $persona->apellido_paterno ?? 'Quispe',
            'correo' => '',
        ])->assertOk();

        $this->assertNull($persona->fresh()->correo);
    }

    // ---- Clientes -----------------------------------------------------------

    public function test_archivar_un_cliente_conserva_su_historial(): void
    {
        $cliente = app(GeneradorCodigoCliente::class)
            ->crearCon(['persona_id' => Persona::factory()->create()->id]);

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/clientes/{$cliente->id}")->assertOk();

        // Archivado, no borrado: las ventas que hizo siguen apuntando aquí.
        $this->assertSoftDeleted('clientes', ['id' => $cliente->id]);
    }

    public function test_restaurar_un_cliente_le_devuelve_su_codigo(): void
    {
        $cliente = app(GeneradorCodigoCliente::class)
            ->crearCon(['persona_id' => Persona::factory()->create()->id]);
        $codigo = $cliente->codigo;

        $cliente->delete();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/clientes/{$cliente->id}/restaurar")
            ->assertOk()
            ->assertJsonPath('data.codigo', $codigo);

        $this->assertNull($cliente->fresh()->deleted_at);
        $this->assertSame(1, Cliente::withTrashed()->count());
    }

    // ---- Permisos -----------------------------------------------------------

    public function test_un_vendedor_no_puede_administrar_personal(): void
    {
        $cargo = Cargo::factory()->create();
        $trabajador = Trabajador::factory()->create();
        $persona = Persona::factory()->create();

        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('vendedor'));

        $this->postJson('/api/v1/personal/cargos', ['nombre' => 'Nuevo'])->assertForbidden();
        $this->deleteJson("/api/v1/personal/cargos/{$cargo->id}")->assertForbidden();
        $this->postJson('/api/v1/personal/trabajadores', ['nombre' => 'X'])->assertForbidden();
        $this->postJson("/api/v1/personal/trabajadores/{$trabajador->id}/baja")->assertForbidden();
        $this->postJson("/api/v1/personas/{$persona->id}", ['nombres' => 'X'])->assertForbidden();
    }
}
