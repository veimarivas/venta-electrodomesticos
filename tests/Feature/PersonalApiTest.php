<?php

namespace Tests\Feature;

use App\Models\Cargo;
use App\Models\Cliente;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\Trabajador;
use App\Models\Unidad;
use App\Models\User;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API de personal y clientes que consume la app.
 */
class PersonalApiTest extends TestCase
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

    /** Venta completada de un cliente, con el vendedor indicado. */
    private function venderA(?Cliente $cliente, User $vendedor, float $precio = 1500): void
    {
        $unidad = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create(['precio_venta' => $precio])->id,
            'estado' => 'en_stock',
            'costo_unitario' => $precio / 2,
            'precio_venta' => $precio,
        ]);

        app(RegistroDeVenta::class)->registrar(
            [['unidad_id' => $unidad->id, 'precio_unitario' => (string) $precio, 'descuento' => '0']],
            ['cliente_id' => $cliente?->id],
            $vendedor->id,
        );
    }

    // ---- Cargos ------------------------------------------------------------

    public function test_los_cargos_separan_los_vigentes_de_las_bajas(): void
    {
        $cargo = Cargo::factory()->create(['nombre' => 'Vendedor']);

        Trabajador::factory()->create(['cargo_id' => $cargo->id]);
        Trabajador::factory()->create([
            'cargo_id' => $cargo->id,
            'fecha_baja' => now()->subMonth()->toDateString(),
        ]);

        Sanctum::actingAs($this->admin());

        $fila = $this->getJson('/api/v1/personal/cargos')->assertOk()->json('data.0');

        $this->assertSame('Vendedor', $fila['nombre']);
        $this->assertSame(2, $fila['trabajadores']);
        // Un cargo con dos fichas de las que una es una baja no está ocupado
        // por dos personas.
        $this->assertSame(1, $fila['activos']);
        $this->assertSame(1, $fila['dados_de_baja']);
    }

    // ---- Trabajadores ------------------------------------------------------

    public function test_el_listado_muestra_solo_a_los_activos_por_defecto(): void
    {
        Trabajador::factory()->create(['codigo' => 'COD-0001']);
        Trabajador::factory()->create([
            'codigo' => 'COD-0002',
            'fecha_baja' => now()->subDay()->toDateString(),
        ]);

        Sanctum::actingAs($this->admin());

        $activos = $this->getJson('/api/v1/personal/trabajadores')->assertOk()->json('data');

        $this->assertCount(1, $activos);
        $this->assertSame('COD-0001', $activos[0]['codigo']);
        $this->assertTrue($activos[0]['esta_activo']);

        // La baja es un estado, no un borrado: su ficha sigue consultable.
        $bajas = $this->getJson('/api/v1/personal/trabajadores?estado=bajas')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $bajas);
        $this->assertSame('COD-0002', $bajas[0]['codigo']);
        $this->assertFalse($bajas[0]['esta_activo']);
        $this->assertNotNull($bajas[0]['fecha_baja']);
    }

    public function test_el_listado_busca_por_codigo_nombre_y_cargo(): void
    {
        $cargo = Cargo::factory()->create(['nombre' => 'Técnico']);
        $persona = Persona::factory()->create(['nombres' => 'Rosario']);

        Trabajador::factory()->create([
            'codigo' => 'COD-0007',
            'cargo_id' => $cargo->id,
            'persona_id' => $persona->id,
        ]);
        Trabajador::factory()->create(['codigo' => 'COD-0008']);

        Sanctum::actingAs($this->admin());

        foreach (['COD-0007', 'Rosario', 'Técnico'] as $termino) {
            $datos = $this->getJson('/api/v1/personal/trabajadores?buscar='.urlencode($termino))
                ->assertOk()
                ->json('data');

            $this->assertCount(1, $datos, "La búsqueda «{$termino}» no acotó el listado.");
            $this->assertSame('COD-0007', $datos[0]['codigo']);
        }
    }

    public function test_la_ficha_trae_su_cuenta_y_lo_que_ha_vendido(): void
    {
        $persona = Persona::factory()->create();
        $trabajador = Trabajador::factory()->create(['persona_id' => $persona->id]);

        $cuenta = User::factory()->create([
            'name' => 'rquispe',
            'persona_id' => $persona->id,
            'is_active' => true,
        ]);
        $cuenta->syncRoles('vendedor');

        $this->venderA(null, $cuenta, 1500);
        $this->venderA(null, $cuenta, 500);

        Sanctum::actingAs($this->admin());

        $ficha = $this->getJson("/api/v1/personal/trabajadores/{$trabajador->id}")
            ->assertOk()
            ->json('data');

        $this->assertSame('rquispe', $ficha['cuenta']['usuario']);
        $this->assertSame(['vendedor'], $ficha['cuenta']['roles']);
        $this->assertSame(2, $ficha['ventas']['total']);
        $this->assertEquals(2000, $ficha['ventas']['importe']);
        $this->assertNotNull($ficha['persona']['nombre_completo']);
    }

    public function test_la_ficha_de_quien_no_tiene_cuenta_no_le_atribuye_ventas(): void
    {
        $trabajador = Trabajador::factory()->create();

        // Otro usuario vendió: sin cuenta propia, no puede ser suyo.
        $this->venderA(null, $this->admin());

        Sanctum::actingAs($this->admin());

        $ficha = $this->getJson("/api/v1/personal/trabajadores/{$trabajador->id}")
            ->assertOk()
            ->json('data');

        $this->assertNull($ficha['cuenta']);
        $this->assertSame(0, $ficha['ventas']['total']);
    }

    public function test_el_personal_exige_su_permiso(): void
    {
        // Un vendedor puede ver clientes, no fichas laborales de compañeros.
        Sanctum::actingAs(User::factory()->create(['is_active' => true])->syncRoles('vendedor'));

        $this->getJson('/api/v1/personal/trabajadores')->assertForbidden();
        $this->getJson('/api/v1/personal/cargos')->assertForbidden();
        $this->getJson('/api/v1/clientes')->assertOk();
    }

    // ---- Clientes ----------------------------------------------------------

    public function test_el_listado_de_clientes_resume_sus_compras(): void
    {
        $cliente = Cliente::factory()->create(['codigo' => 'CLI-0001']);
        $vendedor = $this->admin();

        $this->venderA($cliente, $vendedor, 1200);
        $this->venderA($cliente, $vendedor, 800);

        Sanctum::actingAs($vendedor);

        $fila = $this->getJson('/api/v1/clientes')->assertOk()->json('data.0');

        $this->assertSame('CLI-0001', $fila['codigo']);
        $this->assertSame(2, $fila['compras']['total']);
        $this->assertEquals(2000, $fila['compras']['importe']);
        $this->assertNotNull($fila['compras']['ultima']);
    }

    public function test_una_venta_anulada_no_cuenta_como_compra(): void
    {
        $cliente = Cliente::factory()->create();
        $vendedor = $this->admin();

        $this->venderA($cliente, $vendedor, 1000);

        app(RegistroDeVenta::class)->anular(
            $cliente->ventas()->firstOrFail(),
            'El cliente devolvió el aparato.'
        );

        Sanctum::actingAs($vendedor);

        $fila = $this->getJson('/api/v1/clientes')->assertOk()->json('data.0');

        $this->assertSame(0, $fila['compras']['total']);
        $this->assertEquals(0, $fila['compras']['importe']);
    }

    public function test_la_ficha_del_cliente_trae_sus_ultimas_compras(): void
    {
        $cliente = Cliente::factory()->create();
        $vendedor = $this->admin();

        $this->venderA($cliente, $vendedor, 900);

        Sanctum::actingAs($vendedor);

        $ficha = $this->getJson("/api/v1/clientes/{$cliente->id}")->assertOk()->json('data');

        $this->assertCount(1, $ficha['ultimas_ventas']);
        $this->assertEquals(900, $ficha['ultimas_ventas'][0]['total']);
    }

    public function test_un_cliente_archivado_conserva_su_ficha(): void
    {
        $cliente = Cliente::factory()->create();
        $cliente->delete();

        Sanctum::actingAs($this->admin());

        // Fuera del listado activo...
        $this->assertCount(0, $this->getJson('/api/v1/clientes')->assertOk()->json('data'));

        // ...pero su ficha se abre igual: su historial sigue apuntando aquí.
        $ficha = $this->getJson("/api/v1/clientes/{$cliente->id}")->assertOk()->json('data');

        $this->assertTrue($ficha['archivado']);
    }

    public function test_sin_permiso_de_ventas_no_viaja_cuanto_ha_gastado(): void
    {
        $cliente = Cliente::factory()->create();
        $this->venderA($cliente, $this->admin(), 1000);

        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->syncPermissions(['clientes.ver']);

        Sanctum::actingAs($usuario);

        $fila = $this->getJson('/api/v1/clientes')->assertOk()->json('data.0');

        // Cuánto ha gastado alguien es información de ventas, no de su ficha.
        $this->assertArrayNotHasKey('compras', $fila);
        $this->assertNotNull($fila['persona']['carnet']);
    }
}
