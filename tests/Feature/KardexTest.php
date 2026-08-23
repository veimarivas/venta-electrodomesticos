<?php

namespace Tests\Feature;

use App\Livewire\Inventario\Kardex as KardexLivewire;
use App\Livewire\Unidades\Index as UnidadesIndex;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Kardex;
use App\Support\RecepcionDeCompra;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KardexTest extends TestCase
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
     * Compra recepcionada con N unidades de un producto.
     */
    private function compraRecepcionada(int $cantidad = 3): Compra
    {
        $compra = Compra::factory()->create([
            'proveedor_id' => Proveedor::factory()->create(['activo' => true])->id,
            'estado' => 'borrador',
        ]);

        CompraDetalle::factory()->create([
            'compra_id' => $compra->id,
            'producto_id' => Producto::factory()->create(['meses_garantia' => 0])->id,
            'cantidad' => $cantidad,
            'costo_unitario' => 100,
            'subtotal' => 100 * $cantidad,
        ]);

        app(RecepcionDeCompra::class)->recepcionar($compra->fresh());

        return $compra->fresh();
    }

    // ---- Registro automático ----------------------------------------------

    public function test_la_compra_deja_una_entrada_por_cada_unidad(): void
    {
        $compra = $this->compraRecepcionada(3);

        $movimientos = MovimientoInventario::all();

        $this->assertCount(3, $movimientos);
        $this->assertTrue($movimientos->every(fn ($m) => $m->tipo === 'entrada'));
        $this->assertTrue($movimientos->every(fn ($m) => $m->estado_anterior === null));
        $this->assertTrue($movimientos->every(fn ($m) => $m->estado_nuevo === 'en_stock'));
        // El origen apunta a la compra que trajo el aparato.
        $this->assertTrue($movimientos->every(fn ($m) => $m->origen_id === $compra->id));
        $this->assertSame(Compra::class, $movimientos->first()->origen_type);
    }

    public function test_no_puede_existir_inventario_sin_su_movimiento_de_origen(): void
    {
        $this->compraRecepcionada(4);

        $unidades = Unidad::pluck('id');

        foreach ($unidades as $id) {
            $this->assertSame(
                1,
                MovimientoInventario::where('unidad_id', $id)->where('tipo', 'entrada')->count(),
                "La unidad {$id} no tiene su entrada en el kardex."
            );
        }
    }

    public function test_el_alta_manual_registra_una_entrada_sin_origen(): void
    {
        $producto = Producto::factory()->create();

        Livewire::actingAs($this->admin())
            ->test(UnidadesIndex::class)
            ->call('abrirCrear')
            ->set('productoId', $producto->id)
            ->set('costo', '900.00')
            ->set('precio', '1500.00')
            ->call('guardar')
            ->assertHasNoErrors();

        $movimiento = MovimientoInventario::first();

        $this->assertSame('entrada', $movimiento->tipo);
        // Es una regularización del stock que ya existía: no hay compra detrás.
        $this->assertNull($movimiento->origen_id);
        $this->assertSame('Alta manual de regularización', $movimiento->notas);
    }

    public function test_editar_una_unidad_registra_el_cambio_de_estado(): void
    {
        $this->compraRecepcionada(1);
        $unidad = Unidad::first();

        Livewire::actingAs($this->admin())
            ->test(UnidadesIndex::class)
            ->call('abrirEditar', $unidad->id)
            ->set('estado', 'danado')
            ->call('guardar')
            ->assertHasNoErrors();

        $movimiento = MovimientoInventario::where('unidad_id', $unidad->id)
            ->where('tipo', 'dano')
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertSame('en_stock', $movimiento->estado_anterior);
        $this->assertSame('danado', $movimiento->estado_nuevo);
    }

    public function test_editar_sin_cambiar_el_estado_no_ensucia_el_kardex(): void
    {
        // Un kardex lleno de filas que no mueven nada no se puede leer.
        $this->compraRecepcionada(1);
        $unidad = Unidad::first();

        Livewire::actingAs($this->admin())
            ->test(UnidadesIndex::class)
            ->call('abrirEditar', $unidad->id)
            ->set('ubicacion', 'Estante B')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(1, MovimientoInventario::where('unidad_id', $unidad->id)->count());
    }

    // ---- Tipos de movimiento ----------------------------------------------

    public function test_el_tipo_sale_del_estado_de_destino(): void
    {
        $this->compraRecepcionada(1);
        $unidad = Unidad::first();
        $kardex = app(Kardex::class);

        $casos = [
            'vendido' => 'salida',
            'devuelto' => 'devolucion',
            'danado' => 'dano',
            'reservado' => 'ajuste',
        ];

        foreach ($casos as $estado => $tipoEsperado) {
            $anterior = $unidad->estado;
            $unidad->update(['estado' => $estado]);

            $movimiento = $kardex->cambioDeEstado($unidad->refresh(), $anterior);

            $this->assertSame($tipoEsperado, $movimiento->tipo, "Estado {$estado}");
        }
    }

    public function test_un_estado_que_no_cambia_no_genera_movimiento(): void
    {
        $this->compraRecepcionada(1);
        $unidad = Unidad::first();

        $this->assertNull(app(Kardex::class)->cambioDeEstado($unidad, 'en_stock'));
    }

    // ---- Pantalla de kardex ------------------------------------------------

    public function test_busca_un_aparato_por_su_serial(): void
    {
        $this->compraRecepcionada(2);
        $unidad = Unidad::first();
        $unidad->update(['serial' => 'SER-BUSCADO']);

        Livewire::actingAs($this->admin())
            ->test(KardexLivewire::class)
            ->set('buscar', 'SER-BUSCADO')
            ->assertSee($unidad->codigo_interno);
    }

    public function test_busca_un_aparato_por_su_codigo_interno(): void
    {
        $this->compraRecepcionada(1);
        $unidad = Unidad::first();

        Livewire::actingAs($this->admin())
            ->test(KardexLivewire::class)
            ->set('buscar', $unidad->codigo_interno)
            ->assertSee($unidad->codigo_interno);
    }

    public function test_abrir_una_unidad_muestra_toda_su_historia(): void
    {
        $this->compraRecepcionada(1);
        $unidad = Unidad::first();

        Livewire::actingAs($this->admin())
            ->test(KardexLivewire::class)
            ->call('abrirUnidad', $unidad->id)
            ->assertSet('unidadId', $unidad->id)
            ->assertSee($unidad->codigo_interno)
            ->assertSee('Entrada');
    }

    // ---- Ajustes -----------------------------------------------------------

    public function test_un_ajuste_cambia_el_estado_y_queda_registrado(): void
    {
        $this->compraRecepcionada(1);
        $unidad = Unidad::first();

        Livewire::actingAs($this->admin())
            ->test(KardexLivewire::class)
            ->call('abrirUnidad', $unidad->id)
            ->set('nuevoEstado', 'danado')
            ->set('motivo', 'Pantalla rota en el traslado')
            ->call('ajustar')
            ->assertHasNoErrors()
            ->assertDispatched('toast', tipo: 'success');

        $this->assertSame('danado', $unidad->fresh()->estado);

        $movimiento = MovimientoInventario::where('unidad_id', $unidad->id)
            ->where('tipo', 'dano')
            ->first();

        $this->assertNotNull($movimiento);
        $this->assertSame('Pantalla rota en el traslado', $movimiento->notas);
        // Queda quién lo hizo: es un registro de auditoría.
        $this->assertNotNull($movimiento->user_id);
    }

    public function test_un_ajuste_sin_motivo_no_se_registra(): void
    {
        // Un ajuste sin explicación no sirve de auditoría.
        $this->compraRecepcionada(1);
        $unidad = Unidad::first();

        Livewire::actingAs($this->admin())
            ->test(KardexLivewire::class)
            ->call('abrirUnidad', $unidad->id)
            ->set('nuevoEstado', 'danado')
            ->set('motivo', '')
            ->call('ajustar')
            ->assertHasErrors('motivo');

        $this->assertSame('en_stock', $unidad->fresh()->estado);
        $this->assertSame(1, MovimientoInventario::where('unidad_id', $unidad->id)->count());
    }

    public function test_ajustar_al_mismo_estado_no_hace_nada(): void
    {
        $this->compraRecepcionada(1);
        $unidad = Unidad::first();

        Livewire::actingAs($this->admin())
            ->test(KardexLivewire::class)
            ->call('abrirUnidad', $unidad->id)
            ->set('nuevoEstado', 'en_stock')
            ->set('motivo', 'Sin novedad')
            ->call('ajustar')
            ->assertHasErrors('nuevoEstado');

        $this->assertSame(1, MovimientoInventario::where('unidad_id', $unidad->id)->count());
    }

    public function test_el_kardex_es_de_solo_escritura(): void
    {
        // No hay updated_at: un movimiento que se puede editar deja de servir
        // como auditoría.
        $this->compraRecepcionada(1);

        $this->assertNull(MovimientoInventario::UPDATED_AT);
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('movimientos_inventario', 'updated_at')
        );
    }

    // ---- Permisos ----------------------------------------------------------

    public function test_el_kardex_exige_el_permiso_de_ver_inventario(): void
    {
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)->get('/inventario/kardex')->assertForbidden();
        $this->actingAs($this->admin())->get('/inventario/kardex')->assertOk();
    }

    public function test_un_vendedor_no_puede_ajustar_el_inventario(): void
    {
        // El vendedor ve el inventario, pero ajustarlo exige inventario.ajustar.
        $this->compraRecepcionada(1);
        $unidad = Unidad::first();
        $vendedor = User::factory()->create()->syncRoles('vendedor');

        Livewire::actingAs($vendedor)
            ->test(KardexLivewire::class)
            ->call('abrirUnidad', $unidad->id)
            ->set('nuevoEstado', 'danado')
            ->set('motivo', 'Intento no autorizado')
            ->call('ajustar')
            ->assertForbidden();

        $this->assertSame('en_stock', $unidad->fresh()->estado);
    }
}
