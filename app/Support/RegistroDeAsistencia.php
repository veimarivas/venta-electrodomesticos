<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\Tienda;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Marcar entrada y salida dentro de la tienda, y el historial del mes.
 *
 * El teléfono manda dónde está (latitud, longitud y la precisión que da el
 * GPS) y si la ubicación es simulada; **el servidor vuelve a medir** la
 * distancia a cada tienda y decide. La app enseña la distancia en vivo y
 * apaga el botón fuera del radio, pero la regla vale aquí: un teléfono
 * modificado no se salta nada mandando otra cosa.
 *
 * Reglas:
 * - Entrada: dentro del radio de **alguna** tienda activa (se vende en
 *   cualquiera) y sin otro turno abierto hoy.
 * - Salida: dentro del radio de **la misma** tienda donde entró.
 * - Ubicación simulada (apps de «GPS falso») o precisión peor que 50 m: no se
 *   marca. Con una señal tan mala el punto puede estar en la calle de al lado.
 * - Atraso: en la primera entrada del día, si la tienda tiene hora de entrada
 *   y se llega pasada la tolerancia, se anotan los minutos desde esa hora.
 */
class RegistroDeAsistencia
{
    /** Peor precisión aceptada, en metros. */
    public const PRECISION_MAXIMA = 50;

    /** «8 h 05 min», «45 min», «—». */
    public static function horas(?int $minutos): string
    {
        if ($minutos === null) {
            return '—';
        }

        $h = intdiv($minutos, 60);
        $m = $minutos % 60;

        return $h === 0 ? "{$m} min" : sprintf('%d h %02d min', $h, $m);
    }

