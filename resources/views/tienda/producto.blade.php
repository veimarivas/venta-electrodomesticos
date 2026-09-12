@extends('tienda.layout')

@section('title', $producto->nombre)

@php
    $disponibles = (int) ($producto->disponibles ?? 0);
    $agotado = $disponibles === 0;
    $bajo = ! $agotado && $producto->stock_minimo > 0 && $disponibles <= $producto->stock_minimo;
@endphp

@section('content')

    <div class="container tienda-pagina">

        {{-- ── Migas ───────────────────────────────────────────────────── --}}
        <nav class="tienda-migas" aria-label="Ruta">
            <a href="{{ route('tienda.index') }}">Catálogo</a>
            @if ($producto->categoria)
                <span class="tienda-migas-sep">/</span>
                <a href="{{ route('tienda.index', ['categoria' => $producto->categoria->slug]) }}">
                    {{ $producto->categoria->nombre }}
                </a>
            @endif
            <span class="tienda-migas-sep">/</span>
            <span class="tienda-migas-actual">{{ $producto->nombre }}</span>
        </nav>

        {{-- ── Ficha ───────────────────────────────────────────────────── --}}
        <div class="tienda-ficha">

            <div class="tienda-ficha-media">
                <div class="tienda-ficha-imagen">
                    @if ($producto->imagen)
                        <img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}">
                    @else
                        <img src="{{ asset('assets/images/sin_imagen.png') }}" alt="{{ $producto->nombre }}"
                            class="tienda-ficha-sin-imagen">
                    @endif

                    @if ($agotado)
                        <span class="tienda-card-franja">Agotado</span>
                    @endif
                </div>
            </div>

            <div class="tienda-ficha-info">
                <div class="tienda-ficha-meta">
                    @if ($producto->marca)
                        <span class="tienda-ficha-marca">{{ $producto->marca->nombre }}</span>
                    @endif
                    @if ($producto->categoria)
                        <span class="tienda-ficha-categoria">{{ $producto->categoria->nombre }}</span>
                    @endif
                </div>

                <h1 class="tienda-ficha-titulo">{{ $producto->nombre }}</h1>

                @if ($producto->modelo)
                    <p class="tienda-ficha-modelo">Modelo {{ $producto->modelo }}</p>
                @endif

                <div class="tienda-ficha-precio">
                    <span class="tienda-ficha-precio-valor">
                        Bs {{ number_format((float) $producto->precio_venta, 2, ',', '.') }}
                    </span>
                    <span class="tienda-ficha-disponibilidad
                        @if ($agotado) is-agotado @elseif ($bajo) is-bajo @else is-ok @endif">
                        <span class="tienda-ficha-punto"></span>
                        @if ($agotado)
                            Sin stock
                        @elseif ($bajo)
                            Últimas {{ $disponibles }} {{ $disponibles === 1 ? 'unidad' : 'unidades' }}
                        @else
                            {{ $disponibles }} {{ $disponibles === 1 ? 'unidad' : 'unidades' }} disponibles
                        @endif
                    </span>
                </div>

                @if ($producto->descripcion)
                    <p class="tienda-ficha-descripcion">{{ $producto->descripcion }}</p>
                @endif

                <ul class="tienda-ficha-datos">
                    @if ($producto->modelo)
                        <li>
                            <span class="tienda-ficha-dato-etiqueta">Modelo</span>
                            <span class="tienda-ficha-dato-valor">{{ $producto->modelo }}</span>
                        </li>
                    @endif
                    @if ($producto->meses_garantia)
                        <li>
                            <span class="tienda-ficha-dato-etiqueta">Garantía</span>
                            <span class="tienda-ficha-dato-valor">{{ $producto->meses_garantia }} meses</span>
                        </li>
                    @endif
                    @if ($producto->marca)
                        <li>
                            <span class="tienda-ficha-dato-etiqueta">Marca</span>
                            <span class="tienda-ficha-dato-valor">{{ $producto->marca->nombre }}</span>
                        </li>
                    @endif
                </ul>

                <div class="tienda-ficha-acciones">
                    <a href="{{ route('tienda.index', ['categoria' => $producto->categoria?->slug]) }}"
                        class="tienda-btn tienda-btn-primary">
                        <i class="ri-arrow-left-line"></i> Seguir explorando
                    </a>
                    <span class="tienda-ficha-nota">
                        <i class="ri-store-2-line"></i> Disponible en tienda
                    </span>
                </div>
            </div>
        </div>

        {{-- ── Características ─────────────────────────────────────────── --}}
        @if ($producto->especificaciones->isNotEmpty())
            <section class="tienda-seccion tienda-seccion--plana">
                <header class="tienda-seccion-header">
                    <div class="tienda-seccion-icono"><i class="ri-list-check-2"></i></div>
                    <div class="tienda-seccion-texto">
                        <h2 class="tienda-seccion-titulo">Características</h2>
                    </div>
                </header>
                <ul class="tienda-specs">
                    @foreach ($producto->especificaciones as $spec)
                        <li class="tienda-spec">
                            <span class="tienda-spec-clave">{{ $spec->clave }}</span>
                            <span class="tienda-spec-valor">{{ $spec->valor ?? 'Sí' }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- ── Relacionados ────────────────────────────────────────────── --}}
        @if ($relacionados->isNotEmpty())
            <section class="tienda-seccion">
                <header class="tienda-seccion-header">
                    <div class="tienda-seccion-icono"><i class="ri-links-line"></i></div>
                    <div class="tienda-seccion-texto">
                        <h2 class="tienda-seccion-titulo">Productos relacionados</h2>
                        <p class="tienda-seccion-sub">De la misma categoría</p>
                    </div>
                </header>
                <div class="tienda-grid">
                    @foreach ($relacionados as $relacionado)
                        @include('tienda.partials.tarjeta-producto', ['producto' => $relacionado])
                    @endforeach
                </div>
            </section>
        @endif

    </div>

@endsection
