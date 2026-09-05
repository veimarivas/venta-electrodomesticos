<?php

namespace App\Support;

use App\Models\Unidad;
use App\Models\Producto;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Genera el código interno de cada unidad física (unidades) con el formato
 * P{id}-{AAMM}-{correlativo} (ej. P001-2608-0042).
 *
 * El correlativo se calcula por producto y mes sobre el máximo existente,
 * incluyendo los registros archivados (softDeletes), para no reutilizar
 * códigos que romperían el histórico. La unicidad la garantiza el índice
 * único de la columna, no esta lógica: ante una colisión concurrente se
 * reintenta con el número siguiente.
 */
class GeneradorCodigoUnidad
{
    private const DIGITOS = 4;

    /** Cuántas veces se reintenta si otro proceso tomó el mismo número. */
    private const INTENTOS = 5;

    /**
     * Siguiente código libre para el producto. Es solo una previsualización:
     * el valor definitivo lo fija crearCon().
     */
    public function siguiente(Producto $producto): string
    {
        return $this->formatear($producto->id, $this->ultimoCorrelativo($producto->id) + 1);
    }

    /**
     * Crea la unidad asignándole el siguiente código libre.
     *
     * @param  array<string, mixed>  $datos  Sin la clave 'codigo_interno'
     */
    public function crearCon(array $datos): Unidad
    {
        $productoId = $datos['producto_id'];

        for ($intento = 1; $intento <= self::INTENTOS; $intento++) {
            try {
                return DB::transaction(fn (): Unidad => Unidad::create([
                    ...$datos,
                    'codigo_interno' => $this->formatear($productoId, $this->ultimoCorrelativo($productoId) + 1),
                ]));
            } catch (QueryException $e) {
                // Solo se reintenta ante una colisión del código interno;
                // cualquier otro fallo de base de datos debe propagarse.
                if (! $this->esCodigoDuplicado($e) || $intento === self::INTENTOS) {
                    throw $e;
                }
            }
        }

        // Inalcanzable: el bucle retorna o lanza. Está para satisfacer el tipo.
        throw new \RuntimeException('No se pudo generar un código interno de unidad.');
    }

    /**
     * Mayor correlativo usado para este producto y mes, 0 si aún no hay
     * unidades con ese prefijo.
     */
    private function ultimoCorrelativo(int $productoId): int
    {
        $prefijo = $this->prefijo($productoId);
        $maximo = Unidad::withTrashed()
            ->where('codigo_interno', 'like', $prefijo.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(codigo_interno, ?) AS UNSIGNED)) as maximo', [strlen($prefijo) + 1])
            ->value('maximo');

        return (int) $maximo;
    }

    private function prefijo(int $productoId): string
    {
        return 'P'.str_pad((string) $productoId, 3, '0', STR_PAD_LEFT).'-'.now()->format('ym').'-';
    }

    private function formatear(int $productoId, int $correlativo): string
    {
        return $this->prefijo($productoId).str_pad((string) $correlativo, self::DIGITOS, '0', STR_PAD_LEFT);
    }

    /**
     * ¿El fallo viene del índice único de 'codigo_interno'?
     */
    private function esCodigoDuplicado(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'items_internal_code_unique')
            || (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), '-'));
    }
}
