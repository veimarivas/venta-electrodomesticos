<?php

namespace App\Support;

/**
 * Normaliza el valor de especificaciones de un producto a filas clave/valor.
 *
 * Existía una columna JSON en `productos` donde convivieron tres formatos
 * según por dónde se guardara, y uno de ellos rompía la edición:
 *
 * - objeto  `{clave: valor}` (panel, `true` = distintivo sin valor);
 * - lista   `[{clave, valor}, ...]` (app);
 * - string  `"{clave: valor}"` (un `json_encode` de más en un seeder, que con
 *   el cast `array` quedaba doblemente codificado).
 *
 * Ahora las especificaciones viven en `producto_especificaciones`; esta clase
 * solo se usa para migrar lo que había y para mantener el formato estable.
 */
class Especificaciones
{
    /**
     * @return array<int, array{clave: string, valor: string|null}>
     */
    public static function filasDesdeValor(mixed $valor): array
    {
        $decodificado = is_string($valor) ? json_decode($valor, true) : $valor;

        // Doble codificación (el seeder): el primer `json_decode` devuelve un
        // string, no un array. Se decodifica otra vez.
        if (is_string($decodificado)) {
            $decodificado = json_decode($decodificado, true);
        }

        if (! is_array($decodificado) || $decodificado === []) {
            return [];
        }

        $filas = [];

        // Lista de pares (`[{clave, valor}]`), lo que manda la app.
        if (array_is_list($decodificado)) {
            foreach ($decodificado as $fila) {
                if (! is_array($fila) || trim((string) ($fila['clave'] ?? '')) === '') {
                    continue;
                }

                $filas[] = self::fila((string) $fila['clave'], $fila['valor'] ?? null);
            }

            return $filas;
        }

        // Objeto `{clave: valor}`.
        foreach ($decodificado as $clave => $valor) {
            $filas[] = self::fila((string) $clave, $valor);
        }

        return $filas;
    }

    /**
     * @return array{clave: string, valor: string|null}
     */
    private static function fila(string $clave, mixed $valor): array
    {
        $clave = trim($clave);

        // `true` era la bandera de «característica sin valor».
        if ($valor === true || $valor === null || $valor === '') {
            return ['clave' => $clave, 'valor' => null];
        }

        return ['clave' => $clave, 'valor' => trim((string) $valor)];
    }
}