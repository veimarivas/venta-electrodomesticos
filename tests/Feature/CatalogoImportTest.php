<?php

namespace Tests\Feature;

use App\Livewire\Catalogo\Importar;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\User;
use App\Support\Excel\EscritorXlsx;
use App\Support\Excel\LectorXlsx;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Carga masiva del catálogo desde un Excel: categorías, subcategorías y
 * productos de una vez.
 *
 * Lo que se fija aquí es la promesa de la función: un archivo relleno con la
 * plantilla crea el catálogo, reimportarlo actualiza en vez de duplicar, y las
 * filas mal escritas no tumban al resto.
 */
class CatalogoImportTest extends TestCase
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

    /**
     * Arma un `.xlsx` con las hojas dadas y lo devuelve como archivo subible.
     *
     * @param  array<int, array{nombre: string, filas: array<int, array<int, scalar|null>>}>  $hojas
     */
    private function archivo(array $hojas): UploadedFile
    {
        return $this->archivoCon((new EscritorXlsx)->generar($hojas));
    }

    private function archivoCon(string $contenido): UploadedFile
    {
        // `fake()` y no un UploadedFile a mano: Livewire lee la propiedad `name`
        // del archivo de prueba al almacenarlo en su carpeta temporal.
        return UploadedFile::fake()
            ->createWithContent('catalogo.xlsx', $contenido)
            ->mimeType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }

    /**
     * @return array<int, array{nombre: string, filas: array<int, array<int, string>>}>
     */
    private function hojasValidas(): array
    {
        return [
            [
                'nombre' => 'Categorias',
                'filas' => [
                    ['Nombre', 'Categoria padre', 'Descripcion', 'Activo (SI/NO)'],
                    ['Electrónica', '', 'Línea general', 'SI'],
                    ['Audio', 'Electrónica', 'Equipos de sonido', 'SI'],
                ],
            ],
            [
                'nombre' => 'Productos',
                'filas' => [
                    [
                        'Categoria', 'Subcategoria', 'Nombre', 'Marca', 'Modelo',
                        'Descripcion', 'Precio venta', 'Descuento maximo',
                        'Stock minimo', 'Meses garantia', 'Tiene serial (SI/NO)',
                        'Activo (SI/NO)', 'Especificaciones',
                    ],
                    [
                        'Electrónica', 'Audio', 'Parlante JBL', 'JBL', 'Flip 6',
                        'Parlante portátil', '350,50', '20', '1', '12', 'SI', 'SI',
                        'Potencia=20W; Bluetooth=',
                    ],
                ],
            ],
        ];
    }

    // ---- Plantilla -----------------------------------------------------------

    public function test_descarga_la_plantilla_con_las_hojas_esperadas(): void
    {
        Sanctum::actingAs($this->admin());

        $respuesta = $this->get('/api/v1/catalogo/plantilla')->assertOk();

        $respuesta->assertDownload('plantilla-catalogo.xlsx');

        // El contenido tiene que ser un Excel de verdad, con la misma forma que
        // el importador entiende.
        $ruta = tempnam(sys_get_temp_dir(), 'plan').'.xlsx';
        file_put_contents($ruta, $respuesta->streamedContent());

        $hojas = (new LectorXlsx)->leer($ruta);
        unlink($ruta);

        $this->assertArrayHasKey('Categorias', $hojas);
        $this->assertArrayHasKey('Productos', $hojas);
        $this->assertSame('Nombre', $hojas['Categorias'][0][0]);
        $this->assertSame('Categoria', $hojas['Productos'][0][0]);
    }

    // ---- Importación ---------------------------------------------------------

    public function test_importa_categorias_subcategorias_y_productos(): void
    {
        Sanctum::actingAs($this->admin());

        $this->post('/api/v1/catalogo/importar', ['archivo' => $this->archivo($this->hojasValidas())], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.categorias_creadas', 2)
            ->assertJsonPath('data.productos_creados', 1)
            ->assertJsonPath('data.errores', []);

        $electronica = Categoria::where('nombre', 'Electrónica')->firstOrFail();
        $audio = Categoria::where('nombre', 'Audio')->firstOrFail();

        // La subcategoría cuelga de su padre, no de la raíz.
        $this->assertSame($electronica->id, $audio->padre_id);

        $producto = Producto::firstOrFail();
        $this->assertSame('Parlante JBL', $producto->nombre);
        $this->assertSame($audio->id, $producto->categoria_id);
        $this->assertSame(350.50, (float) $producto->precio_venta);
        $this->assertSame('JBL', $producto->marca->nombre);

        // Las especificaciones llegan de la celda «clave=valor; clave2=valor2».
        $filas = $producto->especificaciones()->orderBy('posicion')->get();
        $this->assertCount(2, $filas);
        $this->assertSame('Potencia', $filas[0]->clave);
        $this->assertSame('20W', $filas[0]->valor);
        $this->assertSame('Bluetooth', $filas[1]->clave);
        $this->assertNull($filas[1]->valor);
    }

    public function test_reimportar_actualiza_en_vez_de_duplicar(): void
    {
        Sanctum::actingAs($this->admin());

        $this->post('/api/v1/catalogo/importar', ['archivo' => $this->archivo($this->hojasValidas())], ['Accept' => 'application/json'])->assertOk();

        $hojas = $this->hojasValidas();
        $hojas[1]['filas'][1][6] = '400.00';

        $this->post('/api/v1/catalogo/importar', ['archivo' => $this->archivo($hojas)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.categorias_creadas', 0)
            ->assertJsonPath('data.categorias_actualizadas', 2)
            ->assertJsonPath('data.productos_creados', 0)
            ->assertJsonPath('data.productos_actualizados', 1);

        $this->assertSame(1, Producto::count());
        $this->assertSame(400.0, (float) Producto::firstOrFail()->precio_venta);
    }

    public function test_una_fila_con_error_no_detiene_al_resto(): void
    {
        Sanctum::actingAs($this->admin());

        $hojas = [
            [
                'nombre' => 'Categorias',
                'filas' => [
                    ['Nombre', 'Categoria padre'],
                    ['Audio', ''],
                ],
            ],
            [
                'nombre' => 'Productos',
                'filas' => [
                    ['Categoria', 'Nombre', 'Precio venta'],
                    ['Electrónica', 'Producto huérfano', '100'],
                    ['Audio', 'Parlante', 'no es un número'],
                    ['Audio', 'Bafle', '150'],
                ],
            ],
        ];

        $respuesta = $this->post('/api/v1/catalogo/importar', ['archivo' => $this->archivo($hojas)], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.productos_creados', 1);

        $this->assertCount(2, $respuesta->json('data.errores'));
        $this->assertSame('Bafle', Producto::firstOrFail()->nombre);
    }

    public function test_se_ignoran_las_filas_de_ejemplo(): void
    {
        Sanctum::actingAs($this->admin());

        // La plantilla trae ejemplos marcados con «#»; importarla tal cual no
        // debe crear nada.
        $archivo = $this->archivoCon(app(\App\Support\ImportadorCatalogo::class)->plantilla());

        $this->post('/api/v1/catalogo/importar', ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.productos_creados', 0)
            ->assertJsonPath('data.categorias_creadas', 0);
    }

    // ---- Permisos ------------------------------------------------------------

    public function test_un_vendedor_no_puede_importar_ni_descargar_la_plantilla(): void
    {
        Sanctum::actingAs($this->vendedor());

        $this->post('/api/v1/catalogo/importar', ['archivo' => $this->archivo($this->hojasValidas())], ['Accept' => 'application/json'])
            ->assertForbidden();

        $this->get('/api/v1/catalogo/plantilla')->assertForbidden();
    }

    public function test_sin_sesion_no_se_puede_importar(): void
    {
        $this->post('/api/v1/catalogo/importar', [], ['Accept' => 'application/json'])
            ->assertUnauthorized();
    }

    // ---- Panel (Livewire) ----------------------------------------------------

    public function test_el_panel_descarga_la_plantilla(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Importar::class)
            ->call('descargarPlantilla')
            ->assertFileDownloaded('plantilla-catalogo.xlsx');
    }

    public function test_el_panel_importa_el_archivo(): void
    {
        Livewire::actingAs($this->admin())
            ->test(Importar::class)
            ->set('archivo', $this->archivo($this->hojasValidas()))
            ->call('importar')
            ->assertSet('resultado.categorias_creadas', 2)
            ->assertSet('resultado.productos_creados', 1);

        $this->assertDatabaseHas('productos', ['nombre' => 'Parlante JBL']);
    }

    public function test_rechaza_un_archivo_que_no_es_excel(): void
    {
        Sanctum::actingAs($this->admin());

        $archivo = UploadedFile::fake()->create('catalogo.pdf', 10, 'application/pdf');

        $this->post('/api/v1/catalogo/importar', ['archivo' => $archivo], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }
}
