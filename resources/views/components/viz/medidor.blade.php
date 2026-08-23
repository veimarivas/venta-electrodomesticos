@props([
    'porcentaje' => 0,
    'etiqueta' => null,
])

@php $valor = max(min((float) $porcentaje, 100), 0); @endphp

<div class="viz viz-medidor">
    <div class="viz-medidor-pista">
        <div class="viz-medidor-marca {{ $valor >= 100 ? 'esta-completo' : '' }}"
            style="width: {{ $valor }}%"
            role="meter" aria-valuenow="{{ round($porcentaje, 1) }}" aria-valuemin="0" aria-valuemax="100"
            aria-label="{{ $etiqueta ?? 'Progreso' }}"></div>
    </div>
    <span class="viz-medidor-valor">{{ number_format((float) $porcentaje, 1, ',', '.') }} %</span>
</div>
