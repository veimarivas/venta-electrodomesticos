<?php

use App\Support\Especificaciones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las especificaciones pasan de una columna JSON en `productos` a una tabla
 * propia. La columna JSON era frágil: convivían tres formatos según por dónde
 * se guardara (objeto `{clave: valor}` del panel, lista de pares del teléfono,
 * y un string JSON de más por un `json_encode` en un seeder), y al leerla el
 * panel mostraba «0» de clave con todo el JSON pegado en el valor.
 *
 * La tabla normaliza todo: una fila por característica, en orden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_especificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->string('clave', 60);
            $table->string('valor', 200)->nullable();
            // Orden en que se registraron; NULL en `valor` = distintivo
            // («Bluetooth»), que antes se guardaba como `true`.
            $table->unsignedInteger('posicion')->default(0);
            $table->timestamps();

            $table->index(['producto_id', 'posicion']);
        });

        $this->migrarDatos();

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('especificaciones');
        });
    }

    /**
     * Copia lo que había en la columna JSON a filas, tolerando los tres
     * formatos con que se guardó a lo largo del tiempo:
     *
     * - objeto  `{clave: valor}` (panel, `true` = distintivo sin valor);
     * - lista   `[{clave, valor}, ...]` (app);
     * - string  `"{clave: valor}"` (json_encode de más en un seeder).
     */
    private function migrarDatos(): void
    {
        $productos = DB::table('productos')
            ->whereNotNull('especificaciones')
            ->select('id', 'especificaciones')
            ->get();

        foreach ($productos as $producto) {
            $filas = Especificaciones::filasDesdeValor($producto->especificaciones);

            if ($filas === []) {
                continue;
            }

            $posicion = 0;

            foreach ($filas as $fila) {
                DB::table('producto_especificaciones')->insert([
                    'producto_id' => $producto->id,
                    'clave' => $fila['clave'],
                    'valor' => $fila['valor'],
                    'posicion' => $posicion++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->json('especificaciones')->nullable()->after('descripcion');
        });

        // Vuelve a armar el objeto JSON desde las filas.
        $especificaciones = DB::table('producto_especificaciones')
            ->orderBy('posicion')
            ->get();

        $porProducto = [];

        foreach ($especificaciones as $e) {
            $porProducto[$e->producto_id][$e->clave] = $e->valor ?? true;
        }

        foreach ($porProducto as $productoId => $mapa) {
            DB::table('productos')
                ->where('id', $productoId)
                ->update(['especificaciones' => json_encode($mapa)]);
        }

        Schema::dropIfExists('producto_especificaciones');
    }
};