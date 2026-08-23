@props([
    'etiqueta' => '',
    'valor' => '0',
    'nota' => null,
])

{{-- Cifra protagonista: un número que un dashboard lidera no es una gráfica
     de una sola barra, es un número grande. --}}
<div class="viz">
    <span class="viz-cifra-etiqueta">{{ $etiqueta }}</span>
    <div class="viz-cifra">{{ $valor }}</div>
    @if ($nota)
        <small class="text-muted">{{ $nota }}</small>
    @endif
</div>
