<?php

namespace Tests\Unit;

use App\Support\Especificaciones;
use PHPUnit\Framework\TestCase;

/**
 * Normaliza lo que quedó en la antigua columna JSON de especificaciones.
 *
 * Conviven tres formatos según por dónde se guardara; esta clase los lleva
 * todos a filas `clave`/`valor`, que es lo que la tabla
 * `producto_especificaciones` espera.
 */
class EspecificacionesTest extends TestCase
{
    public function test_un_objeto_clave_valor_se_vuelve_filas(): void
    {
        $filas = Especificaciones::filasDesdeValor([
            'Sound technology' => 'Dolby Atmos',
            'Bluetooth' => true,
        ]);

        $this->assertSame([
            ['clave' => 'Sound technology', 'valor' => 'Dolby Atmos'],
            ['clave' => 'Bluetooth', 'valor' => null],
        ], $filas);
    }

    public function test_una_lista_de_pares_se_normaliza(): void
    {
        $filas = Especificaciones::filasDesdeValor([
            ['clave' => 'Pantalla', 'valor' => '55 pulgadas'],
            // Sin clave no hay nada que decir: se descarta.
            ['clave' => '', 'valor' => 'suelto'],
        ]);

        $this->assertSame([
            ['clave' => 'Pantalla', 'valor' => '55 pulgadas'],
        ], $filas);
    }

    public function test_un_string_doble_codificado_se_recupera(): void
    {
        // El seeder guardaba `json_encode([...])` sobre la columna con cast
        // `array`, que volvía a codificar: el valor quedó doblemente codificado
        // y un solo `json_decode` devuelve string.
        $doble = json_encode(json_encode([
            'Sound technology' => 'Dolby Atmos',
            'Display' => 'Mini-LED',
        ]));

        $filas = Especificaciones::filasDesdeValor($doble);

        $this->assertSame([
            ['clave' => 'Sound technology', 'valor' => 'Dolby Atmos'],
            ['clave' => 'Display', 'valor' => 'Mini-LED'],
        ], $filas);
    }

    public function test_un_string_plano_se_recupera(): void
    {
        $plano = json_encode([
            'Resolution' => '3840 x 2160',
            'Screen size' => '84.5 in',
        ]);

        $filas = Especificaciones::filasDesdeValor($plano);

        $this->assertSame([
            ['clave' => 'Resolution', 'valor' => '3840 x 2160'],
            ['clave' => 'Screen size', 'valor' => '84.5 in'],
        ], $filas);
    }

    public function test_un_valor_vacio_devuelve_vacio(): void
    {
        $this->assertSame([], Especificaciones::filasDesdeValor(null));
        $this->assertSame([], Especificaciones::filasDesdeValor(''));
        $this->assertSame([], Especificaciones::filasDesdeValor('[]'));
    }
}