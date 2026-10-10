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

    /**
     * Minutos sin tocar la app del teléfono antes de cerrar la sesión. Diez
     * minutos dejan atender a un cliente sin que se cierre a medias, y no dejan
     * un teléfono olvidado en el mostrador con la caja abierta todo el día.
     */
    public const INACTIVIDAD_APP = 'app_inactividad_minutos';

    /** Las opciones que se ofrecen: un número suelto no tiene sentido aquí. */
    public const OPCIONES_INACTIVIDAD = [5, 10, 15, 20, 30, 60];

    /** Días en tienda a partir de los cuales un aparato entra a la lista amarilla. */
    public const LISTA_AMARILLA_DIAS = 'lista_amarilla_dias';

    private const POR_DEFECTO = [
        self::CAJA_OBLIGATORIA => '1',
        self::INACTIVIDAD_APP => '10',
        self::LISTA_AMARILLA_DIAS => '180',
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

    public function inactividadMinutos(): int
    {
        $minutos = (int) $this->valor(self::INACTIVIDAD_APP);

        return in_array($minutos, self::OPCIONES_INACTIVIDAD, true) ? $minutos : 10;
    }

    public function fijarInactividad(int $minutos, ?int $userId = null): void
    {
        if (! in_array($minutos, self::OPCIONES_INACTIVIDAD, true)) {
            throw new \InvalidArgumentException('Tiempo de inactividad no permitido.');
        }

        $this->guardar(self::INACTIVIDAD_APP, (string) $minutos, $userId);
    }

    public function listaAmarillaDias(): int
    {
        return max(30, (int) $this->valor(self::LISTA_AMARILLA_DIAS));
    }

    public function fijarListaAmarillaDias(int $dias, ?int $userId = null): void
    {
        $this->guardar(self::LISTA_AMARILLA_DIAS, (string) max(30, min(1095, $dias)), $userId);
    }

    /** Lo que la app necesita saber de la sesión al entrar. */
    public function paraLaApp(): array
    {
        return [
            'inactividad_minutos' => $this->inactividadMinutos(),
            'opciones_inactividad' => self::OPCIONES_INACTIVIDAD,
        ];
    }
}
