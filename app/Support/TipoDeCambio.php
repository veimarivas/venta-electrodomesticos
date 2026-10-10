<?php

namespace App\Support;

use App\Models\CotizacionDolar;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * El dólar en Bolivia: el oficial del BCB y el del mercado paralelo.
 *
 * La fuente es DolarApi (`bo.dolarapi.com`), que publica el oficial del Banco
 * Central y el paralelo (promedio de compra y venta en Binance P2P, que es como
 * hoy se mide el dólar de la calle). Se consulta como mucho cada 30 minutos
 * —por el programador de tareas y, si no corre, al pedir el dato— y se guarda
 * en `cotizaciones_dolar`: si la fuente se cae, se enseña el último valor
 * conocido con su hora, nunca un cero.
 */
class TipoDeCambio
{
    private const CACHE = 'dolar.actual';

    /** Pasado este tiempo sin poder verificarlo, el dato se marca como viejo. */
    private const HORAS_PARA_VIEJO = 6;

    /** casa de la fuente => tipo nuestro */
    private const CASAS = [
        'oficial' => CotizacionDolar::OFICIAL,
        'binance' => CotizacionDolar::PARALELO,
    ];

    /**
     * Consulta la fuente y guarda lo nuevo. Devuelve false si no respondió o
     * respondió algo que no se entiende; lo guardado no se toca.
     */
    public function actualizar(): bool
    {
        try {
            $respuesta = Http::connectTimeout(4)
                ->timeout(8)
                ->acceptJson()
                ->get((string) config('services.dolar.url'));
        } catch (\Throwable) {
            return false;
        }

        if (! $respuesta->successful() || ! is_array($respuesta->json())) {
            return false;
        }

        $guardadas = 0;

        foreach ($respuesta->json() as $item) {
            $tipo = is_array($item) ? (self::CASAS[$item['casa'] ?? ''] ?? null) : null;

            if ($tipo === null) {
                continue;
            }

            $compra = (float) ($item['compra'] ?? 0);
            $venta = (float) ($item['venta'] ?? 0);

            // Un valor absurdo (cero, o un error de la fuente que multiplica
            // por cien) no entra: mejor el último bueno que uno falso.
            if ($compra <= 0 || $venta <= 0 || $compra > 1000 || $venta > 1000) {
                continue;
            }

            $this->registrar($tipo, $compra, $venta, (string) ($item['nombre'] ?? $item['casa']), $item['fechaActualizacion'] ?? null);
            $guardadas++;
        }

        Cache::forget(self::CACHE);

        return $guardadas > 0;
    }

    /**
     * El dato para enseñar. Si hace más de 30 minutos que no se verifica, lo
     * intenta antes (un intento cada 5 minutos como mucho, para no colgar
     * cada página cuando la fuente está caída).
     *
     * @return array<string, mixed>
     */
    public function actual(bool $refrescar = true): array
    {
        try {
            if ($refrescar) {
                $this->refrescarSiHaceFalta();
            }

            return Cache::remember(self::CACHE, 300, fn (): array => $this->armar());
        } catch (QueryException $e) {
            // Sin la tabla (migración pendiente) o con la base fallando, el
            // dólar no puede tumbar el panel ni la app: se queda sin datos.
            report($e);

            return self::sinDatos();
        }
    }

    /** @return array<string, mixed> */
    public static function sinDatos(): array
    {
        return [
            'paralelo' => null,
            'oficial' => null,
            'brecha' => null,
            'variacion' => null,
            'historial' => [],
            'fuente' => 'BCB (oficial) y Binance P2P (paralelo), vía DolarApi',
        ];
    }

    private function refrescarSiHaceFalta(): void
    {
        $ultima = CotizacionDolar::query()->max('updated_at');

        if ($ultima !== null && Carbon::parse($ultima)->gt(now()->subMinutes(30))) {
            return;
        }

        if (Cache::add('dolar.intento', true, 300)) {
            $this->actualizar();
        }
    }

    private function registrar(string $tipo, float $compra, float $venta, string $fuente, ?string $publicado): void
    {
        $ultima = CotizacionDolar::query()->where('tipo', $tipo)->latest('id')->first();

        // Mismo valor y mismo día: solo se anota que se volvió a verificar.
        // Un día nuevo siempre abre fila, para que la historia tenga cada día.
        if ($ultima !== null
            && abs((float) $ultima->compra - $compra) < 0.00005
            && abs((float) $ultima->venta - $venta) < 0.00005
            && $ultima->created_at->isToday()) {
            $ultima->touch();

            return;
        }

        CotizacionDolar::query()->create([
            'tipo' => $tipo,
            'compra' => $compra,
            'venta' => $venta,
            'fuente' => $fuente,
            'publicado_en' => $publicado !== null ? Carbon::parse($publicado) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function armar(): array
    {
        $paralelo = $this->ultima(CotizacionDolar::PARALELO);
        $oficial = $this->ultima(CotizacionDolar::OFICIAL);

        $historial = $this->historial(14);

        // Cómo se movió el paralelo frente al último valor de ayer o antes.
        $anterior = collect($historial)->filter(fn (array $d): bool => $d['fecha'] < now()->toDateString())
            ->last()['paralelo'] ?? null;

        return [
            'paralelo' => $paralelo,
            'oficial' => $oficial,
            'brecha' => $paralelo !== null && $oficial !== null && $oficial['venta'] > 0
                ? round(($paralelo['venta'] / $oficial['venta'] - 1) * 100, 2)
                : null,
            'variacion' => $paralelo !== null && $anterior !== null
                ? round($paralelo['venta'] - $anterior, 4)
                : null,
            'historial' => $historial,
            'fuente' => 'BCB (oficial) y Binance P2P (paralelo), vía DolarApi',
        ];
    }

    /** @return array<string, mixed>|null */
    private function ultima(string $tipo): ?array
    {
        $fila = CotizacionDolar::query()->where('tipo', $tipo)->latest('id')->first();

        if ($fila === null) {
            return null;
        }

        return [
            'compra' => (float) $fila->compra,
            'venta' => (float) $fila->venta,
            'fuente' => $fila->fuente,
            'publicado_en' => $fila->publicado_en?->toIso8601String(),
            'verificado_en' => $fila->updated_at?->toIso8601String(),
            'desactualizado' => $fila->updated_at === null
                || $fila->updated_at->lt(now()->subHours(self::HORAS_PARA_VIEJO)),
        ];
    }

    /**
     * Último valor de venta de cada día, oficial y paralelo.
     *
     * @return list<array{fecha: string, paralelo: ?float, oficial: ?float}>
     */
    public function historial(int $dias): array
    {
        $filas = CotizacionDolar::query()
            ->where('created_at', '>=', now()->subDays($dias - 1)->startOfDay())
            ->orderBy('id')
            ->get(['tipo', 'venta', 'created_at']);

        $porDia = [];

        foreach ($filas as $fila) {
            $dia = $fila->created_at->toDateString();
            $porDia[$dia] ??= ['fecha' => $dia, 'paralelo' => null, 'oficial' => null];
            $porDia[$dia][$fila->tipo] = (float) $fila->venta;
        }

        ksort($porDia);

        return array_values($porDia);
    }
}
