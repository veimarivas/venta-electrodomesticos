@php
    $num = fn (?float $v, int $dec = 2): string => $v === null ? '—' : number_format($v, $dec, ',', '.');
    $paralelo = $dolar['paralelo'];
    $oficial = $dolar['oficial'];
    $verificado = $paralelo['verificado_en'] ?? $oficial['verificado_en'] ?? null;
    $viejo = ($paralelo['desactualizado'] ?? true) && ($oficial['desactualizado'] ?? true);

    // Línea de las dos semanas: paralelo y oficial en la misma escala.
    $serie = collect($dolar['historial']);
    $valores = $serie->pluck('paralelo')->merge($serie->pluck('oficial'))->filter();
    $linea = function (string $clave) use ($serie, $valores): string {
        if ($serie->count() < 2 || $valores->isEmpty()) {
            return '';
        }
        $min = $valores->min();
        $max = $valores->max();
        $rango = max($max - $min, 0.01);
        $paso = 236 / max($serie->count() - 1, 1);

        return $serie->values()->map(function (array $d, int $i) use ($clave, $min, $rango, $paso): ?string {
            return $d[$clave] === null ? null
                : round(2 + $i * $paso, 1).','.round(44 - (($d[$clave] - $min) / $rango) * 40, 1);
        })->filter()->join(' ');
    };
@endphp

@if ($paralelo === null && $oficial === null)
    {{-- Sin ningún dato todavía (o sin la tabla): no se ocupa la barra. --}}
    <div class="d-none" wire:poll.600s></div>
@else
<div class="dropdown ms-1 header-item dolar-topbar" wire:poll.600s>
    <button type="button" @class(['dolar-chip', 'dolar-chip--viejo' => $viejo])
        data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-haspopup="true" aria-expanded="false"
        aria-label="Dólar: paralelo {{ $num($paralelo['venta'] ?? null) }}, oficial {{ $num($oficial['venta'] ?? null) }}">
        <i class="ri-money-dollar-circle-line dolar-chip-icono"></i>
        <span class="dolar-chip-dato">
            <small>Paralelo</small>
            <strong class="tabular">{{ $num($paralelo['venta'] ?? null) }}</strong>
        </span>
        <span class="dolar-chip-dato d-none d-lg-flex">
            <small>BCB</small>
            <strong class="tabular">{{ $num($oficial['venta'] ?? null) }}</strong>
        </span>
        @if ($dolar['variacion'] !== null && abs($dolar['variacion']) >= 0.005)
            <i @class([
                'dolar-chip-tendencia d-none d-md-inline',
                'ri-arrow-up-line dolar-sube' => $dolar['variacion'] > 0,
                'ri-arrow-down-line dolar-baja' => $dolar['variacion'] < 0,
            ])></i>
        @endif
    </button>

    <div class="dropdown-menu dropdown-menu-end p-0 dolar-panel" x-data="{ monto: '' }">
        <div class="dolar-panel-cabecera">
            <div>
                <h6 class="mb-0">Dólar hoy</h6>
                <small class="text-muted">
                    @if ($verificado)
                        Actualizado {{ \Illuminate\Support\Carbon::parse($verificado)->diffForHumans() }}
                    @else
                        Sin datos todavía
                    @endif
                </small>
            </div>
            <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon" wire:click="$refresh" title="Actualizar">
                <i class="ri-refresh-line" wire:loading.class="ri-spin"></i>
            </button>
        </div>

        @if ($viejo && $verificado)
            <div class="dolar-aviso">
                <i class="ri-error-warning-line"></i>
                La fuente no responde: este es el último valor conocido.
            </div>
        @endif

        <div class="dolar-tarjetas">
            <div class="dolar-tarjeta dolar-tarjeta--paralelo">
                <span class="dolar-tarjeta-titulo">Paralelo</span>
                <div class="dolar-tarjeta-fila">
                    <span>Compra</span><strong class="tabular">{{ $num($paralelo['compra'] ?? null) }}</strong>
                </div>
                <div class="dolar-tarjeta-fila">
                    <span>Venta</span><strong class="tabular">{{ $num($paralelo['venta'] ?? null) }}</strong>
                </div>
            </div>
            <div class="dolar-tarjeta dolar-tarjeta--oficial">
                <span class="dolar-tarjeta-titulo">Oficial BCB</span>
                <div class="dolar-tarjeta-fila">
                    <span>Compra</span><strong class="tabular">{{ $num($oficial['compra'] ?? null) }}</strong>
                </div>
                <div class="dolar-tarjeta-fila">
                    <span>Venta</span><strong class="tabular">{{ $num($oficial['venta'] ?? null) }}</strong>
                </div>
            </div>
        </div>

        <div class="dolar-indicadores">
            <span>
                Brecha
                <strong class="tabular">{{ $dolar['brecha'] === null ? '—' : $num($dolar['brecha']).' %' }}</strong>
            </span>
            <span>
                Desde ayer
                <strong @class(['tabular', 'dolar-sube' => ($dolar['variacion'] ?? 0) > 0, 'dolar-baja' => ($dolar['variacion'] ?? 0) < 0])>
                    {{ $dolar['variacion'] === null ? '—' : (($dolar['variacion'] > 0 ? '+' : '').$num($dolar['variacion'])) }}
                </strong>
            </span>
        </div>

        @if ($linea('paralelo') !== '')
            <div class="dolar-grafico">
                <svg viewBox="0 0 240 48" preserveAspectRatio="none" role="img"
                    aria-label="Evolución del dólar en los últimos {{ $serie->count() }} días">
                    <polyline points="{{ $linea('oficial') }}" class="dolar-linea dolar-linea--oficial" />
                    <polyline points="{{ $linea('paralelo') }}" class="dolar-linea dolar-linea--paralelo" />
                </svg>
                <div class="dolar-grafico-leyenda">
                    <span><i class="dolar-punto dolar-punto--paralelo"></i> Paralelo</span>
                    <span><i class="dolar-punto dolar-punto--oficial"></i> Oficial</span>
                    <span class="ms-auto text-muted">{{ $serie->count() }} días</span>
                </div>
            </div>
        @endif

        {{-- Conversor rápido: para responder «¿cuánto es en dólares?» sin calculadora. --}}
        <div class="dolar-conversor">
            <label for="dolar-monto" class="form-label fs-12 mb-1">Convertir dólares</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text">US$</span>
                <input id="dolar-monto" type="number" min="0" step="0.01" class="form-control" placeholder="100" x-model="monto">
            </div>
            <div class="dolar-conversor-resultado" x-show="parseFloat(monto) > 0" x-cloak>
                <span>Paralelo: <strong class="tabular" x-text="'Bs ' + (parseFloat(monto) * {{ (float) ($paralelo['venta'] ?? 0) }}).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong></span>
                <span>BCB: <strong class="tabular" x-text="'Bs ' + (parseFloat(monto) * {{ (float) ($oficial['venta'] ?? 0) }}).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong></span>
            </div>
        </div>

        <div class="dolar-pie">{{ $dolar['fuente'] }}</div>
    </div>
</div>
@endif
