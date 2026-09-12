<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Venta;
use App\Support\RegistroDeVenta;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El buscador del topbar.
 *
 * Está en todas las pantallas, así que tiene que encontrar de verdad y —más
 * importante— no puede mostrar lo que el usuario no tiene permiso de ver:
 * un resultado hacia una pantalla prohibida delata que ese dato existe.
 */
class BuscadorGlobalTest extends TestCase
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

    private function ventaCon(Unidad $unidad): Venta
    {
        return app(RegistroDeVenta::class)->registrar(
            lineas: [[
                'unidad_id' => $unidad->id,
                'precio_unitario' => (float) $unidad->precio_venta,
                'descuento' => 0,
            ]],
            cabecera: ['metodo_pago' => 'efectivo'],
            userId: $this->admin()->id,
        );
    }

    public function test_sin_termino_invita_a_escribir(): void
    {
        $this->actingAs($this->admin())
            ->get('/buscar')
            ->assertOk()
            ->assertSee('Escribe algo para buscar');
    }

    public function test_cuando_no_hay_nada_lo_dice_en_vez_de_quedarse_mudo(): void
    {
        Producto::factory()->create(['nombre' => 'Licuadora']);

        $this->actingAs($this->admin())
            ->get('/buscar?q=zzzzzz')
            ->assertOk()
            ->assertSee('Sin resultados para');
    }

    public function test_encuentra_un_producto_por_nombre_o_modelo(): void
    {
        Producto::factory()->create([
            'nombre' => 'Refrigerador Doble Puerta',
            'modelo' => 'RD-900X',
        ]);
        Producto::factory()->create(['nombre' => 'Licuadora', 'modelo' => 'L-1']);

        foreach (['Refrigerador', 'RD-900X'] as $termino) {
            $this->actingAs($this->admin())
                ->get('/buscar?q='.urlencode($termino))
                ->assertOk()
                ->assertSee('Refrigerador Doble Puerta')
                ->assertDontSee('Licuadora');
        }
    }

    public function test_encuentra_un_aparato_por_su_serial(): void
    {
        Unidad::factory()->create(['serial' => 'SN-ABC-123']);

        $this->actingAs($this->admin())
            ->get('/buscar?q=SN-ABC-123')
            ->assertOk()
            ->assertSee('Aparatos por serial')
            ->assertSee('SN-ABC-123');
    }

    public function test_el_aparato_vendido_lleva_a_su_venta(): void
    {
        $unidad = Unidad::factory()->create(['serial' => 'SN-VEND-1', 'precio_venta' => 500]);
        $venta = $this->ventaCon($unidad);

        $this->actingAs($this->admin())
            ->get('/buscar?q=SN-VEND-1')
            ->assertOk()
            ->assertSee(route('ventas.show', $venta), false)
            ->assertSee($venta->codigo);
    }

    public function test_encuentra_una_venta_por_su_codigo(): void
    {
        $venta = $this->ventaCon(Unidad::factory()->create(['precio_venta' => 500]));

        $this->actingAs($this->admin())
            ->get('/buscar?q='.urlencode($venta->codigo))
            ->assertOk()
            ->assertSee('Ventas')
            ->assertSee($venta->codigo);
    }

    public function test_no_muestra_resultados_de_modulos_que_el_usuario_no_puede_ver(): void
    {
        $unidad = Unidad::factory()->create(['serial' => 'SN-OCULTO-1', 'precio_venta' => 500]);
        $venta = $this->ventaCon($unidad);

        // Sin ningún rol: no tiene ni productos.ver ni ventas.ver.
        $sinPermisos = User::factory()->create();

        $this->actingAs($sinPermisos)
            ->get('/buscar?q=SN-OCULTO-1')
            ->assertOk()
            ->assertSee('Sin resultados para')
            ->assertDontSee($venta->codigo);
    }

    public function test_desde_un_producto_se_salta_al_inventario_ya_filtrado_sin_exponer_la_url(): void
    {
        $producto = Producto::factory()->create(['nombre' => 'Horno Eléctrico']);

        $this->actingAs($this->admin())
            ->get(route('search.producto', $producto))
            ->assertRedirect(route('inventario.unidades.index'))
            ->assertSessionHas('producto_activo', $producto->id);
    }

    public function test_el_salto_al_inventario_exige_permiso(): void
    {
        $producto = Producto::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('search.producto', $producto))
            ->assertForbidden();
    }

    // ---- Clientes y compras ------------------------------------------------

    public function test_encuentra_un_cliente_por_su_nombre(): void
    {
        $persona = Persona::factory()->create([
            'nombres' => 'Marta',
            'apellido_paterno' => 'Quispe',
            'apellido_materno' => 'Rojas',
        ]);
        $cliente = Cliente::factory()->for($persona)->create(['codigo' => 'CLI-7777']);

        $this->actingAs($this->admin())
            ->get('/buscar?q='.urlencode('Quispe'))
            ->assertOk()
            ->assertSee('Clientes')
            ->assertSee('Marta Quispe Rojas')
            // El código es único: el resultado lleva al listado ya filtrado.
            ->assertSee(route('clientes.index', ['buscar' => $cliente->codigo]), false);
    }

    public function test_encuentra_una_compra_por_su_codigo(): void
    {
        $proveedor = Proveedor::factory()->create(['nombre' => 'Distribuidora Andina']);
        $compra = Compra::factory()->for($proveedor)->create([
            'codigo' => 'COM-2026-9999',
            'total' => 1500,
        ]);

        $this->actingAs($this->admin())
            ->get('/buscar?q=COM-2026-9999')
            ->assertOk()
            ->assertSee('Compras')
            ->assertSee($compra->codigo)
            ->assertSee('Distribuidora Andina');
    }

    // ---- Sugerencias en vivo -----------------------------------------------

    public function test_las_sugerencias_llegan_en_json(): void
    {
        Producto::factory()->create(['nombre' => 'Refrigerador No Frost']);

        $this->actingAs($this->admin())
            ->getJson('/buscar/sugerencias?q=Refri')
            ->assertOk()
            ->assertJsonPath('data.0.titulo', 'Productos')
            ->assertJsonPath('data.0.items.0.titulo', 'Refrigerador No Frost');
    }

    public function test_las_sugerencias_no_buscan_con_una_letra(): void
    {
        Producto::factory()->create(['nombre' => 'Refrigerador No Frost']);

        $this->actingAs($this->admin())
            ->getJson('/buscar/sugerencias?q=R')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }

    public function test_las_sugerencias_respetan_los_permisos(): void
    {
        Unidad::factory()->create(['serial' => 'SN-SECRETO-1', 'precio_venta' => 500]);

        $this->actingAs(User::factory()->create())
            ->getJson('/buscar/sugerencias?q=SN-SECRETO')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
