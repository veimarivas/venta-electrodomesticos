{{-- Contenido de una fila de resultado. Se comparte entre la versión
     enlazable y la que no lo es, para que ambas se vean igual. --}}
<div class="flex-grow-1 min-w-0">
    <span class="d-block fw-semibold text-truncate">{{ $item['titulo'] }}</span>
    @if ($item['detalle'] !== '')
        <small class="text-muted d-block text-truncate">{{ $item['detalle'] }}</small>
    @endif
</div>

<span class="badge bg-light text-body flex-shrink-0">{{ $item['nota'] }}</span>

@if ($item['url'])
    <small class="text-primary flex-shrink-0 d-none d-md-inline">
        {{ $item['accion'] }} <i class="ri-arrow-right-line align-bottom"></i>
    </small>
@endif
