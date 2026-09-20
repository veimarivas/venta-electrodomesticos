@props([
    'etiqueta' => '',
    'valor' => '0',
    'nota' => null,
])

@php
    // Cuántos caracteres tiene el número decide su tamaño: una cifra de nueve
    // dígitos no puede lucir la misma tipografía que una de seis, o desborda
    // su columna y se corta justo cuando la venta fue buena. Se pasa al CSS,
    // que además conoce el ancho real de la columna.
    $largo = max(6, mb_strlen((string) $valor));
@endphp

{{-- Cifra protagonista: un número que un dashboard lidera no es una gráfica
     de una sola barra, es un número grande. --}}
<div class="viz viz-cifra-contenedor" style="--cifra-largo: {{ $largo }}">
    <span class="viz-cifra-etiqueta">{{ $etiqueta }}</span>
    <div class="viz-cifra" title="{{ $valor }}">{{ $valor }}</div>
    @if ($nota)
        <small class="viz-cifra-nota text-muted">{{ $nota }}</small>
    @endif
</div>
