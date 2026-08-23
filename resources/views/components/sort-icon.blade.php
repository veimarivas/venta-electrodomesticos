@props([
    'field',      // columna de esta cabecera
    'current',    // columna por la que se está ordenando
    'direction',  // asc | desc
])

@if ($current === $field)
    <i class="ri-arrow-{{ $direction === 'asc' ? 'up' : 'down' }}-s-fill align-middle text-primary fs-16"></i>
@else
    {{-- Marca tenue: indica que la columna es ordenable sin competir con la activa --}}
    <i class="ri-arrow-up-down-line align-middle text-muted opacity-50 fs-12"></i>
@endif