    /** Distancia en metros entre dos puntos (fórmula del haversine). */
    public static function distancia(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $radio = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $radio * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * La tienda más cercana a un punto y si se está dentro de su radio.
     *
     * @return array{tienda: ?Tienda, distancia: ?int, dentro: bool}
     */
    public function masCercana(float $lat, float $lng): array
    {
        $mejor = null;
        $distancia = null;

        foreach (Tienda::query()->paraMarcar()->get() as $tienda) {
            $d = self::distancia($lat, $lng, $tienda->latitud, $tienda->longitud);

            if ($distancia === null || $d < $distancia) {
                [$mejor, $distancia] = [$tienda, $d];
            }
        }

        return [
            'tienda' => $mejor,
            'distancia' => $distancia === null ? null : (int) round($distancia),
            'dentro' => $mejor !== null && $distancia <= $mejor->radio_metros,
        ];
    }

    /** El turno abierto de hoy, si lo hay. */
    public function abierta(User $usuario): ?Asistencia
    {
        return Asistencia::query()
            ->with('tienda')
            ->where('user_id', $usuario->id)
            ->whereDate('fecha', today())
            ->abiertas()
            ->latest('entrada_en')
            ->first();
    }

    public function marcarEntrada(User $usuario, float $lat, float $lng, ?float $precision, bool $simulada): Asistencia
    {
        $this->validarLectura($precision, $simulada);

        return DB::transaction(function () use ($usuario, $lat, $lng, $precision): Asistencia {
            // Bloqueo por usuario: dos toques seguidos no abren dos turnos.
            User::query()->whereKey($usuario->id)->lockForUpdate()->first();

            if ($abierta = $this->abierta($usuario)) {
                throw new RuntimeException(
                    "Ya marcaste tu entrada a las {$abierta->entrada_en->format('H:i')} en {$abierta->tienda?->nombre}. Marca primero la salida."
                );
            }

            ['tienda' => $tienda, 'distancia' => $distancia, 'dentro' => $dentro] = $this->masCercana($lat, $lng);

            if ($tienda === null) {
                throw new RuntimeException('Todavía no hay tiendas con su ubicación registrada. Pide al administrador que la fije.');
            }

            if (! $dentro) {
                throw new RuntimeException(
                    "Estás a {$this->metros($distancia)} de {$tienda->nombre}. Para marcar tienes que estar a menos de {$tienda->radio_metros} m."
                );
            }

            $ahora = now();
            $primeraDelDia = ! Asistencia::query()
                ->where('user_id', $usuario->id)
                ->whereDate('fecha', $ahora->toDateString())
                ->exists();

            return Asistencia::query()->create([
                'user_id' => $usuario->id,
                'tienda_id' => $tienda->id,
                'fecha' => $ahora->toDateString(),
                'entrada_en' => $ahora,
                'entrada_latitud' => $lat,
                'entrada_longitud' => $lng,
                'entrada_distancia' => $distancia,
                'entrada_precision' => $precision === null ? null : (int) round($precision),
                'minutos_atraso' => $primeraDelDia ? $this->atraso($tienda, $ahora) : null,
            ])->load('tienda');
        });
    }

    public function marcarSalida(User $usuario, float $lat, float $lng, ?float $precision, bool $simulada): Asistencia
    {
        $this->validarLectura($precision, $simulada);

        $abierta = $this->abierta($usuario);

        if ($abierta === null) {
            throw new RuntimeException('No tienes una entrada abierta hoy.');
        }

        $tienda = $abierta->tienda;
        $distancia = (int) round(self::distancia($lat, $lng, (float) $tienda->latitud, (float) $tienda->longitud));

        if ($distancia > $tienda->radio_metros) {
            throw new RuntimeException(
                "Estás a {$this->metros($distancia)} de {$tienda->nombre}. La salida se marca dentro de la tienda donde entraste."
            );
        }

        $abierta->update([
            'salida_en' => now(),
            'salida_latitud' => $lat,
            'salida_longitud' => $lng,
            'salida_distancia' => $distancia,
            'salida_precision' => $precision === null ? null : (int) round($precision),
        ]);

        return $abierta->fresh('tienda');
    }

    /**
     * El administrador pone la salida que el trabajador olvidó marcar. Queda
     * anotado quién la puso y por qué.
     */
    public function corregirSalida(Asistencia $asistencia, Carbon $salida, User $quien, string $motivo): Asistencia
    {
        if ($salida->lte($asistencia->entrada_en)) {
            throw new RuntimeException('La salida tiene que ser después de la entrada ('.$asistencia->entrada_en->format('H:i').').');
        }

        if ($salida->isFuture()) {
            throw new RuntimeException('La salida no puede ser en el futuro.');
        }

        $asistencia->update([
            'salida_en' => $salida,
            'corregida_por' => $quien->id,
            'notas' => trim($motivo),
        ]);

        return $asistencia->fresh(['tienda', 'user']);
    }

    /**
     * Historial de un mes, por trabajador, con sus días.
     *
     * @return list<array<string, mixed>>
     */
    public function historial(int $anio, int $mes, ?int $userId = null, ?int $tiendaId = null): array
    {
        $asistencias = Asistencia::query()
            ->with(['user:id,name', 'tienda:id,nombre'])
            ->delMes($anio, $mes)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when($tiendaId, fn ($q) => $q->where('tienda_id', $tiendaId))
            ->orderBy('fecha')
            ->orderBy('entrada_en')
            ->get();

        return $asistencias
            ->groupBy('user_id')
            ->map(fn (Collection $turnos): array => $this->resumenDe($turnos))
            ->sortBy('trabajador', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /** @param  Collection<int, Asistencia>  $turnos */
    private function resumenDe(Collection $turnos): array
    {
        $primero = $turnos->first();

        $dias = $turnos
            ->groupBy(fn (Asistencia $a): string => $a->fecha->toDateString())
            ->map(fn (Collection $delDia, string $fecha): array => [
                'fecha' => $fecha,
                'tiendas' => $delDia->map(fn (Asistencia $a) => $a->tienda?->nombre)->unique()->values()->all(),
                'entrada' => $delDia->first()->entrada_en->toIso8601String(),
                'salida' => $delDia->last()->salida_en?->toIso8601String(),
                'minutos' => (int) $delDia->sum(fn (Asistencia $a): int => $a->minutosTrabajados() ?? 0),
                'minutos_atraso' => (int) $delDia->max('minutos_atraso'),
                'sin_salida' => $delDia->contains(fn (Asistencia $a): bool => $a->sinSalida()),
                'abierta' => $delDia->contains(fn (Asistencia $a): bool => $a->estaAbierta() && $a->fecha->isToday()),
                'turnos' => $delDia->map(fn (Asistencia $a): array => $a->aArreglo())->values()->all(),
            ])
            ->values();

        return [
            'user_id' => $primero->user_id,
            'trabajador' => $primero->user?->name ?? '—',
            'dias' => $dias->count(),
            'minutos' => (int) $dias->sum('minutos'),
            'atrasos' => $dias->filter(fn (array $d): bool => $d['minutos_atraso'] > 0)->count(),
            'minutos_atraso' => (int) $dias->sum('minutos_atraso'),
            'sin_salida' => $dias->where('sin_salida', true)->count(),
            'detalle' => $dias->all(),
        ];
    }

    private function validarLectura(?float $precision, bool $simulada): void
    {
        if ($simulada) {
            throw new RuntimeException('Tu teléfono está usando una ubicación simulada. Desactiva la app de GPS falso para marcar.');
        }

        if ($precision !== null && $precision > self::PRECISION_MAXIMA) {
            throw new RuntimeException(
                'La señal del GPS es débil (±'.round($precision).' m). Acércate a la puerta o a una ventana y vuelve a intentarlo.'
            );
        }
    }

    /** Minutos de atraso contra la hora de entrada; 0 dentro de la tolerancia. */
    private function atraso(Tienda $tienda, Carbon $momento): ?int
    {
        if (! $tienda->hora_entrada) {
            return null;
        }

        $hora = $momento->copy()->setTimeFromTimeString((string) $tienda->hora_entrada);
        $pasado = (int) $hora->diffInMinutes($momento, false);

        return $pasado > $tienda->tolerancia_minutos ? $pasado : 0;
    }

    private function metros(?int $distancia): string
    {
        return $distancia === null ? '—' : ($distancia >= 1000
            ? number_format($distancia / 1000, 1, ',', '.').' km'
            : $distancia.' m');
    }
}
