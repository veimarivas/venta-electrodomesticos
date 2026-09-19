<?php

namespace Tests\Unit;

use App\Support\Excel\EscritorXlsx;
use App\Support\Excel\LectorXlsx;
use PHPUnit\Framework\TestCase;

/**
 * El lector y el escritor de `.xlsx` son la base de la carga masiva: si el
 * archivo que se descarga no se puede volver a leer, la función no sirve.
 */
class ExcelXlsxTest extends TestCase
{
    private string $ruta = '';

    protected function tearDown(): void
    {
        if ($this->ruta !== '' && is_file($this->ruta)) {
            unlink($this->ruta);
        }

        parent::tearDown();
    }

    private function escribir(array $hojas): void
    {
        $this->ruta = tempnam(sys_get_temp_dir(), 'xlsx').'.xlsx';
        file_put_contents($this->ruta, (new EscritorXlsx)->generar($hojas));
    }

    public function test_lo_escrito_se_puede_volver_a_leer(): void
    {
        $this->escribir([
            ['nombre' => 'Categorias', 'filas' => [['Nombre', 'Padre'], ['Audio', ''], ['Parlantes', 'Audio']]],
            ['nombre' => 'Productos', 'filas' => [['Nombre', 'Precio'], ['Parlante', 250.5]]],
        ]);

        $hojas = (new LectorXlsx)->leer($this->ruta);

        $this->assertSame(['Categorias', 'Productos'], array_keys($hojas));
        $this->assertSame(['Nombre', 'Padre'], $hojas['Categorias'][0]);
        $this->assertSame(['Parlantes', 'Audio'], $hojas['Categorias'][2]);
        $this->assertSame(['Parlante', '250.5'], $hojas['Productos'][1]);
    }

    public function test_conserva_acentos_y_caracteres_xml(): void
    {
        $this->escribir([
            ['nombre' => 'Hoja', 'filas' => [['Electrónica áéíóú ñ "comillas" & <xml>']]],
        ]);

        $hojas = (new LectorXlsx)->leer($this->ruta);

        $this->assertSame('Electrónica áéíóú ñ "comillas" & <xml>', $hojas['Hoja'][0][0]);
    }

    public function test_una_celda_vacia_no_desplaza_a_las_de_su_derecha(): void
    {
        $this->escribir([
            ['nombre' => 'Hoja', 'filas' => [['A', '', 'C']]],
        ]);

        $fila = (new LectorXlsx)->leer($this->ruta)['Hoja'][0];

        // El hueco de la columna B queda como cadena vacía, no se compacta.
        $this->assertSame(['A', '', 'C'], $fila);
    }
}
