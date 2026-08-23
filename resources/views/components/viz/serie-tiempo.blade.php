@props([
    'puntos' => [],      // [['etiqueta' => '12/08', 'valor' => 1234.5], ...]
    'formato' => 'Bs ',  // prefijo del valor en el tooltip
    'alto' => 180,
])

@php
    // Serie única: el título de la tarjeta ya dice qué se grafica, así que no
    // lleva caja de leyenda — un solo color no necesita descifrarse.
    $valores = array_column($puntos, 'valor');
    // La escala se calcula sobre el máximo real: con una fija, un período
    // flojo se vería igual que uno bueno. El 0.01 garantiza una base mínima
    // (período vacío o todo en ceros) sin dividir entre cero. Va dentro del
    // mismo array para no topar con el quirks de max() entre array y escalar.
    $maximo = max(array_merge($valores ?: [], [0.01]));
    $total = count($puntos);

    // Coordenadas en un lienzo 0..100 x 0..100; el SVG escala solo.
    $coordenada = function (int $i, float $valor) use ($total, $maximo): array {
        $x = $total > 1 ? $i / ($total - 1) * 100 : 50;
        $y = 100 - ($valor / $maximo * 88) - 6;   // 6% de aire arriba y abajo

        return [round($x, 3), round($y, 3)];
    };

    $puntosSvg = [];
    $paraTooltip = [];

    foreach ($puntos as $i => $punto) {
        [$x, $y] = $coordenada($i, (float) $punto['valor']);
        $puntosSvg[] = "{$x},{$y}";

        $paraTooltip[] = [
            'y' => $y,
            'titulo' => $punto['etiqueta'],
            'filas' => [[
                'serie' => $punto['serie'] ?? '',
                'valor' => $formato.number_format((float) $punto['valor'], 2, ',', '.'),
                'color' => 'var(--viz-1)',
            ]],
        ];
    }

    $linea = implode(' ', $puntosSvg);
    $area = $linea !== '' ? "0,100 {$linea} 100,100" : '';
@endphp

<div class="viz" style="height: {{ $alto }}px" data-viz-serie-tiempo='@json($paraTooltip)'>
    @if ($total === 0)
        <p class="text-muted text-center py-5 mb-0">Sin datos en este período.</p>
    @else
        <svg viewBox="0 0 100 100" preserveAspectRatio="none" style="height: {{ $alto }}px"
            role="img" aria-label="Evolución del período">
            {{-- Rejilla recesiva: tres líneas sólidas de un pelo. --}}
            @foreach ([6, 50, 94] as $y)
                <line class="viz-grid-linea" x1="0" y1="{{ $y }}" x2="100" y2="{{ $y }}"
                    vector-effect="non-scaling-stroke" />
            @endforeach

            <polygon class="viz-area" points="{{ $area }}" />
            <polyline class="viz-linea" points="{{ $linea }}" vector-effect="non-scaling-stroke" />

            {{-- Cruz y punto activo: los mueve la capa de interacción. --}}
            <line class="viz-cruz" x1="0" y1="0" x2="0" y2="100" style="display:none"
                vector-effect="non-scaling-stroke" />
            <circle class="viz-punto viz-punto-activo" r="4" cx="0" cy="0" style="display:none"
                vector-effect="non-scaling-stroke" />
        </svg>

        {{-- Eje X: solo los extremos y el centro. Etiquetar los 31 días
             convertiría el eje en una mancha. --}}
        <div class="d-flex justify-content-between mt-1">
            @foreach ([0, intdiv($total - 1, 2), $total - 1] as $i)
                <small class="text-muted fs-11">{{ $puntos[$i]['etiqueta'] ?? '' }}</small>
            @endforeach
        </div>
    @endif
</div>
