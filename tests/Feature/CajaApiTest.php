<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La caja del turno desde la app.
 *
 * Abrir, mover efectivo y cerrar ocurren detrás del mostrador. El cajero no ve
 * el importe esperado —se le pide contar, no comparar—; quien supervisa sí.
 */
class CajaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function cajero(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    private function supervisor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('supervisor');
    }

    public function test_el_cajero_abre_la_caja(): void
    {
        $respuesta = $this->actingAs($this->cajero())
            ->postJson('/api/v1/caja/abrir', ['monto_inicial' => 200]);

        $respuesta->assertOk()
            ->assertJsonPath('message', 'Caja abierta.')
            ->assertJsonPath('data.abierta.monto_inicial', fn ($v) => (float) $v === 200.0)
            ->assertJsonPath('data.puede_gestionar', true);

        $this->assertSame(1, Caja::abiertas()->count());
    }

    public function test_no_se_abren_dos_cajas(): void
    {
        $cajero = $this->cajero();
        $this->actingAs($cajero)->postJson('/api/v1/caja/abrir', ['monto_inicial' => 100])->assertOk();

        $this->actingAs($cajero)
            ->postJson('/api/v1/caja/abrir', ['monto_inicial' => 100])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Ya hay una caja abierta. Ciérrala antes de abrir otra.');
    }

    public function test_el_cajero_registra_un_retiro(): void
    {
        $cajero = $this->cajero();
        $this->actingAs($cajero)->postJson('/api/v1/caja/abrir', ['monto_inicial' => 500])->assertOk();

        $respuesta = $this->actingAs($cajero)->postJson('/api/v1/caja/movimientos', [
            'tipo' => 'retiro',
            'monto' => 150,
            'motivo' => 'Flete a la tienda',
        ]);

        $respuesta->assertOk()
            ->assertJsonPath('message', 'Retiro registrado.')
            ->assertJsonPath('data.abierta.movimientos_neto', fn ($v) => (float) $v === -150.0)
            ->assertJsonPath('data.abierta.movimientos.0.tipo', 'retiro')
            ->assertJsonPath('data.abierta.movimientos.0.motivo', 'Flete a la tienda');

        $this->assertSame(1, MovimientoCaja::count());
    }

    public function test_el_retiro_no_puede_superar_lo_que_hay(): void
    {
        $cajero = $this->cajero();
        $this->actingAs($cajero)->postJson('/api/v1/caja/abrir', ['monto_inicial' => 100])->assertOk();

        $this->actingAs($cajero)
            ->postJson('/api/v1/caja/movimientos', [
                'tipo' => 'retiro',
                'monto' => 200,
                'motivo' => 'Demasiado',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'No se puede retirar más de lo que hay en el cajón.');

        $this->assertSame(0, MovimientoCaja::count());
    }

    public function test_el_cajero_cierra_y_cuadra(): void
    {
        $cajero = $this->cajero();
        $this->actingAs($cajero)->postJson('/api/v1/caja/abrir', ['monto_inicial' => 100])->assertOk();

        $respuesta = $this->actingAs($cajero)
            ->postJson('/api/v1/caja/cerrar', ['monto_declarado' => 100]);

        $respuesta->assertOk()
            ->assertJsonPath('message', 'Caja cerrada y cuadrada.')
            ->assertJsonPath('data.abierta', null);

        $this->assertSame('0.00', Caja::first()->diferencia);
    }

    public function test_el_cajero_no_ve_lo_esperado(): void
    {
        $cajero = $this->cajero();
        $this->actingAs($cajero)->postJson('/api/v1/caja/abrir', ['monto_inicial' => 300])->assertOk();

        // Se le pide contar, no comparar: ver la cifra antes de contar la
        // convierte en la respuesta.
        $this->actingAs($cajero)
            ->getJson('/api/v1/caja')
            ->assertOk()
            ->assertJsonPath('data.abierta.esperado', null);
    }

    public function test_el_supervisor_ve_lo_esperado(): void
    {
        $cajero = $this->cajero();
        $this->actingAs($cajero)->postJson('/api/v1/caja/abrir', ['monto_inicial' => 300])->assertOk();

        $this->actingAs($this->supervisor())
            ->getJson('/api/v1/caja')
            ->assertOk()
            ->assertJsonPath('data.abierta.esperado', fn ($v) => (float) $v === 300.0);
    }

    public function test_sin_permiso_de_caja_no_se_entra(): void
    {
        $sinPermiso = User::factory()->create(['is_active' => true]);

        $this->actingAs($sinPermiso)->getJson('/api/v1/caja')->assertForbidden();
    }

    public function test_quien_solo_ve_no_puede_abrir(): void
    {
        $auditor = User::factory()->create(['is_active' => true]);
        $auditor->givePermissionTo('caja.ver');

        $this->actingAs($auditor)
            ->postJson('/api/v1/caja/abrir', ['monto_inicial' => 100])
            ->assertForbidden();

        $this->assertSame(0, Caja::count());
    }

    public function test_se_necesita_sesion(): void
    {
        $this->getJson('/api/v1/caja')->assertUnauthorized();
    }
}
