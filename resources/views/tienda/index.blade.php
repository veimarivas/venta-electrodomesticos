@extends('tienda.layout')

@section('title', 'Catálogo')

@php
    // Enlaces de las píldoras de categoría: conservan la marca y el filtro de
    // disponibilidad para no perder lo que ya se eligió.
    $categoriaActiva = $categoria?->slug;
    $chipBase = array_filter([
        'buscar' => $buscar !== '' ? $buscar : null,
        'marca' => $marcaId,
        'disponible' => $soloDisponibles ? 1 : null,
    ]);
@endphp

@section('content')

    {{-- ── Portada ─────────────────────────────────────────────────────── --}}
    <section class="tienda-hero">
        <div class="tienda-hero-bg" aria-hidden="true"></div>
        <div class="container tienda-hero-inner">
            <span class="tienda-hero-chip"><i class="ri-store-2-line"></i> Catálogo</span>
            <h1 class="tienda-hero-titulo">Todo para tu hogar, <span>a un vistazo.</span></h1>
            <p class="tienda-hero-sub">
                Explora por categorías, busca el modelo que necesitas y consulta la disponibilidad al día.
            </p>

            <form class="tienda-buscador" method="GET" action="{{ route('tienda.index') }}" role="search">
                <i class="ri-search-line tienda-buscador-icono" aria-hidden="true"></i>
                <input type="search" name="buscar" value="{{ $buscar }}"
                    placeholder="Busca por nombre o modelo…" aria-label="Buscar productos" autocomplete="off">
                <button type="submit" class="tienda-btn tienda-btn-primary">Buscar</button>
            </form>

            <div class="tienda-hero-stats">
                <div class="tienda-hero-stat">
                    <strong>{{ number_format($totalProductos, 0, ',', '.') }}</strong>
                    <span>productos</span>
                </div>
                <div class="tienda-hero-stat">
                    <strong>{{ $categoriasFiltro->count() }}</strong>
                    <span>categorías</span>
                </div>
                <div class="tienda-hero-stat">
                    <strong>{{ $marcasFiltro->count() }}</strong>
                    <span>marcas</span>
                </div>
            </div>
        </div>
    </section>

    <div class="container tienda-pagina">

        {{-- ── Filtros ─────────────────────────────────────────────────── --}}
        <section class="tienda-filtros" aria-label="Filtros del catálogo">
            <div class="tienda-chips">
                <a href="{{ route('tienda.index', $chipBase) }}"
                    class="tienda-chip {{ $categoriaActiva === null ? 'is-activa' : '' }}">
                    Todo
                </a>
                @foreach ($categoriasFiltro as $cat)
                    <a href="{{ route('tienda.index', $chipBase + ['categoria' => $cat->slug]) }}"
                        class="tienda-chip {{ $categoriaActiva === $cat->slug ? 'is-activa' : '' }}">
                        {{ $cat->nombre }}
                    </a>
                @endforeach
            </div>

            <form class="tienda-filtros-form" method="GET" action="{{ route('tienda.index') }}">
                @if ($buscar !== '')
                    <input type="hidden" name="buscar" value="{{ $buscar }}">
                @endif
                @if ($categoria)
                    <input type="hidden" name="categoria" value="{{ $categoria->slug }}">
                @endif

                <label class="tienda-select">
                    <i class="ri-trademark-line" aria-hidden="true"></i>
                    <select name="marca" onchange="this.form.submit()" aria-label="Filtrar por marca">
                        <option value="">Todas las marcas</option>
                        @foreach ($marcasFiltro as $marca)
                            <option value="{{ $marca->id }}" @selected($marcaId === $marca->id)>
                                {{ $marca->nombre }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="tienda-switch">
                    <input type="checkbox" name="disponible" value="1"
                        onchange="this.form.submit()" @checked($soloDisponibles)>
                    <span class="tienda-switch-track" aria-hidden="true"></span>
                    <span class="tienda-switch-texto">Solo disponibles</span>
                </label>

                @if ($hayFiltros)
                    <a href="{{ route('tienda.index') }}" class="tienda-limpiar">
                        <i class="ri-close-line" aria-hidden="true"></i> Limpiar
                    </a>
                @endif
            </form>
        </section>

        {{-- ── Resultados filtrados ────────────────────────────────────── --}}
        @if ($hayFiltros)
            <div class="tienda-resultados-cabecera">
                <h2 class="tienda-resultados-titulo">
                    @if ($buscar !== '')
                        Resultados para «{{ $buscar }}»
                    @elseif ($categoria)
                        {{ $categoria->nombre }}
                    @else
                        Catálogo
                    @endif
                </h2>
                <p class="tienda-resultados-sub">
                    {{ $productos->total() }}
                    {{ $productos->total() === 1 ? 'producto' : 'productos' }} encontrados
                </p>
            </div>

            @if ($productos->isNotEmpty())
                <div class="tienda-grid tienda-grid--entrada">
                    @foreach ($productos as $producto)
                        @include('tienda.partials.tarjeta-producto', ['producto' => $producto])
                    @endforeach
                </div>

                <div class="tienda-paginacion">
                    {{ $productos->links() }}
                </div>
            @else
                <div class="tienda-vacio">
                    <div class="tienda-vacio-icono"><i class="ri-search-eye-line"></i></div>
                    <h5>No encontramos productos</h5>
                    <p>Prueba con otro término o quita algún filtro.</p>
                    <a href="{{ route('tienda.index') }}" class="tienda-btn tienda-btn-primary">
                        Ver todo el catálogo
                    </a>
                </div>
            @endif

        {{-- ── Portada: recomendados y secciones por categoría ─────────── --}}
        @else

            @if ($recomendados->isNotEmpty())
                <section class="tienda-seccion">
                    <header class="tienda-seccion-header">
                        <div class="tienda-seccion-icono tienda-seccion-icono--destacado">
                            <i class="ri-fire-line"></i>
                        </div>
                        <div class="tienda-seccion-texto">
                            <h2 class="tienda-seccion-titulo">Recomendados</h2>
                            <p class="tienda-seccion-sub">Los más vendidos de este mes</p>
                        </div>
                    </header>
                    <div class="tienda-grid tienda-grid--entrada">
                        @foreach ($recomendados as $producto)
                            @include('tienda.partials.tarjeta-producto', ['producto' => $producto])
                        @endforeach
                    </div>
                </section>
            @endif

            @forelse ($secciones as $seccion)
                <section class="tienda-seccion">
                    <header class="tienda-seccion-header">
                        <div class="tienda-seccion-icono">
                            <i class="ri-folder-3-line"></i>
                        </div>
                        <div class="tienda-seccion-texto">
                            <h2 class="tienda-seccion-titulo">{{ $seccion->nombre }}</h2>
                            <p class="tienda-seccion-sub">
                                {{ $seccion->productos->count() }}
                                {{ $seccion->productos->count() === 1 ? 'producto' : 'productos' }}
                            </p>
                        </div>
                        <a href="{{ route('tienda.index', ['categoria' => $seccion->slug]) }}"
                            class="tienda-seccion-ver">
                            Ver todo <i class="ri-arrow-right-line" aria-hidden="true"></i>
                        </a>
                    </header>
                    <div class="tienda-grid">
                        @foreach ($seccion->productos as $producto)
                            @include('tienda.partials.tarjeta-producto', ['producto' => $producto])
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="tienda-vacio">
                    <div class="tienda-vacio-icono"><i class="ri-archive-drawer-line"></i></div>
                    <h5>Todavía no hay productos</h5>
                    <p>El catálogo se llenará cuando se agreguen productos con categoría.</p>
                </div>
            @endforelse

        @endif

    </div>

@endsection
