<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Unidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Escaparate público: la portada que ve quien llega sin sesión.
 *
 * Comprueba que se abre sin autenticación, que busca y filtra, que la ficha
 * pública responde y —lo más importante— que el costo nunca se asoma.
 */
class TiendaPublicaTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_tienda_se_abre_sin_sesion_y_muestra_el_catalogo(): void
    {
        $categoria = Categoria::factory()->create(['nombre' => 'Televisores', 'slug' => 'televisores']);
        Producto::factory()->create([
            'categoria_id' => $categoria->id,
            'nombre' => 'TelevisorQXR 55 pulgadas',
            'slug' => 'televisorqxr-55',
            'precio_venta' => 3500.00,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('TelevisorQXR 55 pulgadas')
            ->assertSee('Televisores');
    }

    public function test_la_busqueda_filtra_por_nombre(): void
    {
        Producto::factory()->create([
            'nombre' => 'LavadoraQXR Carga Frontal',
            'slug' => 'lavadoraqxr',
        ]);
        Producto::factory()->create([
            'nombre' => 'RefrigeradorQXR No Frost',
            'slug' => 'refrigeradorqxr',
        ]);

        $this->get('/?buscar=Lavadora')
            ->assertOk()
            ->assertSee('LavadoraQXR Carga Frontal')
            ->assertDontSee('RefrigeradorQXR No Frost');
    }

    public function test_filtra_por_categoria(): void
    {
        $audio = Categoria::factory()->create(['nombre' => 'Audio', 'slug' => 'audio']);
        $video = Categoria::factory()->create(['nombre' => 'Video', 'slug' => 'video']);

        Producto::factory()->create([
            'categoria_id' => $audio->id,
            'nombre' => 'ParlanteQXR Bluetooth',
            'slug' => 'parlanteqxr',
        ]);
        Producto::factory()->create([
            'categoria_id' => $video->id,
            'nombre' => 'ProyectorQXR 4K',
            'slug' => 'proyectorqxr',
        ]);

        $this->get('/?categoria=audio')
            ->assertOk()
            ->assertSee('ParlanteQXR Bluetooth')
            ->assertDontSee('ProyectorQXR 4K');
    }

    public function test_el_filtro_de_disponibles_esconde_lo_agotado(): void
    {
        $conStock = Producto::factory()->create([
            'nombre' => 'MicroondasQXR',
            'slug' => 'microondasqxr',
        ]);
        Unidad::factory()->deProducto($conStock)->create();

        Producto::factory()->create([
            'nombre' => 'AspiradoraQXR',
            'slug' => 'aspiradoraqxr',
        ]);

        $this->get('/?disponible=1')
            ->assertOk()
            ->assertSee('MicroondasQXR')
            ->assertDontSee('AspiradoraQXR');
    }

    public function test_los_productos_inactivos_no_salen_en_la_portada(): void
    {
        Producto::factory()->create([
            'nombre' => 'VentiladorQXR Oculto',
            'slug' => 'ventiladorqxr-oculto',
            'activo' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('VentiladorQXR Oculto');
    }

    public function test_la_ficha_del_producto_se_abre_por_slug_y_muestra_el_precio(): void
    {
        $producto = Producto::factory()->create([
            'nombre' => 'HornoQXR Electrico',
            'slug' => 'hornoqxr-electrico',
            'precio_venta' => 1234.56,
        ]);
        Unidad::factory()->deProducto($producto)->create();

        $this->get('/producto/hornoqxr-electrico')
            ->assertOk()
            ->assertSee('HornoQXR Electrico')
            ->assertSee('1.234,56');
    }

    public function test_la_ficha_no_expone_el_costo(): void
    {
        $producto = Producto::factory()->create([
            'nombre' => 'CongeladorQXR',
            'slug' => 'congeladorqxr',
            'precio_venta' => 4200.00,
        ]);
        Unidad::factory()->deProducto($producto)->create(['costo_unitario' => 987.65]);

        $this->get('/producto/congeladorqxr')
            ->assertOk()
            ->assertSee('4.200,00')
            ->assertDontSee('987,65');
    }

    public function test_un_producto_inactivo_da_404_en_su_ficha(): void
    {
        Producto::factory()->create([
            'nombre' => 'TostadoraQXR',
            'slug' => 'tostadoraqxr',
            'activo' => false,
        ]);

        $this->get('/producto/tostadoraqxr')->assertNotFound();
    }

    public function test_un_producto_de_categoria_oculta_da_404(): void
    {
        $categoria = Categoria::factory()->create(['activo' => false]);
        $producto = Producto::factory()->create([
            'categoria_id' => $categoria->id,
            'slug' => 'cafeteraqxr',
        ]);

        $this->get('/producto/'.$producto->slug)->assertNotFound();
    }
}
