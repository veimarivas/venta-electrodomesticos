<?php

namespace Tests\Feature;

use App\Models\Unidad;
use App\Models\Producto;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Proveedor;
use App\Models\User;
use App\Support\GeneradorEtiquetas;
use App\Support\RecepcionDeCompra;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EtiquetaTest extends TestCase
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

    // ---- Generación del código de barras ----------------------------------

    public function test_genera_un_svg_incrustable_en_html(): void
    {
        $svg = app(GeneradorEtiquetas::class)->codigoDeBarras('TVSAM55-2608-0042');

        // La librería devuelve prólogo XML y DOCTYPE, que no pueden ir en
        // medio de un documento HTML: deben quedar recortados.
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringNotContainsString('<?xml', $svg);
        $this->assertStringNotContainsString('DOCTYPE', $svg);
        $this->assertStringContainsString('<rect', $svg);
    }

    public function test_el_codigo_de_barras_admite_letras_y_guiones(): void
    {
        // Code128 es obligatorio: el formato {SKU}-{AAMM}-{correlativo} tiene
        // letras y guiones, que EAN o UPC no aceptan.
        $svg = app(GeneradorEtiquetas::class)->codigoDeBarras('ABC-2608-0001');

        $this->assertStringContainsString('<rect', $svg);
    }

    public function test_el_codigo_de_barras_escala_en_vez_de_recortarse(): void
    {
        // Esta es la regresión que hacía ilegibles las etiquetas impresas: la
        // librería devuelve el SVG con medidas en píxeles y sin viewBox, y un
        // SVG sin viewBox no tiene proporción intrínseca, así que el
        // `width: 100%` de la hoja no lo escalaba: le RECORTABA el lienzo. El
        // código salía sin su última parte —dígito de control y patrón de
        // parada— y ningún lector podía leerlo.
        $svg = app(GeneradorEtiquetas::class)->codigoDeBarras('TVSAM55-2608-0042', 'pequena');

        $this->assertMatchesRegularExpression('/<svg[^>]*viewBox="/', $svg);
        $this->assertDoesNotMatchRegularExpression('/<svg[^>]*\swidth="\d/', $svg);
        $this->assertDoesNotMatchRegularExpression('/<svg[^>]*\sheight="\d/', $svg);
    }

    public function test_el_codigo_de_barras_reserva_sus_zonas_mudas(): void
    {
        // Code128 exige 10 módulos en blanco a cada lado. La librería dibuja
        // el patrón pegado al borde, así que el viewBox tiene que empezar
        // ANTES del 0 y acabar más allá del último trazo.
        $svg = app(GeneradorEtiquetas::class)->codigoDeBarras('TVSAM55-2608-0042', 'pequena');

        preg_match('/viewBox="(-?[\d.]+) 0 ([\d.]+) /', $svg, $caja);

        $this->assertNotEmpty($caja, 'El SVG debe traer un viewBox medible.');

        $inicio = (float) $caja[1];
        $ancho = (float) $caja[2];

        // Con módulo de 1 px en la etiqueta pequeña: 10 px a cada lado.
        $this->assertSame(-10.0, $inicio);

        // Último trazo dibujado + su zona muda.
        preg_match_all('/<rect x="([\d.]+)"[^>]*width="([\d.]+)"/', $svg, $barras);
        $finDelPatron = max(array_map(
            fn ($x, $w) => (float) $x + (float) $w,
            $barras[1],
            $barras[2]
        ));

        $this->assertSame($finDelPatron + 10.0, $ancho + $inicio);
    }

    public function test_cada_tamano_produce_un_codigo_distinto(): void
    {
        $generador = app(GeneradorEtiquetas::class);

        $pequena = $generador->codigoDeBarras('TVSAM55-2608-0042', 'pequena');
        $grande = $generador->codigoDeBarras('TVSAM55-2608-0042', 'grande');

        $this->assertNotSame($pequena, $grande);
    }

    public function test_un_tamano_desconocido_cae_en_el_mediano(): void
    {
        $generador = app(GeneradorEtiquetas::class);

        $this->assertSame(
            $generador->codigoDeBarras('ABC-2608-0001', 'mediana'),
            $generador->codigoDeBarras('ABC-2608-0001', 'inventado')
        );
    }

    // ---- Hoja de una compra -----------------------------------------------

    private function compraRecepcionada(int $cantidad = 3): Compra
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

    public function test_imprime_las_etiquetas_de_toda_una_compra(): void
    {
        $compra = $this->compraRecepcionada(3);

        $respuesta = $this->actingAs($this->admin())
            ->get(route('etiquetas.compra', $compra))
            ->assertOk();

        foreach (Unidad::pluck('codigo_interno') as $codigo) {
            $respuesta->assertSee($codigo);
        }

        $respuesta->assertSee($compra->codigo);
    }

    // ---- Hoja de unidades sueltas -----------------------------------------

    public function test_imprime_las_etiquetas_de_las_unidades_elegidas(): void
    {
        $this->compraRecepcionada(3);
        $unidades = Unidad::orderBy('id')->take(2)->get();

        $respuesta = $this->actingAs($this->admin())
            ->get(route('etiquetas.unidades', ['ids' => $unidades->pluck('id')->implode(',')]))
            ->assertOk();

        $respuesta->assertSee($unidades[0]->codigo_interno);
        $respuesta->assertSee($unidades[1]->codigo_interno);
        // La tercera no se pidió: no debe salir.
        $respuesta->assertDontSee(Unidad::orderBy('id')->skip(2)->first()->codigo_interno);
    }

    /**
     * Cuenta las etiquetas contando sus bloques renderizados. Buscar el texto
     * "6 etiquetas" no sirve: Blade lo parte en varias líneas.
     */
    private function contarEtiquetas(string $html): int
    {
        return substr_count($html, 'class="etiqueta-codigo-texto"');
    }

    public function test_las_copias_multiplican_las_etiquetas(): void
    {
        $this->compraRecepcionada(2);
        $ids = Unidad::pluck('id')->implode(',');

        $respuesta = $this->actingAs($this->admin())
            ->get(route('etiquetas.unidades', ['ids' => $ids, 'copias' => 3]))
            ->assertOk();

        // 2 unidades x 3 copias
        $this->assertSame(6, $this->contarEtiquetas($respuesta->getContent()));
    }

    public function test_las_copias_estan_acotadas(): void
    {
        // Un typo en la URL no debe intentar generar miles de etiquetas.
        $this->compraRecepcionada(1);
        $ids = Unidad::pluck('id')->implode(',');

        $respuesta = $this->actingAs($this->admin())
            ->get(route('etiquetas.unidades', ['ids' => $ids, 'copias' => 9999]))
            ->assertOk();

        $this->assertSame(5, $this->contarEtiquetas($respuesta->getContent()));
    }

    public function test_ignora_ids_repetidos_y_vacios(): void
    {
        $this->compraRecepcionada(2);
        $primera = Unidad::orderBy('id')->first();

        $respuesta = $this->actingAs($this->admin())
            ->get(route('etiquetas.unidades', ['ids' => "{$primera->id},,{$primera->id}, "]))
            ->assertOk();

        $this->assertSame(1, $this->contarEtiquetas($respuesta->getContent()));
    }

    public function test_sin_ids_validos_devuelve_404(): void
    {
        $this->actingAs($this->admin())
            ->get(route('etiquetas.unidades', ['ids' => '999999']))
            ->assertNotFound();
    }

    public function test_los_ids_son_obligatorios(): void
    {
        $this->actingAs($this->admin())
            ->get(route('etiquetas.unidades'))
            ->assertSessionHasErrors('ids');
    }

    // ---- Permisos ---------------------------------------------------------

    public function test_hace_falta_permiso_para_ver_las_etiquetas(): void
    {
        $compra = $this->compraRecepcionada(1);
        $sinPermiso = User::factory()->create();

        $this->actingAs($sinPermiso)
            ->get(route('etiquetas.compra', $compra))
            ->assertForbidden();

        $this->actingAs($sinPermiso)
            ->get(route('etiquetas.unidades', ['ids' => Unidad::first()->id]))
            ->assertForbidden();
    }

    public function test_un_invitado_no_entra(): void
    {
        $compra = $this->compraRecepcionada(1);

        $this->get(route('etiquetas.compra', $compra))->assertRedirect('/login');
    }
}
