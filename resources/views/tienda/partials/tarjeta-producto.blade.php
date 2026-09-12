{{--
    Tarjeta de producto del escaparate. Recibe $producto con `categoria`, `marca`
    y el conteo `disponibles` cargados. Enlaza a la ficha pública del producto.
--}}
@php
    $disponibles = (int) ($producto->disponibles ?? 0);
    $agotado = $disponibles === 0;
    $bajo = ! $agotado && $producto->stock_minimo > 0 && $disponibles <= $producto->stock_minimo;
@endphp

<article class="tienda-card {{ $agotado ? 'is-agotado' : '' }}">
    <a href="{{ route('tienda.producto', $producto) }}" class="tienda-card-link"
        aria-label="{{ $producto->nombre }}">

        <div class="tienda-card-media">
            @if ($producto->imagen)
                <img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}" loading="lazy">
            @else
                <img src="{{ asset('assets/images/sin_imagen.png') }}" alt="{{ $producto->nombre }}"
                    class="tienda-card-sin-imagen" loading="lazy">
            @endif

            @if ($agotado)
                <span class="tienda-card-franja">Agotado</span>
            @elseif ($bajo)
                <span class="tienda-card-badge tienda-card-badge--bajo">Últimas unidades</span>
            @else
                <span class="tienda-card-badge">Disponible</span>
            @endif

            <span class="tienda-card-cta">
                Ver detalle <i class="ri-arrow-right-line" aria-hidden="true"></i>
            </span>
        </div>

        <div class="tienda-card-body">
            <span class="tienda-card-marca">{{ $producto->marca?->nombre ?? ' ' }}</span>
            <h3 class="tienda-card-nombre">{{ $producto->nombre }}</h3>

            <div class="tienda-card-pie">
                <span class="tienda-card-precio">
                    Bs {{ number_format((float) $producto->precio_venta, 2, ',', '.') }}
                </span>
                <span class="tienda-card-stock">
                    @if ($agotado)
                        Sin stock
                    @else
                        {{ $disponibles }} {{ $disponibles === 1 ? 'unidad' : 'unidades' }}
                    @endif
                </span>
            </div>
        </div>
    </a>
</article>
