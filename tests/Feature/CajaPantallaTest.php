<?php

namespace Tests\Feature;

use App\Livewire\Caja\Index;
use App\Models\Caja;
use App\Models\User;
use App\Support\ArqueoDeCaja;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La pantalla de caja.
 *
 * Dos permisos porque son dos trabajos: `caja.gestionar` abre y cierra el
 * turno; `caja.ver` repasa el histórico de cierres de todos. El cajero tiene el
 * primero y no el segundo — los descuadres de sus compañeros no son asunto suyo.
 */
class CajaPantallaTest extends TestCase
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

    // ---- Abrir y cerrar ----------------------------------------------------

    public function test_el_cajero_abre_su_turno(): void
    {
        Livewire::actingAs($this->cajero())
            ->test(Index::class)
            ->set('montoInicial', '200')
            ->call('abrir')
            ->assertHasNoErrors()
            ->assertDispatched('cerrar-modal-abrir-caja');

        $this->assertSame(1, Caja::abiertas()->count());
        $this->assertSame('200.00', Caja::first()->monto_inicial);
    }

    public function test_el_fondo_es_obligatorio(): void
    {
        Livewire::actingAs($this->cajero())
            ->test(Index::class)
            ->set('montoInicial', '')
            ->call('abrir')
            ->assertHasErrors(['montoInicial' => 'required']);

        $this->assertSame(0, Caja::count());
    }

    public function test_cerrar_avisa_del_faltante_en_el_momento(): void
    {
        $cajero = $this->cajero();
        app(ArqueoDeCaja::class)->abrir($cajero->id, 200);

        // Un faltante tiene que verse al cerrar, no al abrir el histórico
        // mañana: es cuando todavía se puede buscar el billete.
        Livewire::actingAs($cajero)
            ->test(Index::class)
            ->set('montoDeclarado', '150')
            ->call('cerrar')
            ->assertHasNoErrors()
            ->assertDispatched(
                'toast',
                fn (string $evento, array $datos): bool => $datos['tipo'] === 'warning'
                    && str_contains($datos['mensaje'], 'Faltan')
            );

        $this->assertSame('-50.00', Caja::first()->diferencia);
    }

    public function test_cerrar_confirma_cuando_cuadra(): void
    {
        $cajero = $this->cajero();
        app(ArqueoDeCaja::class)->abrir($cajero->id, 200);

        Livewire::actingAs($cajero)
            ->test(Index::class)
            ->set('montoDeclarado', '200')
            ->call('cerrar')
            ->assertDispatched(
                'toast',
                fn (string $evento, array $datos): bool => $datos['tipo'] === 'success'
                    && str_contains($datos['mensaje'], 'cuadrada')
            );
    }

    public function test_lo_contado_es_obligatorio(): void
    {
        $cajero = $this->cajero();
        app(ArqueoDeCaja::class)->abrir($cajero->id, 100);

        Livewire::actingAs($cajero)
            ->test(Index::class)
            ->set('montoDeclarado', '')
            ->call('cerrar')
            ->assertHasErrors(['montoDeclarado' => 'required']);

        $this->assertTrue(Caja::first()->esta_abierta);
    }

    public function test_el_importe_contado_no_se_propone(): void
    {
        $cajero = $this->cajero();
        app(ArqueoDeCaja::class)->abrir($cajero->id, 500);

        // Si el sistema rellenara lo esperado, cerrar sería darle a aceptar y
        // el arqueo compararía un número consigo mismo.
        Livewire::actingAs($cajero)
            ->test(Index::class)
            ->call('confirmarCierre')
            ->assertSet('montoDeclarado', '');
    }

    // ---- Qué ve cada rol ----------------------------------------------------

    public function test_el_cajero_no_ve_el_historico_de_cierres(): void
    {
        $cajero = $this->cajero();

        $this->assertFalse($cajero->can('caja.ver'));

        Livewire::actingAs($cajero)
            ->test(Index::class)
            ->assertViewHas('cierres', null)
            ->assertDontSee('Cierres anteriores', false);
    }

    public function test_al_cajero_no_se_le_enseña_lo_esperado(): void
    {
        $cajero = $this->cajero();
        app(ArqueoDeCaja::class)->abrir($cajero->id, 300);

        // Se le pide contar, no comparar: ver la cifra antes de contar la
        // convierte en la respuesta.
        Livewire::actingAs($cajero)
            ->test(Index::class)
            ->assertViewHas('esperado', null);
    }

    public function test_el_supervisor_ve_el_historico_y_lo_esperado(): void
    {
        $supervisor = $this->supervisor();
        app(ArqueoDeCaja::class)->abrir($supervisor->id, 300);

        Livewire::actingAs($supervisor)
            ->test(Index::class)
            ->assertViewHas('esperado', '300.00')
            ->assertSee('Cierres anteriores', false);
    }

    // ---- Permisos -----------------------------------------------------------

    public function test_sin_ningun_permiso_de_caja_no_se_entra(): void
    {
        $sinPermiso = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($sinPermiso)
            ->test(Index::class)
            ->assertForbidden();
    }

    public function test_quien_solo_ve_no_puede_abrir_ni_cerrar(): void
    {
        // Un rol de solo lectura sobre caja: repasa cierres, no toca el cajón.
        $auditor = User::factory()->create(['is_active' => true]);
        $auditor->givePermissionTo('caja.ver');

        Livewire::actingAs($auditor)
            ->test(Index::class)
            ->set('montoInicial', '100')
            ->call('abrir')
            ->assertForbidden();

        $this->assertSame(0, Caja::count());
    }
}
