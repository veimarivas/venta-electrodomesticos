{{--
    Tarjeta de producto de la vitrina. Recibe $producto (App\Models\Producto con
    `categoria`, `marca` y el conteo `disponibles` cargados).

    Toda la tarjeta enlaza a las unidades del producto cuando quien la ve puede
    entrar al inventario; si no, es solo una ficha legible.
--}}
@php
    $disponibles = (int) $producto->disponibles;
    $agotado = $disponibles === 0;
    $bajoMinimo = !$agotado && $producto->stock_minimo > 0 && $disponibles <= $producto->stock_minimo;

    $tono = $agotado
        ? ['clase' => 'vitrina-stock-agotado', 'texto' => 'Agotado']
        : ($bajoMinimo
            ? ['clase' => 'vitrina-stock-bajo', 'texto' => 'Bajo mínimo']
            : ['clase' => 'vitrina-stock-ok', 'texto' => $disponibles.($disponibles === 1 ? ' unidad' : ' unidades')]);
@endphp
<div class="col-6 col-md-4 col-lg-3 col-xxl-2">
    <div class="vitrina-producto h-100 @if ($agotado) vitrina-producto--agotado @endif">
        <div class="vitrina-producto-imagen">
            @if ($producto->imagen)
                <img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}" loading="lazy">
            @else
                <div class="vitrina-producto-placeholder">
                    <i class="ri-image-line"></i>
                </div>
            @endif

            {{-- Franja de agotado sobre la imagen --}}
            @if ($agotado)
                <div class="vitrina-producto-agotado-franja">
                    <i class="ri-shopping-cart-line"></i>
                    <span>Agotado</span>
                </div>
            @endif

            {{-- Badge de unidades --}}
            <div class="vitrina-producto-unidades-badge @if ($agotado) vitrina-producto-unidades-badge--agotado @elseif ($bajoMinimo) vitrina-producto-unidades-badge--bajo @endif">
                @if ($agotado)
                    <i class="ri-close-circle-line"></i>
                @else
                    <i class="ri-box-3-line"></i>
                @endif
                {{ $agotado ? '0' : $disponibles }}
            </div>

            @can('unidades.ver')
                <div class="vitrina-producto-overlay">
                    <span>Ver unidades</span>
                </div>
            @endcan
        </div>
        <div class="vitrina-producto-body">
            <div class="vitrina-producto-nombre" title="{{ $producto->nombre }}">{{ $producto->nombre }}</div>
            <div class="vitrina-producto-marca">{{ $producto->marca?->nombre ?? '' }}</div>
            <div class="vitrina-producto-precio">Bs {{ number_format((float) $producto->precio_venta, 2, ',', '.') }}</div>
            <div class="vitrina-producto-stock {{ $tono['clase'] }}">
                <span class="vitrina-producto-stock-dot"></span>
                {{ $tono['texto'] }}
            </div>
        </div>
        @can('unidades.ver')
            <a href="{{ route('search.producto', $producto) }}" class="vitrina-producto-link"
                aria-label="Ver unidades de {{ $producto->nombre }}"></a>
        @endcan
    </div>
</div>
