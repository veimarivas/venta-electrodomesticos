@props([
    'segmentos' => [],   // [['nombre'=>, 'valor'=>], ...] máximo 4
    'formato' => 'Bs ',
    'vacio' => 'Sin datos en este período.',
])

@php
    // Paleta categórica validada (ver _viz.scss). Cuatro ranuras: a partir de
    // la quinta no se generan tonos nuevos, se agrupa en "Otros".
    $tonos = ['var(--viz-1)', 'var(--viz-2)', 'var(--viz-3)', 'var(--viz-4)'];
    $total = max(array_sum(array_map(fn ($s) => (float) $s['valor'], $segmentos)), 0.01);
@endphp

<div class="viz">
    @if ($segmentos === [])
        <p class="text-muted text-center py-4 mb-0">{{ $vacio }}</p>
    @else
        <div class="viz-apilada" role="img" aria-label="Reparto por método de pago">
            @foreach ($segmentos as $i => $segmento)
                @php
                    $porcentaje = (float) $segmento['valor'] / $total * 100;
                    $color = $tonos[$i % count($tonos)];
                    $importe = $formato.number_format((float) $segmento['valor'], 2, ',', '.');
                @endphp
                <div class="viz-apilada-segmento" style="width: {{ $porcentaje }}%; background: {{ $color }}"
                    tabindex="0"
                    data-viz-titulo="{{ $segmento['nombre'] }}"
                    data-viz-serie="{{ round($porcentaje, 1) }} %"
                    data-viz-valor="{{ $importe }}"
                    data-viz-color="{{ $color }}"
                    aria-label="{{ $segmento['nombre'] }}: {{ $importe }}"></div>
            @endforeach
        </div>

        {{-- Leyenda SIEMPRE con dos o más series, y con el valor escrito al
             lado: en claro estos tonos no llegan a 3:1 contra el fondo, así que
             la identidad no puede descansar solo en el color. --}}
        <div class="viz-leyenda">
            @foreach ($segmentos as $i => $segmento)
                <span class="viz-leyenda-item">
                    <span class="viz-leyenda-marca" style="background: {{ $tonos[$i % count($tonos)] }}"></span>
                    {{ $segmento['nombre'] }}
                    <span class="viz-leyenda-valor">
                        {{ $formato }}{{ number_format((float) $segmento['valor'], 2, ',', '.') }}
                    </span>
                </span>
            @endforeach
        </div>
    @endif
</div>
