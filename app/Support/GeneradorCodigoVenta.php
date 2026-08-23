<?php

namespace App\Support;

use App\Models\Venta;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Genera el código de las ventas con formato VTA-2026-000123.
 *
 * El correlativo se reinicia cada año y se calcula sobre el máximo existente
 * incluyendo las anuladas: reutilizar el código de una venta anulada rompería
 * el histórico y el índice único lo rechazaría igualmente.
 */
class GeneradorCodigoVenta
{
    public const PREFIJO = 'VTA-';

    private const DIGITOS = 6;

    private const INTENTOS = 5;

    /**
     * Siguiente código libre. Es una previsualización: el definitivo lo fija
     * crearCon(), porque entre consultar y guardar otra caja puede haberlo
     * tomado.
     */
    public function siguiente(): string
    {
        return $this->formatear($this->ultimoCorrelativo() + 1);
    }

    /**
     * Crea la venta asignándole el siguiente código libre.
     *
     * La unicidad la garantiza el índice único de la columna, no esta lógica:
     * con dos cajas vendiendo a la vez, una recibe el duplicado y reintenta.
     *
     * @param  array<string, mixed>  $datos  Sin la clave 'codigo'
     */
    public function crearCon(array $datos): Venta
    {
        for ($intento = 1; $intento <= self::INTENTOS; $intento++) {
            try {
                return DB::transaction(fn (): Venta => Venta::create([
                    ...$datos,
                    'codigo' => $this->formatear($this->ultimoCorrelativo() + 1),
                ]));
            } catch (QueryException $e) {
                if (! $this->esCodigoDuplicado($e) || $intento === self::INTENTOS) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('No se pudo generar un código de venta.');
    }

    private function ultimoCorrelativo(): int
    {
        $prefijo = $this->prefijo();

        $maximo = Venta::query()
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
        return str_contains($e->getMessage(), 'ventas_codigo_unique')
            || (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), self::PREFIJO));
    }
}
