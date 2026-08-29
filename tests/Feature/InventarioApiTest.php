<?php

namespace Tests\Feature;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Unidad;
use App\Models\User;
use App\Support\RecepcionDeCompra;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Inventario desde el teléfono: listado, ficha con kardex y ajuste.
 *
 * Es el módulo que más se consulta de pie, con el aparato en la mano. La app
 * puede mirar y corregir estado y ubicación; el alta, la baja y los importes
 * se quedan en el panel.
 */
class InventarioApiTest extends TestCase
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

    private function vendedor(): User
    {
        return User::factory()->create(['is_active' => true])->syncRoles('vendedor');
    }

    private function unidad(array $atributos = []): Unidad
    {
        return Unidad::factory()->create([
            'producto_id' => Producto::factory()->create()->id,
            'estado' => 'en_stock',
            ...$atributos,
        ]);
    }

    /** Una compra recepcionada deja unidades con su entrada en el kardex. */
    private function compraRecepcionada(int $cantidad = 2): Compra
    {
        $compra = Compra::factory()->create([
            'proveedor_id' => Proveedor::factory()->create()->id,
            'estado' => 'borrador',
        ]);

        CompraDetalle::factory()->create([
            'compra_id' => $compra->id,
            'producto_id' => Producto::factory()->create()->id,
            'cantidad' => $cantidad,
            'costo_unitario' => 100,
            'subtotal' => 100 * $cantidad,
            'precio_venta' => 150,
        ]);

        app(RecepcionDeCompra::class)->recepcionar($compra);

        return $compra->fresh();
    }

    // ---- Listado -----------------------------------------------------------

    public function test_lista_el_inventario_paginado(): void
    {
        $this->unidad();
        $this->unidad();

        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/unidades')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'codigo_interno', 'estado', 'estado_texto', 'precio_venta']],
                'meta' => ['resumen'],
            ]);
    }

    public function test_busca_por_codigo_interno_por_serial_y_por_producto(): void
    {
        $porCodigo = $this->unidad(['codigo_interno' => 'TVSAM55-2608-0042']);
        $porSerial = $this->unidad(['serial' => 'SN-BUSCABLE-9']);
        $porNombre = Unidad::factory()->create([
            'producto_id' => Producto::factory()->create(['nombre' => 'Licuadora Oster'])->id,
            'estado' => 'en_stock',
        ]);

        Sanctum::actingAs($this->admin());

        foreach ([
            'TVSAM55-2608' => $porCodigo->id,
            'SN-BUSCABLE' => $porSerial->id,
            'Licuadora' => $porNombre->id,
        ] as $termino => $esperada) {
            $respuesta = $this->getJson('/api/v1/unidades?buscar='.urlencode($termino))->assertOk();

            $this->assertSame(
                [$esperada],
                array_column($respuesta->json('data'), 'id'),
                "La búsqueda «{$termino}» no devolvió la unidad esperada."
            );
        }
    }

    public function test_filtra_por_estado(): void
    {
        $this->unidad(['estado' => 'en_stock']);
        $danada = $this->unidad(['estado' => 'danado']);

        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson('/api/v1/unidades?estado=danado')->assertOk();

        $this->assertSame([$danada->id], array_column($respuesta->json('data'), 'id'));
    }

    public function test_el_resumen_cuenta_todos_los_estados_aunque_esten_a_cero(): void
    {
        $this->unidad(['estado' => 'en_stock']);
        $this->unidad(['estado' => 'danado']);

        Sanctum::actingAs($this->admin());

        $resumen = $this->getJson('/api/v1/unidades')->assertOk()->json('meta.resumen');

        $this->assertSame(1, $resumen['en_stock']);
        $this->assertSame(1, $resumen['danado']);
        // Una pestaña que aparece y desaparece según el stock del día
        // desconcierta más de lo que ahorra: los ceros también viajan.
        $this->assertSame(0, $resumen['perdido']);
        $this->assertSame(array_keys(Unidad::ESTADOS), array_keys($resumen));
    }

    // ---- Ficha con kardex --------------------------------------------------

    public function test_la_ficha_trae_el_kardex_del_aparato(): void
    {
        $this->compraRecepcionada(1);
        $unidad = Unidad::first();

        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson("/api/v1/unidades/{$unidad->id}")
            ->assertOk()
            ->assertJsonPath('data.codigo_interno', $unidad->codigo_interno);

        $this->assertSame('entrada', $respuesta->json('data.kardex.0.tipo'));
        $this->assertSame('Entrada', $respuesta->json('data.kardex.0.tipo_texto'));
        // La compra que lo trajo: es lo que convierte el kardex en una
        // historia y no en una lista de fechas.
        $this->assertStringStartsWith('Compra ', $respuesta->json('data.kardex.0.origen'));
        $this->assertNotNull($respuesta->json('data.compra.codigo'));
    }

    public function test_el_costo_solo_viaja_con_el_permiso_de_ver_costos(): void
    {
        $unidad = $this->unidad(['costo_unitario' => 700]);

        // El vendedor mira el inventario desde el mostrador: ve qué hay y
        // dónde está, no cuánto margen deja.
        Sanctum::actingAs($this->vendedor());
        $this->getJson("/api/v1/unidades/{$unidad->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.costo_unitario');

        Sanctum::actingAs($this->admin());
        $respuesta = $this->getJson("/api/v1/unidades/{$unidad->id}")->assertOk();

        // Comparación numérica, no idéntica: 700.0 se serializa a JSON como
        // `700` y vuelve como entero.
        $this->assertEqualsWithDelta(700, $respuesta->json('data.costo_unitario'), 0.001);
    }

    public function test_la_ficha_dice_en_que_venta_salio_el_aparato(): void
    {
        $unidad = $this->unidad(['estado' => 'vendido']);

        Sanctum::actingAs($this->admin());

        // Sin venta detrás la clave existe igualmente, en null: la app
        // distingue «no se vendió» de «no me lo dijeron».
        $this->getJson("/api/v1/unidades/{$unidad->id}")
            ->assertOk()
            ->assertJsonPath('data.venta', null);
    }

    // ---- Ajuste ------------------------------------------------------------

    public function test_ajustar_el_estado_deja_rastro_en_el_kardex(): void
    {
        $unidad = $this->unidad(['estado' => 'en_stock']);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/unidades/{$unidad->id}", [
            'estado' => 'danado',
            'motivo' => 'Pantalla rota en el traslado',
        ])->assertOk()->assertJsonPath('data.estado', 'danado');

        $movimiento = MovimientoInventario::where('unidad_id', $unidad->id)->first();

        $this->assertNotNull($movimiento, 'El ajuste no dejó movimiento en el kardex.');
        $this->assertSame('dano', $movimiento->tipo);
        $this->assertSame('en_stock', $movimiento->estado_anterior);
        $this->assertSame('Pantalla rota en el traslado', $movimiento->notas);
    }

    public function test_ajustar_la_ubicacion_no_inventa_un_movimiento(): void
    {
        $unidad = $this->unidad(['estado' => 'en_stock']);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/unidades/{$unidad->id}", [
            'ubicacion' => 'Pasillo 5, estante B',
        ])->assertOk()->assertJsonPath('data.ubicacion', 'Pasillo 5, estante B');

        // El aparato no se movió de estado: un kardex lleno de filas que no
        // mueven nada no se puede leer.
        $this->assertSame(0, MovimientoInventario::where('unidad_id', $unidad->id)->count());
    }

    public function test_una_ubicacion_en_blanco_se_guarda_como_nula(): void
    {
        $unidad = $this->unidad(['ubicacion' => 'Pasillo 3']);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/unidades/{$unidad->id}", ['ubicacion' => '   '])->assertOk();

        $this->assertNull($unidad->fresh()->ubicacion);
    }

    public function test_no_se_puede_marcar_vendido_a_mano(): void
    {
        $unidad = $this->unidad(['estado' => 'en_stock']);

        Sanctum::actingAs($this->admin());

        // Sacaría el aparato del stock sin una venta que lo respalde.
        $this->postJson("/api/v1/unidades/{$unidad->id}", ['estado' => 'vendido'])
            ->assertStatus(422);

        $this->assertSame('en_stock', $unidad->fresh()->estado);
        $this->assertSame(0, MovimientoInventario::where('unidad_id', $unidad->id)->count());
    }

    public function test_no_se_puede_devolver_al_stock_un_aparato_vendido(): void
    {
        $unidad = $this->unidad(['estado' => 'vendido']);

        Sanctum::actingAs($this->admin());

        // Dejaría una línea de venta apuntando a un aparato que vuelve a
        // figurar disponible: el mismo aparato se vendería dos veces.
        $this->postJson("/api/v1/unidades/{$unidad->id}", ['estado' => 'en_stock'])
            ->assertStatus(422);

        $this->assertSame('vendido', $unidad->fresh()->estado);
    }

    public function test_un_ajuste_sin_cambios_se_rechaza(): void
    {
        $unidad = $this->unidad();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/unidades/{$unidad->id}", ['motivo' => 'porque sí'])
            ->assertStatus(422);
    }

    public function test_un_estado_inventado_se_rechaza(): void
    {
        $unidad = $this->unidad();

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/unidades/{$unidad->id}", ['estado' => 'hackeado'])
            ->assertStatus(422);
    }

    // ---- Permisos ----------------------------------------------------------

    public function test_ver_el_inventario_exige_su_permiso(): void
    {
        $unidad = $this->unidad();
        $sinPermiso = User::factory()->create(['is_active' => true]);

        Sanctum::actingAs($sinPermiso);

        $this->getJson('/api/v1/unidades')->assertForbidden();
        $this->getJson("/api/v1/unidades/{$unidad->id}")->assertForbidden();
    }

    public function test_el_vendedor_consulta_pero_no_ajusta(): void
    {
        $unidad = $this->unidad();

        Sanctum::actingAs($this->vendedor());

        $this->getJson("/api/v1/unidades/{$unidad->id}")->assertOk();
        $this->postJson("/api/v1/unidades/{$unidad->id}", ['estado' => 'danado'])
            ->assertForbidden();
    }

    public function test_un_invitado_no_entra(): void
    {
        $unidad = $this->unidad();

        $this->getJson("/api/v1/unidades/{$unidad->id}")->assertUnauthorized();
    }
}
