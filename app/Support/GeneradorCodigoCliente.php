<?php

namespace App\Support;

use App\Models\Cliente;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Genera el código correlativo de los clientes con formato CLI-0001.
 *
 * Mismo criterio que GeneradorCodigoTrabajador: el correlativo se calcula
 * sobre el máximo existente incluyendo los registros archivados
 * (softDeletes), porque reutilizar el código de un cliente archivado rompería
 * el histórico de ventas y chocaría con el índice único.
 */
class GeneradorCodigoCliente
{
    public const PREFIJO = 'CLI-';

    private const DIGITOS = 4;

    /** Cuántas veces se reintenta si otro proceso tomó el mismo número. */
    private const INTENTOS = 5;

    /**
     * Siguiente código libre. Es solo una previsualización: entre consultarlo
     * y guardar, otro usuario puede haber tomado ese número. El valor
     * definitivo lo fija crearCon().
     */
    public function siguiente(): string
    {
        return $this->formatear($this->ultimoCorrelativo() + 1);
    }

    /**
     * Crea el cliente asignándole el siguiente código libre.
     *
     * La unicidad la garantiza el índice único de la columna, no esta lógica:
     * si dos usuarios registran a la vez, uno recibe el error de duplicado y
     * se reintenta con el número siguiente.
     *
     * @param  array<string, mixed>  $datos  Sin la clave 'codigo'
     */
    public function crearCon(array $datos): Cliente
    {
        for ($intento = 1; $intento <= self::INTENTOS; $intento++) {
            try {
                return DB::transaction(fn (): Cliente => Cliente::create([
                    ...$datos,
                    'codigo' => $this->formatear($this->ultimoCorrelativo() + 1),
                ]));
            } catch (QueryException $e) {
                // Solo se reintenta ante una colisión del código; cualquier
                // otro fallo de base de datos debe propagarse tal cual.
                if (! $this->esCodigoDuplicado($e) || $intento === self::INTENTOS) {
                    throw $e;
                }
            }
        }

        // Inalcanzable: el bucle retorna o lanza. Está para satisfacer el tipo.
        throw new \RuntimeException('No se pudo generar un código de cliente.');
    }

    /**
     * Mayor correlativo usado hasta ahora, 0 si aún no hay clientes.
     */
    private function ultimoCorrelativo(): int
    {
        $maximo = Cliente::withTrashed()
            ->where('codigo', 'like', self::PREFIJO.'%')
            ->selectRaw('MAX(CAST(SUBSTRING(codigo, ?) AS UNSIGNED)) as maximo', [strlen(self::PREFIJO) + 1])
            ->value('maximo');

        return (int) $maximo;
    }

    private function formatear(int $correlativo): string
    {
        return self::PREFIJO.str_pad((string) $correlativo, self::DIGITOS, '0', STR_PAD_LEFT);
    }

    /**
     * ¿El fallo viene del índice único de 'codigo'?
     */
    private function esCodigoDuplicado(Throwable $e): bool
    {
        return str_contains($e->getMessage(), 'clientes_codigo_unique')
            || (str_contains($e->getMessage(), 'Duplicate entry') && str_contains($e->getMessage(), self::PREFIJO));
    }
}
