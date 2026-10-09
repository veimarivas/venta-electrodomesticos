<?php

namespace App\Support;

use App\Models\Ajuste;

/**
 * Ajustes de la tienda que cambia el administrador desde el panel o la app.
 *
 * Cada ajuste tiene su valor por defecto aquí: lo que no está guardado vale
 * eso, así que una instalación nueva se porta igual que antes de existir el
 * ajuste.
 */
class Ajustes
{
    /**
     * ¿Hace falta un turno de caja abierto para vender?
     *
     * Por defecto sí, que es como funcionaba siempre. Apagado, se vende sin
     * abrir turno; la caja sigue disponible para quien quiera usarla, y el
     * control del día lo da el resumen diario de ingresos y gastos.
     */
    public const CAJA_OBLIGATORIA = 'caja_obligatoria';

    private const POR_DEFECTO = [
        self::CAJA_OBLIGATORIA => '1',
    ];

    /** Cache por petición: el POS pregunta varias veces en cada render. */
    private array $valores = [];

    public function valor(string $clave): ?string
    {
        if (! array_key_exists($clave, $this->valores)) {
            $guardado = Ajuste::query()->where('clave', $clave)->value('valor');

            $this->valores[$clave] = $guardado ?? (self::POR_DEFECTO[$clave] ?? null);
        }

        return $this->valores[$clave];
    }

    public function guardar(string $clave, ?string $valor, ?int $userId = null): void
    {
        Ajuste::query()->updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valor, 'user_id' => $userId],
        );

        $this->valores[$clave] = $valor;
    }

    public function cajaObligatoria(): bool
    {
        return $this->valor(self::CAJA_OBLIGATORIA) === '1';
    }

    public function exigirCaja(bool $exigir, ?int $userId = null): void
    {
        $this->guardar(self::CAJA_OBLIGATORIA, $exigir ? '1' : '0', $userId);
    }
}
