<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API de catálogo que consume la app: categorías, marcas y productos.
 */
class CatalogoApiTest extends TestCase
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

    /** Producto con `$enStock` unidades listas para vender. */
    private function producto(array $atributos = [], int $enStock = 0): Producto
    {
        $producto = Producto::factory()->create($atributos);

        Unidad::factory()->count($enStock)->create([
            'producto_id' => $producto->id,
            'estado' => 'en_stock',
        ]);

        return $producto;
    }

    // ---- Categorías --------------------------------------------------------

    public function test_las_categorias_viajan_planas_con_su_nivel(): void
    {
        $padre = Categoria::factory()->create(['nombre' => 'Audio', 'posicion' => 1]);
        $hija = Categoria::factory()->create([
            'nombre' => 'Parlantes',
            'padre_id' => $padre->id,
            'posicion' => 1,
        ]);

        Producto::factory()->count(2)->create(['categoria_id' => $hija->id]);

        Sanctum::actingAs($this->admin());

        $respuesta = $this->getJson('/api/v1/catalogo/categorias')->assertOk();

        $filas = $respuesta->json('data');

        // La hija va justo detrás de su padre, con un nivel más.
        $this->assertSame('Audio', $filas[0]['nombre']);
        $this->assertSame(0, $filas[0]['nivel']);
        $this->assertSame('Parlantes', $filas[1]['nombre']);
        $this->assertSame(1, $filas[1]['nivel']);
        $this->assertSame($padre->id, $filas[1]['padre_id']);

        // El padre no tiene productos propios, pero su rama sí: contarlos solo
        // en directo lo haría parecer vacío.
        $this->assertSame(0, $filas[0]['productos']);
        $this->assertSame(2, $filas[0]['productos_rama']);
        $this->assertSame(1, $filas[0]['subcategorias']);
    }

    // ---- Marcas ------------------------------------------------------------

    public function test_las_marcas_traen_sus_productos_y_lo_que_queda_en_stock(): void
    {
        $marca = Marca::factory()->create(['nombre' => 'Samsung']);

        $this->producto(['marca_id' => $marca->id], enStock: 3);
        $this->producto(['marca_id' => $marca->id], enStock: 0);

        Sanctum::actingAs($this->admin());

        $fila = $this->getJson('/api/v1/catalogo/marcas')->assertOk()->json('data.0');

        $this->assertSame('Samsung', $fila['nombre']);
        $this->assertSame(2, $fila['productos']);
        $this->assertSame(3, $fila['disponibles']);
    }

    // ---- Productos ---------------------------------------------------------

    public function test_el_listado_marca_lo_agotado_y_lo_que_esta_bajo_minimo(): void
    {
        $this->producto(['nombre' => 'TV agotado', 'stock_minimo' => 2], enStock: 0);
        $this->producto(['nombre' => 'TV justo', 'stock_minimo' => 3], enStock: 1);
        $this->producto(['nombre' => 'TV sobrado', 'stock_minimo' => 1], enStock: 5);

        Sanctum::actingAs($this->admin());

        $porNombre = collect(
            $this->getJson('/api/v1/catalogo/productos')->assertOk()->json('data')
        )->keyBy('nombre');

        $this->assertTrue($porNombre['TV agotado']['agotado']);
        $this->assertFalse($porNombre['TV justo']['agotado']);
        $this->assertTrue($porNombre['TV justo']['bajo_minimo']);
        $this->assertFalse($porNombre['TV sobrado']['bajo_minimo']);
        $this->assertSame(5, $porNombre['TV sobrado']['disponibles']);
    }

    public function test_el_listado_filtra_por_la_rama_de_la_categoria(): void
    {
        $padre = Categoria::factory()->create();
        $hija = Categoria::factory()->create(['padre_id' => $padre->id]);
        $otra = Categoria::factory()->create();

        $this->producto(['categoria_id' => $padre->id, 'nombre' => 'Del padre']);
        $this->producto(['categoria_id' => $hija->id, 'nombre' => 'De la hija']);
        $this->producto(['categoria_id' => $otra->id, 'nombre' => 'De otra rama']);

        Sanctum::actingAs($this->admin());

        // Entrar en el padre muestra también lo que cuelga de él.
        $nombres = collect(
            $this->getJson("/api/v1/catalogo/productos?categoria_id={$padre->id}")
                ->assertOk()
                ->json('data')
        )->pluck('nombre');

        $this->assertCount(2, $nombres);
        $this->assertContains('Del padre', $nombres);
        $this->assertContains('De la hija', $nombres);
    }

    public function test_el_listado_busca_por_nombre_y_serial(): void
    {
        $producto = $this->producto(['nombre' => 'Licuadora']);
        $unidad = Unidad::factory()->create([
            'producto_id' => $producto->id,
            'estado' => 'en_stock',
            'serial' => 'SN-ABC-999',
        ]);

        $this->producto(['nombre' => 'Televisor']);

        Sanctum::actingAs($this->admin());

        foreach (['Licua', $unidad->serial] as $termino) {
            $datos = $this->getJson('/api/v1/catalogo/productos?buscar='.urlencode($termino))
                ->assertOk()
                ->json('data');

            $this->assertCount(1, $datos, "La búsqueda «{$termino}» no acotó el listado.");
            $this->assertSame('Licuadora', $datos[0]['nombre']);
        }
    }

    public function test_el_filtro_de_disponibles_deja_fuera_lo_agotado(): void
    {
        $this->producto(['nombre' => 'Con stock'], enStock: 2);
        $this->producto(['nombre' => 'Agotado']);

        Sanctum::actingAs($this->admin());

        $datos = $this->getJson('/api/v1/catalogo/productos?solo_disponibles=1')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $datos);
        $this->assertSame('Con stock', $datos[0]['nombre']);
    }

    public function test_la_ficha_trae_especificaciones_y_las_unidades_en_stock(): void
    {
        $producto = $this->producto([
            'nombre' => 'Parlante',
            'descripcion' => 'Con bluetooth',
            'especificaciones' => ['Potencia' => '120 W', 'Bluetooth' => true],
            'descuento_maximo' => 25,
        ], enStock: 2);

        Sanctum::actingAs($this->admin());

        $ficha = $this->getJson("/api/v1/catalogo/productos/{$producto->id}")
            ->assertOk()
            ->json('data');

        $this->assertSame('Con bluetooth', $ficha['descripcion']);
        // assertEquals y no assertSame: un float sin decimales viaja como 25.
        $this->assertEquals(25, $ficha['descuento_maximo']);
        $this->assertSame(
            [['clave' => 'Potencia', 'valor' => '120 W'], ['clave' => 'Bluetooth', 'valor' => '']],
            $ficha['especificaciones']
        );
        $this->assertCount(2, $ficha['unidades']);
        $this->assertArrayNotHasKey('costo_unitario', $ficha['unidades'][0]);
    }

    public function test_la_ficha_no_lista_unidades_a_quien_no_puede_verlas(): void
    {
        // El costo y el inventario serializado no son ficha comercial.
        $producto = $this->producto(enStock: 2);

        $usuario = User::factory()->create(['is_active' => true]);
        $usuario->syncPermissions(['productos.ver']);

        Sanctum::actingAs($usuario);

        $ficha = $this->getJson("/api/v1/catalogo/productos/{$producto->id}")
            ->assertOk()
            ->json('data');

        $this->assertArrayNotHasKey('unidades', $ficha);
        // El conteo sí: saber cuántos quedan no expone nada.
        $this->assertSame(2, $ficha['disponibles']);
    }

    public function test_el_catalogo_exige_el_permiso_de_ver_productos(): void
    {
        $usuario = User::factory()->create(['is_active' => true]);

        Sanctum::actingAs($usuario);

        $this->getJson('/api/v1/catalogo/categorias')->assertForbidden();
        $this->getJson('/api/v1/catalogo/marcas')->assertForbidden();
        $this->getJson('/api/v1/catalogo/productos')->assertForbidden();
    }

    public function test_el_catalogo_no_responde_sin_sesion(): void
    {
        $this->getJson('/api/v1/catalogo/productos')->assertUnauthorized();
    }
}
