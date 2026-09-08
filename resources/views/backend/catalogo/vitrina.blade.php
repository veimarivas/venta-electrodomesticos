@extends('backend.layouts.master')

@section('title', 'Vitrina')

@section('content')
    <div class="vitrina-modulo">

        {{-- ===================== Hero principal ===================== --}}
        <div class="vitrina-hero mb-4">
            <div class="vitrina-hero-bg" aria-hidden="true"></div>
            <div class="vitrina-hero-content">
                <div class="vitrina-hero-texto">
                    <span class="vitrina-hero-chip">
                        <i class="ri-store-2-line"></i>
                        Catálogo
                    </span>
                    <h1 class="vitrina-hero-titulo">Vitrina</h1>
                    <p class="vitrina-hero-subtitulo">
                        Explora nuestro catálogo completo de productos organizados por categoría.
                    </p>
                </div>
                <div class="vitrina-hero-acciones">
                    <div class="vitrina-hero-stat">
                        <span class="vitrina-hero-stat-valor">{{ $categorias->pluck('productos')->flatten()->count() }}</span>
                        <span class="vitrina-hero-stat-label">productos</span>
                    </div>
                    <div class="vitrina-hero-stat">
                        <span class="vitrina-hero-stat-valor">{{ $categorias->count() }}</span>
                        <span class="vitrina-hero-stat-label">categorías</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- ===================== Recomendados ===================== --}}
            @if ($hayVentas)
                <div class="col-12">
                    <div class="vitrina-seccion">
                        <div class="vitrina-seccion-header">
                            <div class="vitrina-seccion-icono vitrina-seccion-icono--recomendados">
                                <i class="ri-fire-line"></i>
                            </div>
                            <div>
                                <h4 class="vitrina-seccion-titulo">Recomendados</h4>
                                <p class="vitrina-seccion-subtitulo">Los más vendidos de este mes, para reponer y recomendar</p>
                            </div>
                            <span class="vitrina-seccion-badge">{{ $recomendados->count() }}</span>
                        </div>
                        <div class="vitrina-seccion-body">
                            <div class="row g-3">
                                @foreach ($recomendados as $producto)
                                    @include('backend.catalogo.partials.tarjeta-producto', ['producto' => $producto, 'recomendado' => true])
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            {{-- ===================== Categorías ===================== --}}
            @forelse ($categorias as $categoria)
                <div class="col-12">
                    <div class="vitrina-seccion">
                        <div class="vitrina-seccion-header">
                            <div class="vitrina-seccion-icono vitrina-seccion-icono--categoria">
                                <i class="ri-folder-3-line"></i>
                            </div>
                            <div>
                                <h4 class="vitrina-seccion-titulo">{{ $categoria->nombre }}</h4>
                                <p class="vitrina-seccion-subtitulo">
                                    {{ $categoria->productos->count() }}
                                    {{ $categoria->productos->count() === 1 ? 'producto' : 'productos' }}
                                </p>
                            </div>
                        </div>
                        <div class="vitrina-seccion-body">
                            <div class="row g-3">
                                @foreach ($categoria->productos as $producto)
                                    @include('backend.catalogo.partials.tarjeta-producto', ['producto' => $producto])
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="vitrina-vacio">
                        <div class="vitrina-vacio-icono">
                            <i class="ri-archive-drawer-line"></i>
                        </div>
                        <h5>Todavía no hay productos</h5>
                        <p>El catálogo se llenará cuando se agreguen productos con categoría.</p>
                    </div>
                </div>
            @endforelse

        </div>
    </div>
@endsection
