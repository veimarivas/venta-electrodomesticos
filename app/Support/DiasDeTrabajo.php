<?php

namespace App\Support;

use App\Models\DiaDeTrabajo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Calendario de trabajo del mes, por vendedor.
 *
 * Se guarda el mes entero de una vez: lo que se marca queda, lo que se
 * desmarca se borra. Así el calendario de la pantalla y el de la base nunca
 * difieren, y no hay que pensar en «agregar» y «quitar» por separado.
 */
class DiasDeTrabajo
{
    /** Días de la semana de un turno habitual: lunes a sábado. */
    public const LUNES_A_SABADO = [1, 2, 3, 4, 5, 6];

    /**
     * Fechas programadas del mes (Y-m-d), ordenadas.
     *
     * @return list<string>
     */
    public function delMes(int $userId, int $anio, int $mes): array
    {
        return DiaDeTrabajo::query()
            ->where('user_id', $userId)
            ->delMes($anio, $mes)
            ->orderBy('fecha')
            ->pluck('fecha')
            ->map(fn (Carbon $f): string => $f->toDateString())
            ->all();
    }

    /**
     * Cuántos días tiene programados cada usuario en el mes.
     *
     * @return array<int, int> user_id => días
     */
    public function cantidadesDelMes(int $anio, int $mes): array
    {
        return DiaDeTrabajo::query()
            ->delMes($anio, $mes)
            ->selectRaw('user_id, count(*) as dias')
            ->groupBy('user_id')
            ->pluck('dias', 'user_id')
            ->map(fn ($d): int => (int) $d)
            ->all();
    }

    /**
     * Reemplaza los días del mes por los indicados.
     *
     * @param  array<int, string>  $fechas  Fechas Y-m-d; las de otro mes se rechazan.
     * @return list<string>  Lo que quedó guardado.
     *
     * @throws RuntimeException  Si alguna fecha no es del mes.
     */
    public function guardar(User $vendedor, int $anio, int $mes, array $fechas, ?User $quien = null): array
    {
        $limpias = collect($fechas)
            ->map(fn (string $f): Carbon => Carbon::parse($f)->startOfDay())
            ->each(function (Carbon $f) use ($anio, $mes): void {
                if ($f->year !== $anio || $f->month !== $mes) {
                    throw new RuntimeException("La fecha {$f->toDateString()} no es de este mes.");
                }
            })
            ->map(fn (Carbon $f): string => $f->toDateString())
            ->unique()
            ->sort()
            ->values();

        DB::transaction(function () use ($vendedor, $anio, $mes, $limpias, $quien): void {
            DiaDeTrabajo::query()
                ->where('user_id', $vendedor->id)
                ->delMes($anio, $mes)
                ->whereNotIn('fecha', $limpias->all())
                ->delete();

            $ya = DiaDeTrabajo::query()
                ->where('user_id', $vendedor->id)
                ->delMes($anio, $mes)
                ->pluck('fecha')
                ->map(fn (Carbon $f): string => $f->toDateString())
                ->all();

            $ahora = now();

            DiaDeTrabajo::query()->insert(
                $limpias->diff($ya)->map(fn (string $f): array => [
                    'user_id' => $vendedor->id,
                    'fecha' => $f,
                    'programado_por' => $quien?->id,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ])->values()->all()
            );
        });

        return $this->delMes($vendedor->id, $anio, $mes);
    }

    /**
     * Los días de la semana indicados, en todo el mes (1 = lunes … 7 = domingo).
     *
     * @param  array<int, int>  $diasDeLaSemana
     * @return list<string>
     */
    public function patron(int $anio, int $mes, array $diasDeLaSemana = self::LUNES_A_SABADO): array
    {
        $dia = Carbon::create($anio, $mes, 1);
        $fechas = [];

        while ($dia->month === $mes) {
            if (in_array($dia->dayOfWeekIso, $diasDeLaSemana, true)) {
                $fechas[] = $dia->toDateString();
            }

            $dia->addDay();
        }

        return $fechas;
    }

    /**
     * El calendario del mes anterior llevado a este: los mismos días de la
     * semana que trabajó (el patrón, no las fechas, que no coinciden).
     *
     * @return list<string>
     */
    public function comoElMesAnterior(int $userId, int $anio, int $mes): array
    {
        $anterior = Carbon::create($anio, $mes, 1)->subMonth();

        $semana = collect($this->delMes($userId, $anterior->year, $anterior->month))
            ->map(fn (string $f): int => Carbon::parse($f)->dayOfWeekIso)
            ->unique()
            ->values()
            ->all();

        return $semana === [] ? [] : $this->patron($anio, $mes, $semana);
    }
}
