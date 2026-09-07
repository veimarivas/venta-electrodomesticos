<?php

namespace Tests\Feature;

use App\Livewire\Compras\Pagos;
use App\Models\Compra;
use App\Models\PagoCompra;
use App\Models\Proveedor;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PagosComprasPanelTest extends TestCase
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

    private function pagoDe(string $proveedor, float $monto, ?string $fecha = null): PagoCompra
    {
        $compra = Compra::factory()->create([
            'proveedor_id' => Proveedor::factory()->create(['nombre' => $proveedor])->id,
            'estado' => 'recepcionada',
        ]);

        return $compra->pagos()->create([
            'user_id' => $this->admin()->id,
            'monto' => $monto,
            'fecha' => $fecha ?? now()->toDateString(),
        ]);
    }

    public function test_el_panel_lista_los_pagos_del_rango(): void
    {
        $hoy = $this->pagoDe('Alfa', 1500);
        $anterior = $this->pagoDe('Beta', 2000, now()->subMonths(2)->toDateString());

        $componente = Livewire::actingAs($this->admin())
            ->test(Pagos::class)
            ->assertSet('filtro', 'mes')
            ->assertViewHas('pagos', fn ($pagos) => $pagos->pluck('id')->all() === [$hoy->id]);

        // El total del mes suma solo el de hoy.
        $this->assertSame('1500.00', $componente->get('total'));
        $this->assertSame(1, $componente->get('cantidad'));

        // Sin filtro, los dos.
        $componente->set('filtro', 'todas')
            ->assertViewHas('pagos', fn ($pagos) => $pagos->count() === 2);
        $this->assertSame('3500.00', $componente->get('total'));
        $this->assertSame(2, $componente->get('cantidad'));
    }

    public function test_la_pagina_exige_permiso_de_compras(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)->get('/compras/pagos')->assertForbidden();
    }
}