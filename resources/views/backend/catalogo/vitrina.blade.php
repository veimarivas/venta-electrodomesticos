@extends('backend.layouts.master')

@section('title', 'Vitrina')

@section('content')
    @php
        $totalProductos = $categorias->sum(fn ($categoria) => $categoria->productos->count());
        $totalCategorias = $categorias->count();
    @endphp

    <div class="vitrina-modulo">

        {{-- ===================== Encabezado ===================== --}}
        <header class="vitrina-hero mb-4">
            <div class="vitrina-hero-bg" aria-hidden="true"></div>
            <div class="vitrina-hero-content">
                <div class="vitrina-hero-texto">
                    <h1 class="vitrina-hero-titulo">Vitrina</h1>
                    <p class="vitrina-hero-subtitulo">
                        Todo el catálogo, ordenado por categoría, para mostrar y recomendar con el cliente delante.
                    </p>
                </div>
                <div class="vitrina-hero-acciones">
                    <div class="vitrina-hero-stat">
                        <span class="vitrina-hero-stat-valor">{{ number_format($totalProductos, 0, ',', '.') }}</span>
                        <span class="vitrina-hero-stat-label">productos</span>
                    </div>
                    <div class="vitrina-hero-stat">
                        <span class="vitrina-hero-stat-valor">{{ $totalCategorias }}</span>
                        <span class="vitrina-hero-stat-label">{{ $totalCategorias === 1 ? 'categoría' : 'categorías' }}</span>
                    </div>
                    @if ($hayVentas)
                        <div class="vitrina-hero-stat vitrina-hero-stat--oro">
                            <span class="vitrina-hero-stat-valor">{{ $recomendados->count() }}</span>
                            <span class="vitrina-hero-stat-label">recomendados</span>
                        </div>
                    @endif
                </div>
            </div>
        </header>

        {{-- ===================== Buscador y navegación ===================== --}}
        @if ($totalProductos > 0)
            <div class="vitrina-toolbar mb-4">
                <div class="vitrina-buscador">
                    <i class="ri-search-line" aria-hidden="true"></i>
                    <input type="search" class="form-control" placeholder="Buscar por producto o marca..."
                        data-vitrina-buscador autocomplete="off" aria-label="Buscar en la vitrina">
                    <button type="button" class="vitrina-buscador-limpiar" data-vitrina-limpiar hidden
                        aria-label="Limpiar búsqueda">
                        <i class="ri-close-circle-fill" aria-hidden="true"></i>
                    </button>
                </div>

                <nav class="vitrina-nav" aria-label="Categorías de la vitrina">
                    @if ($hayVentas)
                        <a href="#vitrina-recomendados" class="vitrina-nav-chip vitrina-nav-chip--oro" data-vitrina-nav>
                            <i class="ri-fire-line" aria-hidden="true"></i> Recomendados
                        </a>
                    @endif
                    @foreach ($categorias as $categoria)
                        <a href="#cat-{{ $categoria->id }}" class="vitrina-nav-chip" data-vitrina-nav>
                            {{ $categoria->nombre }}
                            <span class="vitrina-nav-chip-total">{{ $categoria->productos->count() }}</span>
                        </a>
                    @endforeach
                </nav>
            </div>
        @endif

        <div class="row g-4">

            {{-- ===================== Recomendados ===================== --}}
            @if ($hayVentas)
                <div class="col-12" id="vitrina-recomendados" data-vitrina-seccion>
                    <section class="vitrina-seccion vitrina-seccion--destacada">
                        <div class="vitrina-seccion-header">
                            <span class="vitrina-seccion-icono vitrina-seccion-icono--recomendados">
                                <i class="ri-fire-line" aria-hidden="true"></i>
                            </span>
                            <div class="vitrina-seccion-titulo-grupo">
                                <h2 class="vitrina-seccion-titulo">Recomendados</h2>
                                <p class="vitrina-seccion-subtitulo">Los más vendidos del mes, para reponer y recomendar</p>
                            </div>
                            <span class="vitrina-seccion-conteo">{{ $recomendados->count() }}</span>
                        </div>
                        <div class="vitrina-seccion-body">
                            <div class="row g-3">
                                @foreach ($recomendados as $producto)
                                    @include('backend.catalogo.partials.tarjeta-producto', ['producto' => $producto])
                                @endforeach
                            </div>
                        </div>
                    </section>
                </div>
            @endif

            {{-- ===================== Categorías ===================== --}}
            @forelse ($categorias as $categoria)
                <div class="col-12" id="cat-{{ $categoria->id }}" data-vitrina-seccion>
                    <section class="vitrina-seccion">
                        <div class="vitrina-seccion-header">
                            <span class="vitrina-seccion-icono vitrina-seccion-icono--categoria">
                                <i class="ri-folder-3-line" aria-hidden="true"></i>
                            </span>
                            <div class="vitrina-seccion-titulo-grupo">
                                <h2 class="vitrina-seccion-titulo">{{ $categoria->nombre }}</h2>
                                <p class="vitrina-seccion-subtitulo">
                                    {{ $categoria->productos->count() }}
                                    {{ $categoria->productos->count() === 1 ? 'producto disponible' : 'productos disponibles' }}
                                </p>
                            </div>
                            <span class="vitrina-seccion-conteo">{{ $categoria->productos->count() }}</span>
                        </div>
                        <div class="vitrina-seccion-body">
                            <div class="row g-3">
                                @foreach ($categoria->productos as $producto)
                                    @include('backend.catalogo.partials.tarjeta-producto', ['producto' => $producto])
                                @endforeach
                            </div>
                        </div>
                    </section>
                </div>
            @empty
                <div class="col-12">
                    <div class="vitrina-vacio">
                        <div class="vitrina-vacio-icono">
                            <i class="ri-archive-drawer-line"></i>
                        </div>
                        <h5>La vitrina está vacía</h5>
                        <p>Cuando agregues productos con categoría, aparecerán aquí listos para mostrar.</p>
                    </div>
                </div>
            @endforelse

        </div>

        {{-- ===================== Sin resultados de búsqueda ===================== --}}
        <div class="vitrina-sin-resultados" data-vitrina-sin-resultados hidden>
            <i class="ri-search-eye-line" aria-hidden="true"></i>
            <h5>Sin resultados</h5>
            <p>Ningún producto coincide con esa búsqueda. Prueba con otro nombre o marca.</p>
        </div>

    </div>
@endsection
