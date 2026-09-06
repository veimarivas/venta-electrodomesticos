{{--
    Tarjeta de producto de la vitrina. Recibe $producto (App\Models\Producto con
    `categoria`, `marca` y el conteo `disponibles` cargados).

    Toda la tarjeta enlaza a las unidades del producto cuando quien la ve puede
    entrar al inventario; si no, es solo una ficha legible.
--}}
@php
    $disponibles = (int) $producto->disponibles;
    $tono = $disponibles === 0
        ? ['badge' => 'bg-danger', 'texto' => 'Agotado']
        : ($producto->stock_minimo > 0 && $disponibles <= $producto->stock_minimo
            ? ['badge' => 'bg-warning text-dark', 'texto' => 'Bajo mínimo']
            : ['badge' => 'bg-success', 'texto' => $disponibles.' en stock']);
@endphp
<div class="col-6 col-md-4 col-lg-3 col-xxl-2">
    <div class="card h-100">
        <div style="aspect-ratio: 4/3; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center;">
            @if ($producto->imagen)
                <img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}"
                    style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
            @else
                <i class="ri-image-line fs-1" style="color: #94a3b8;"></i>
            @endif
        </div>
        <div class="card-body p-3">
            <div class="fw-semibold text-truncate" title="{{ $producto->nombre }}">{{ $producto->nombre }}</div>
            <small class="text-muted d-block text-truncate">{{ $producto->marca?->nombre ?? '' }}</small>
            <div class="fw-semibold mt-2">Bs {{ number_format((float) $producto->precio_venta, 2, ',', '.') }}</div>
            <span class="badge {{ $tono['badge'] }}">{{ $tono['texto'] }}</span>
        </div>
        @can('unidades.ver')
            <a href="{{ route('search.producto', $producto) }}" class="stretched-link"
                aria-label="Ver unidades de {{ $producto->nombre }}"></a>
        @endcan
    </div>
</div>