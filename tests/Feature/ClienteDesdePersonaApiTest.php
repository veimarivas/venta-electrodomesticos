<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Persona;
use App\Models\User;
use App\Support\GeneradorCodigoCliente;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Segundo peldaño del buscador de cliente del POS: gente que ya está en
 * `personas` pero todavía no tiene ficha de cliente.
 *
 * Sin esto, el mostrador teclea otra vez el carnet de alguien que ya existe,
 * el índice único lo rechaza y la venta se atasca con el cliente delante.
 */
class ClienteDesdePersonaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function vendedor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    private function clienteDe(Persona $persona): Cliente
    {
        return app(GeneradorCodigoCliente::class)->crearCon(['persona_id' => $persona->id]);
    }

    // ---- Búsqueda de personas sin ficha ------------------------------------

    public function test_encuentra_personas_que_todavia_no_son_clientes(): void
    {
        $persona = Persona::factory()->create(['nombres' => 'Marisol']);

        Sanctum::actingAs($this->vendedor());

        $this->getJson('/api/v1/personas/sin-ficha?termino=Marisol')
            ->assertOk()
            ->assertJsonPath('data.0.id', $persona->id)
            ->assertJsonPath('data.0.carnet', $persona->carnet);
    }

    public function test_quien_ya_es_cliente_no_sale_en_ese_listado(): void
    {
        $persona = Persona::factory()->create(['nombres' => 'Marisol']);
        $this->clienteDe($persona);

        Sanctum::actingAs($this->vendedor());

        // Sale por el buscador normal de clientes; repetirla aquí ofrecería
        // «registrarla» a alguien que ya está registrado.
        $this->getJson('/api/v1/personas/sin-ficha?termino=Marisol')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ---- Alta desde una persona --------------------------------------------

    public function test_crea_la_ficha_de_cliente_sin_volver_a_pedir_los_datos(): void
    {
        $persona = Persona::factory()->create();

        Sanctum::actingAs($this->vendedor());

        $respuesta = $this->postJson('/api/v1/clientes/desde-persona', [
            'persona_id' => $persona->id,
        ])->assertCreated();

        $this->assertSame($persona->id, $respuesta->json('data.persona.id'));
        $this->assertDatabaseHas('clientes', ['persona_id' => $persona->id]);
        // No se duplicó a la persona: es la misma fila.
        $this->assertSame(1, Persona::where('carnet', $persona->carnet)->count());
    }

    public function test_una_ficha_archivada_se_restaura_en_vez_de_crear_otra(): void
    {
        $persona = Persona::factory()->create();
        $cliente = $this->clienteDe($persona);
        $codigo = $cliente->codigo;

        $cliente->delete();

        Sanctum::actingAs($this->vendedor());

        // Crear otra rompería el índice único de `persona_id`, y además la
        // ficha vieja conserva su código y su historial de compras.
        $respuesta = $this->postJson('/api/v1/clientes/desde-persona', [
            'persona_id' => $persona->id,
        ])->assertOk();

        $this->assertSame($codigo, $respuesta->json('data.codigo'));
        $this->assertSame(1, Cliente::withTrashed()->where('persona_id', $persona->id)->count());
        $this->assertNull($cliente->fresh()->deleted_at);
    }

    public function test_si_ya_tenia_ficha_devuelve_la_suya_y_no_falla(): void
    {
        $persona = Persona::factory()->create();
        $cliente = $this->clienteDe($persona);

        Sanctum::actingAs($this->vendedor());

        // Pudo crearse desde el panel mientras la pantalla estaba abierta.
        $this->postJson('/api/v1/clientes/desde-persona', [
            'persona_id' => $persona->id,
        ])->assertOk()->assertJsonPath('data.codigo', $cliente->codigo);

        $this->assertSame(1, Cliente::where('persona_id', $persona->id)->count());
    }

    public function test_sin_permiso_de_crear_clientes_no_se_puede(): void
    {
        $persona = Persona::factory()->create();

        // El rol de vendedor sí puede; se le quita el permiso para comprobar
        // que la puerta es el permiso y no el rol.
        $usuario = $this->vendedor();
        $usuario->revokePermissionTo('clientes.crear');
        $usuario->roles()->first()->revokePermissionTo('clientes.crear');

        Sanctum::actingAs($usuario->fresh());

        $this->postJson('/api/v1/clientes/desde-persona', [
            'persona_id' => $persona->id,
        ])->assertForbidden();

        $this->getJson('/api/v1/personas/sin-ficha?termino=aa')->assertForbidden();
    }
}
