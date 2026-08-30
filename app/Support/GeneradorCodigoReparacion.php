<?php

namespace App\Support;

use App\Models\Reparacion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Genera el código de las órdenes de taller con formato REP-2026-000123.
 *
 * Misma forma que `GeneradorCodigoVenta` y por lo mismo: el correlativo se
 * reinicia cada año y se calcula sobre el máximo existente, pero **la unicidad
 * la garantiza el índice único**, no esta lógica. Con dos personas recibiendo
 * aparatos a la vez, una recibe el duplicado y reintenta.
 */
class GeneradorCodigoReparacion
{
    public const PREFIJO = 'REP-';

    private const DIGITOS = 6;

    private const INTENTOS = 5;

    /**
     * Crea la orden asignándole el siguiente código libre.
     *
     * @param  array<string, mixed>  $datos  Sin la clave 'codigo'
     */
    public function crearCon(array $datos): Reparacion
    {
        for ($intento = 1; $intento <= self::INTENTOS; $intento++) {
            try {
                return DB::transaction(fn (): Reparacion => Reparacion::create([
                    ...$datos,
                    'codigo' => $this->formatear($this->ultimoCorrelativo() + 1),
                ]));
            } catch (QueryException $e) {
                if (! $this->esCodigoDuplicado($e) || $intento === self::INTENTOS) {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('No se pudo generar un código de reparación.');
    }

    private function ultimoCorrelativo(): int
    {
        $prefijo = $this->prefijo();

        $maximo = Reparacion::query()
            ->where('codigo', 'like', $prefijo.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(codigo, ?) AS UNSIGNED)) as maximo', [strlen($prefijo) + 1])
            ->value('maximo');

        return (int) $maximo;
    }

    private function prefijo(): string
    {
        return self::PREFIJO.now()->format('Y').'-';
    }

    private function formatear(int $correlativo): string
    {
        return $this->prefijo().str_pad((string) $correlativo, self::DIGITOS, '0', STR_PAD_LEFT);
    }

    private function esCodigoDuplicado(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'reparaciones_codigo_unique')
            || (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), self::PREFIJO));
    }
}
