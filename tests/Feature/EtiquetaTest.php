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

    public function test_el_codigo_generado_se_puede_decodificar(): void
    {
        // Regresión del día que la etiqueta se imprimía cortada: un Code128
        // truncado no lo lee ningún lector. Aquí se decodifica el SVG final de
        // verdad y se exige que dé EXACTAMENTE el código interno, checksum y
        // patrón de parada incluidos.
        $generador = app(GeneradorEtiquetas::class);

        foreach (['P001-2608-0042', 'TVSAM55-2609-0007', 'ABC-123-0001'] as $codigo) {
            foreach (['pequena', 'mediana', 'grande'] as $tamano) {
                $decodificado = $this->decodificarCode128(
                    $generador->codigoDeBarras($codigo, $tamano)
                );

                $this->assertSame(
                    strtoupper($codigo),
                    strtoupper($decodificado),
                    "Fallo decodificando {$codigo} ({$tamano})"
                );
            }
        }
    }

    /**
     * Decodifica el patrón de barras de un SVG Code128 a mano.
     *
     * Extrae los <rect> (solo barras), reconstruye la secuencia de módulos
     * barra/espacio, la recorre contra la tabla de Code128 y devuelve el texto.
     * Verifica además el checksum y la terminación.
     */
    private function decodificarCode128(string $svg): string
    {
        preg_match_all('/<rect x="([\d.]+)"[^>]*width="([\d.]+)"/', $svg, $m, PREG_SET_ORDER);

        $modulos = [];
        $prev = 0.0;
        $primero = true;

        foreach ($m as $barra) {
            $x = (float) $barra[1];
            $w = (float) $barra[2];

            if ($w <= 0.001) {
                continue; // la librería cierra con rects de ancho 0
            }

            if ($primero) {
                $modulos[] = $w;
                $primero = false;
            } else {
                $hueco = $x - $prev;
                if ($hueco > 0.001) {
                    $modulos[] = $hueco;
                }
                $modulos[] = $w;
            }

            $prev = $x + $w;
        }

        $unidad = min($modulos);
        $secuencia = array_map(fn ($v): int => (int) round($v / $unidad), $modulos);

        // Tabla de patrones de Code128 (valores 0..106).
        $patrones = [
            [2,1,2,2,2,2],[2,2,2,1,2,2],[2,2,2,2,2,1],[1,2,1,2,2,3],[1,2,1,3,2,2],[1,3,1,2,2,2],
            [1,2,2,2,1,3],[1,2,2,3,1,2],[1,3,2,2,1,2],[2,2,1,2,1,3],[2,2,1,3,1,2],[2,3,1,2,1,2],
            [1,1,2,2,3,2],[1,2,2,1,3,2],[1,2,2,2,3,1],[1,1,3,2,2,2],[1,2,3,1,2,2],[1,2,3,2,2,1],
            [2,2,3,2,1,1],[2,2,1,1,3,2],[2,2,1,2,3,1],[2,1,3,2,1,2],[2,2,3,1,1,2],[3,1,2,1,3,1],
            [3,1,1,2,2,2],[3,2,1,1,2,2],[3,2,1,2,2,1],[3,1,2,2,1,2],[3,2,2,1,1,2],[3,2,2,2,1,1],
            [2,1,2,1,2,3],[2,1,2,3,2,1],[2,3,2,1,2,1],[1,1,1,3,2,3],[1,3,1,1,2,3],[1,3,1,3,2,1],
            [1,1,2,3,1,3],[1,3,2,1,1,3],[1,3,2,3,1,1],[2,1,1,3,1,3],[2,3,1,1,1,3],[2,3,1,3,1,1],
            [1,1,2,1,3,3],[1,1,2,3,3,1],[1,3,2,1,3,1],[1,1,3,1,2,3],[1,1,3,3,2,1],[1,3,3,1,2,1],
            [3,1,3,1,2,1],[2,1,1,3,3,1],[2,3,1,1,3,1],[2,1,3,1,1,3],[2,1,3,3,1,1],[2,1,3,1,3,1],
            [3,1,1,1,2,3],[3,1,1,3,2,1],[3,3,1,1,2,1],[3,1,2,1,1,3],[3,1,2,3,1,1],[3,3,2,1,1,1],
            [3,1,4,1,1,1],[2,2,1,4,1,1],[4,3,1,1,1,1],[1,1,1,2,2,4],[1,1,1,4,2,2],[1,2,1,1,2,4],
            [1,2,1,4,2,1],[1,4,1,1,2,2],[1,4,1,2,2,1],[1,1,2,2,1,4],[1,1,2,4,1,2],[1,2,2,1,1,4],
            [1,2,2,4,1,1],[1,4,2,1,1,2],[1,4,2,2,1,1],[2,4,1,2,1,1],[2,2,1,1,1,4],[4,1,3,1,1,1],
            [2,4,1,1,1,2],[1,3,4,1,1,1],[1,1,1,2,4,2],[1,2,1,1,4,2],[1,2,1,2,4,1],[1,1,4,2,1,2],
            [1,2,4,1,1,2],[1,2,4,2,1,1],[4,1,1,2,1,2],[4,2,1,1,1,2],[4,2,1,2,1,1],[2,1,2,1,4,1],
            [2,1,4,1,2,1],[4,1,2,1,2,1],[1,1,1,1,4,3],[1,1,1,3,4,1],[1,3,1,1,4,1],[1,1,4,1,1,3],
            [1,1,4,3,1,1],[4,1,1,1,1,3],[4,1,1,3,1,1],[1,1,3,1,4,1],[1,1,4,1,3,1],[3,1,1,1,4,1],
            [4,1,1,1,3,1],[2,1,1,4,1,2],[2,1,1,2,1,4],[2,1,1,2,3,2],[2,3,3,1,1,1],
        ];

        $CSA = 101; $CSB = 100; $CSC = 99;
        $START_A = 103; $START_B = 104; $START_C = 105; $STOP = 106;

        $coincide = fn (array $a, array $b): bool => $a === $b;

        // Recorrer la secuencia de módulos de 6 en 6 buscando símbolos.
        $valores = [];
        $i = 0;
        $n = count($secuencia);

        while ($i <= $n - 6) {
            $ventana = array_slice($secuencia, $i, 6);
            $encontrado = null;

            foreach ($patrones as $valor => $patron) {
                if ($coincide($ventana, $patron)) {
                    $encontrado = $valor;
                    break;
                }
            }

            if ($encontrado === null) {
                break;
            }

            $valores[] = $encontrado;
            $i += 6;
        }

        $this->assertNotEmpty($valores, 'El SVG no produce ningún símbolo Code128.');
        $this->assertContains($valores[0], [$START_A, $START_B, $START_C], 'No arranca con un START.');

        // Verificar la terminación: el patrón de parada debe cerrar completo.
        $indiceStop = array_search($STOP, $valores, true);
        $this->assertNotFalse($indiceStop, 'Falta el patrón de parada.');
        $this->assertGreaterThan(2, $indiceStop, 'El patrón de parada llega demasiado pronto.');

        // El símbolo inmediatamente anterior al STOP es el checksum; se
        // comprueba y NO se cuenta como dato. La norma pondera TAMBIÉN los
        // códigos de cambio de conjunto (CODE A/B/C), como hace la librería.
        $checksum = $valores[$indiceStop - 1];
        $suma = $valores[0];

        foreach (array_slice($valores, 1, $indiceStop - 2) as $posicion => $valor) {
            $suma = ($suma + ($posicion + 1) * $valor) % 103;
        }

        $this->assertSame(
            $suma,
            $checksum,
            'El checksum del Code128 no coincide: la etiqueta no es legible.'
        );

        // Decodificar datos (con cambio de conjunto A/B/C).
        $texto = '';
        $modo = $valores[0];

        foreach (array_slice($valores, 1, $indiceStop - 2) as $valor) {
            if (in_array($valor, [$CSA, $CSB, $CSC], true)) {
                $modo = match ($valor) {
                    $CSA => $START_A,
                    $CSB => $START_B,
                    default => $START_C,
                };

                continue;
            }

            if ($modo === $START_C) {
                $texto .= str_pad((string) $valor, 2, '0', STR_PAD_LEFT);
            } else {
                $texto .= chr($valor + 32);
            }
        }

        return $texto;
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
