<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Unidad;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Alta, edición y baja del catálogo desde la app.
 *
 * Lo que se comprueba aquí es sobre todo que las reglas **coinciden con las del
 * panel**: si la API fuera más laxa, el catálogo acabaría con dos criterios
 * según por dónde se tocó.
 */
class CatalogoEscrituraApiTest extends TestCase
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

    // ---- Categorías ---------------------------------------------------------

    public function test_crea_una_categoria_y_le_deriva_el_slug_del_nombre(): void
    {
        Sanctum::actingAs($this->admin());

        // El slug no se pide: es un dato técnico que nadie teclea de pie en la
        // tienda.
        $this->postJson('/api/v1/catalogo/categorias', [
            'nombre' => 'Línea Blanca',
        ])->assertCreated()->assertJsonPath('data.slug', 'linea-blanca');

        $this->assertDatabaseHas('categorias', ['nombre' => 'Línea Blanca']);
    }

    public function test_dos_categorias_con_el_mismo_nombre_no_chocan_de_slug(): void
    {
        Categoria::factory()->create(['nombre' => 'Audio', 'slug' => 'audio']);

        Sanctum::actingAs($this->admin());

        // Sin sufijo, el índice único rechazaría la segunda con un error de
        // base de datos que no explica nada.
        $this->postJson('/api/v1/catalogo/categorias', ['nombre' => 'Audio'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'audio-2');
    }

    public function test_una_categoria_no_puede_colgar_de_si_misma(): void
    {
        $categoria = Categoria::factory()->create();

        Sanctum::actingAs($this->admin());

        // Dejaría el árbol en un ciclo y el listado se colgaría al recorrerlo.
        $this->postJson("/api/v1/catalogo/categorias/{$categoria->id}", [
            'nombre' => $categoria->nombre,
            'padre_id' => $categoria->id,
        ])->assertStatus(422)->assertJsonValidationErrors('padre_id');
    }

    public function test_una_categoria_no_puede_colgar_de_su_propia_subcategoria(): void
    {
        $padre = Categoria::factory()->create();
        $hija = Categoria::factory()->create(['padre_id' => $padre->id]);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/catalogo/categorias/{$padre->id}", [
            'nombre' => $padre->nombre,
            'padre_id' => $hija->id,
        ])->assertStatus(422)->assertJsonValidationErrors('padre_id');
    }

    public function test_no_se_elimina_una_categoria_con_subcategorias(): void
    {
        $padre = Categoria::factory()->create();
        Categoria::factory()->create(['padre_id' => $padre->id]);

        Sanctum::actingAs($this->admin());

        // Sus ramas quedarían huérfanas y desaparecidas del árbol.
        $this->deleteJson("/api/v1/catalogo/categorias/{$padre->id}")
            ->assertStatus(422);

        $this->assertNull($padre->fresh()->deleted_at);
    }

    public function test_elimina_una_categoria_sin_ramas(): void
    {
        $categoria = Categoria::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/catalogo/categorias/{$categoria->id}")->assertOk();

        $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);
    }

    // ---- Marcas -------------------------------------------------------------

    public function test_crea_una_marca_con_su_logo(): void
    {
        Storage::fake('public');

        Sanctum::actingAs($this->admin());

        $respuesta = $this->post('/api/v1/catalogo/marcas', [
            'nombre' => 'Barson',
            'logo' => UploadedFile::fake()->image('barson.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $marca = Marca::firstOrFail();

        $this->assertNotNull($marca->logo_ruta);
        Storage::disk('public')->assertExists($marca->logo_ruta);
        $this->assertSame('barson', $respuesta->json('data.slug'));
    }

    public function test_no_se_repite_el_nombre_de_una_marca(): void
    {
        Marca::factory()->create(['nombre' => 'Barson']);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/catalogo/marcas', ['nombre' => 'Barson'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre');
    }

    public function test_no_se_elimina_una_marca_que_tiene_productos(): void
    {
        $marca = Marca::factory()->create();
        Producto::factory()->create(['marca_id' => $marca->id]);

        Sanctum::actingAs($this->admin());

        // Marca NO tiene borrado lógico y la FK es restrictOnDelete: sin esta
        // guarda, el fallo llegaría como un error de base de datos.
        $this->deleteJson("/api/v1/catalogo/marcas/{$marca->id}")->assertStatus(422);

        $this->assertDatabaseHas('marcas', ['id' => $marca->id]);
    }

    public function test_al_reemplazar_el_logo_se_borra_el_anterior(): void
    {
        Storage::fake('public');

        $marca = Marca::factory()->create([
            'logo_ruta' => UploadedFile::fake()->image('viejo.png')->store('marcas', 'public'),
        ]);
        $anterior = $marca->logo_ruta;

        Sanctum::actingAs($this->admin());

        $this->post("/api/v1/catalogo/marcas/{$marca->id}", [
            'nombre' => $marca->nombre,
            'logo' => UploadedFile::fake()->image('nuevo.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        // Sin esto, cada edición deja un archivo huérfano en el disco.
        Storage::disk('public')->assertMissing($anterior);
        Storage::disk('public')->assertExists($marca->fresh()->logo_ruta);
    }

    // ---- Productos ----------------------------------------------------------

    public function test_crea_un_producto_con_sus_especificaciones(): void
    {
        $categoria = Categoria::factory()->create();

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/catalogo/productos', [
            'nombre' => 'Televisor 55',
            'sku' => 'TV-55-001',
            'categoria_id' => $categoria->id,
            'precio_venta' => 1899,
            'descuento_maximo' => 100,
            'especificaciones' => [
                ['clave' => 'Pantalla', 'valor' => '55 pulgadas'],
                // Sin valor: es la bandera del panel para lo que se tiene o no
                // se tiene, y se guarda como `true`, no se descarta.
                ['clave' => 'Bluetooth', 'valor' => ''],
                // Sin clave no hay nada que decir: el formulario ofrece filas
                // vacías para ir agregando y guardarlas dejaría la ficha con
                // «: —» repetido.
                ['clave' => '', 'valor' => 'suelto'],
            ],
        ])->assertCreated();

        $producto = Producto::firstOrFail();

        // Mapa, no lista de pares: es el formato que escribe el panel y el que
        // `ProductoResource` sabe leer. Guardar otro dejaría dos formatos en la
        // misma columna según por dónde se creó el producto.
        $this->assertSame(
            ['Pantalla' => '55 pulgadas', 'Bluetooth' => true],
            $producto->especificaciones,
        );
    }

    public function test_un_producto_sin_especificaciones_las_guarda_como_nulo(): void
    {
        $categoria = Categoria::factory()->create();

        Sanctum::actingAs($this->admin());

        // Un array vacío se guardaría como `{}` y la ficha enseñaría una
        // sección de especificaciones sin nada dentro.
        $this->postJson('/api/v1/catalogo/productos', [
            'nombre' => 'Plancha',
            'sku' => 'PLA-001',
            'categoria_id' => $categoria->id,
            'precio_venta' => 150,
            'descuento_maximo' => 0,
            'especificaciones' => [],
        ])->assertCreated();

        $this->assertNull(Producto::firstOrFail()->especificaciones);
    }

    public function test_el_sku_se_guarda_en_mayusculas(): void
    {
        $categoria = Categoria::factory()->create();

        Sanctum::actingAs($this->admin());

        // Se compara a ojo contra la etiqueta: «tv-55» y «TV-55» tienen que
        // ser el mismo, igual que en el panel.
        $this->postJson('/api/v1/catalogo/productos', [
            'nombre' => 'Televisor',
            'sku' => 'tv-55-002',
            'categoria_id' => $categoria->id,
            'precio_venta' => 100,
            'descuento_maximo' => 0,
        ])->assertCreated();

        $this->assertSame('TV-55-002', Producto::firstOrFail()->sku);
    }

    public function test_la_rebaja_no_puede_superar_al_precio(): void
    {
        $categoria = Categoria::factory()->create();

        Sanctum::actingAs($this->admin());

        // Un descuento mayor dejaría vender el aparato gratis o en negativo.
        $this->postJson('/api/v1/catalogo/productos', [
            'nombre' => 'Licuadora',
            'sku' => 'LIC-001',
            'categoria_id' => $categoria->id,
            'precio_venta' => 300,
            'descuento_maximo' => 400,
        ])->assertStatus(422)->assertJsonValidationErrors('descuento_maximo');
    }

    public function test_no_se_repite_el_sku(): void
    {
        $categoria = Categoria::factory()->create();
        Producto::factory()->create(['sku' => 'TV-55-001']);

        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/catalogo/productos', [
            'nombre' => 'Otro',
            'sku' => 'TV-55-001',
            'categoria_id' => $categoria->id,
            'precio_venta' => 100,
            'descuento_maximo' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors('sku');
    }

    public function test_editar_un_producto_no_choca_con_su_propio_sku(): void
    {
        $producto = Producto::factory()->create(['sku' => 'TV-55-001']);

        Sanctum::actingAs($this->admin());

        $this->postJson("/api/v1/catalogo/productos/{$producto->id}", [
            'nombre' => 'Televisor renombrado',
            'sku' => 'TV-55-001',
            'categoria_id' => $producto->categoria_id,
            'precio_venta' => 2000,
            'descuento_maximo' => 0,
        ])->assertOk();

        $this->assertSame('Televisor renombrado', $producto->fresh()->nombre);
    }

    public function test_eliminar_un_producto_no_borra_sus_unidades_ni_su_imagen(): void
    {
        Storage::fake('public');

        $producto = Producto::factory()->create([
            'imagen' => UploadedFile::fake()->image('tv.png')->store('productos', 'public'),
        ]);
        $unidad = Unidad::factory()->create([
            'producto_id' => $producto->id,
            'estado' => 'en_stock',
        ]);
        $imagen = $producto->imagen;

        Sanctum::actingAs($this->admin());

        $this->deleteJson("/api/v1/catalogo/productos/{$producto->id}")->assertOk();

        // Borrado lógico: las unidades y las ventas siguen apuntando aquí, y
        // restaurarlo desde el panel debe devolverlo completo, con su foto.
        $this->assertSoftDeleted('productos', ['id' => $producto->id]);
        $this->assertDatabaseHas('unidades', ['id' => $unidad->id]);
        Storage::disk('public')->assertExists($imagen);
    }

    // ---- Permisos -----------------------------------------------------------

    public function test_un_vendedor_no_puede_escribir_en_el_catalogo(): void
    {
        $categoria = Categoria::factory()->create();
        $marca = Marca::factory()->create();
        $producto = Producto::factory()->create();

        // El vendedor consulta el catálogo en el mostrador, no lo administra.
        Sanctum::actingAs($this->vendedor());

        $this->postJson('/api/v1/catalogo/categorias', ['nombre' => 'Nueva'])->assertForbidden();
        $this->postJson("/api/v1/catalogo/categorias/{$categoria->id}", ['nombre' => 'X'])->assertForbidden();
        $this->deleteJson("/api/v1/catalogo/categorias/{$categoria->id}")->assertForbidden();

        $this->postJson('/api/v1/catalogo/marcas', ['nombre' => 'Nueva'])->assertForbidden();
        $this->deleteJson("/api/v1/catalogo/marcas/{$marca->id}")->assertForbidden();

        $this->postJson('/api/v1/catalogo/productos', ['nombre' => 'Nuevo'])->assertForbidden();
        $this->deleteJson("/api/v1/catalogo/productos/{$producto->id}")->assertForbidden();
    }

    public function test_sin_sesion_no_se_puede_escribir(): void
    {
        $this->postJson('/api/v1/catalogo/categorias', ['nombre' => 'Nueva'])->assertUnauthorized();
    }
}
