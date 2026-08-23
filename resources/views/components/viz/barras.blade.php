@props([
    'filas' => [],       // [['nombre'=>, 'valor'=>, 'meta'=>?, 'formato'=>?], ...]
    'formato' => 'Bs ',
    'vacio' => 'Sin datos en este período.',
])

@php
    // Un solo tono para todas las barras: las categorías (productos,
    // vendedores) no tienen orden natural, así que colorearlas más oscuro
    // cuanto más grandes duplicaría en color lo que la barra ya dice.
    $maximo = max(array_merge(array_map(fn ($f) => (float) $f['valor'], $filas), [0.01]));
@endphp

<div class="viz viz-barras">
    @forelse ($filas as $fila)
        @php $ancho = max((float) $fila['valor'] / $maximo * 100, 1.5); @endphp

        <div class="viz-barra-fila">
            <span class="viz-barra-nombre">{{ $fila['nombre'] }}</span>
            {{-- Valor en la punta: es la etiqueta directa que hace que el
                 tooltip refuerce en vez de esconder el dato. --}}
            <span class="viz-barra-valor">
                {{ $formato }}{{ number_format((float) $fila['valor'], 2, ',', '.') }}
            </span>

            <div class="viz-barra-pista">
                <div class="viz-barra-marca" style="width: {{ $ancho }}%"
                    tabindex="0" role="img"
                    data-viz-titulo="{{ $fila['nombre'] }}"
                    data-viz-serie="{{ $fila['meta'] ?? '' }}"
                    data-viz-valor="{{ $formato }}{{ number_format((float) $fila['valor'], 2, ',', '.') }}"
                    data-viz-color="var(--viz-1)"
                    aria-label="{{ $fila['nombre'] }}: {{ $formato }}{{ number_format((float) $fila['valor'], 2, ',', '.') }}"></div>
            </div>

            @if (! empty($fila['meta']))
                <small class="viz-barra-meta">{{ $fila['meta'] }}</small>
            @endif
        </div>
    @empty
        <p class="text-muted text-center py-4 mb-0">{{ $vacio }}</p>
    @endforelse
</div>
