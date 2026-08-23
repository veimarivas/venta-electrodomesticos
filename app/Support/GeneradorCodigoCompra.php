<?php

namespace App\Support;

use App\Models\Compra;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Genera el código de las compras con formato COM-2026-0001.
 *
 * El correlativo se reinicia cada año y se calcula sobre el máximo existente
 * incluyendo las archivadas: reutilizar el código de una compra anulada
 * confundiría el histórico de costos.
 */
class GeneradorCodigoCompra
{
    public const PREFIJO = 'COM-';

    private const DIGITOS = 4;

    private const INTENTOS = 5;

    /**
     * Siguiente código libre. Es una previsualización: el definitivo lo fija
     * crearCon(), porque entre consultar y guardar otro usuario puede haberlo
     * tomado.
     */
    public function siguiente(): string
    {
        return $this->formatear($this->ultimoCorrelativo() + 1);
    }

    /**
     * Crea la compra asignándole el siguiente código libre.
     *
     * @param  array<string, mixed>  $datos  Sin la clave 'codigo'
     */
    public function crearCon(array $datos): Compra
    {
        for ($intento = 1; $intento <= self::INTENTOS; $intento++) {
            try {
                return DB::transaction(fn (): Compra => Compra::create([
                    ...$datos,
                    'codigo' => $this->formatear($this->ultimoCorrelativo() + 1),
                ]));
            } catch (QueryException $e) {
                if (! $this->esCodigoDuplicado($e) || $intento === self::INTENTOS) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('No se pudo generar un código de compra.');
    }

    private function ultimoCorrelativo(): int
    {
        $prefijo = $this->prefijo();

        $maximo = Compra::withTrashed()
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
        return str_contains($e->getMessage(), 'purchases_code_unique')
            || (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), self::PREFIJO));
    }
}
