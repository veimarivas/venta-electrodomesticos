@extends('backend.layouts.master')

@section('title', 'Vitrina')

@section('content')
    <div class="vitrina-modulo">

        {{-- ===================== Hero principal ===================== --}}
        <div class="card border-0 shadow-sm overflow-hidden mb-4">
            <div class="card-body p-0">
                <div class="p-4 vitrina-hero">
                    <div class="vitrina-hero-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="vitrina-hero-tile rounded-3 d-grid align-items-center justify-content-center"
                             style="width:3.2rem; height:3.2rem;">
                            <i class="ri-store-2-line fs-4" style="color: #fff;"></i>
                        </div>
                        <div>
                            <h2 class="mb-0">Vitrina</h2>
                            <p class="mb-0">Explora nuestro catálogo completo de productos</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">

            {{-- ===================== Recomendados ===================== --}}
            <div class="col-12">
                <div class="vitrina-recomendados">
                    <div class="vitrina-recomendados-header">
                        <div class="vitrina-recomendados-icon">
                            <i class="ri-fire-line"></i>
                        </div>
                        <div>
                            <h4>Recomendados</h4>
                            <p class="vitrina-recomendados-sub mb-0">Los más vendidos de este mes, para reponer y para recomendar</p>
                        </div>
                    </div>
                    <div class="p-3">
                        @if ($recomendados->isEmpty())
                            <div class="vitrina-empty">
                                <div class="vitrina-empty-icon">
                                    <i class="ri-inbox-line"></i>
                                </div>
                                <p class="vitrina-empty-texto">Todavía no hay ventas que recomendar este mes.</p>
                            </div>
                        @else
                            <div class="row g-3">
                                @foreach ($recomendados as $producto)
                                    @include('backend.catalogo.partials.tarjeta-producto', ['producto' => $producto])
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ===================== Categorías ===================== --}}
            @forelse ($categorias as $categoria)
                <div class="col-12">
                    <div class="vitrina-categoria">
                        <div class="vitrina-categoria-header">
                            <div class="vitrina-categoria-titulo">
                                <h4>{{ $categoria->nombre }}</h4>
                                <span class="vitrina-categoria-contador">
                                    <i class="ri-box-3-line" style="font-size: .65rem;"></i>
                                    {{ $categoria->productos->count() }}
                                    {{ $categoria->productos->count() === 1 ? 'producto' : 'productos' }}
                                </span>
                            </div>
                        </div>
                        <div class="vitrina-categoria-body">
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
                    <div class="vitrina-categoria">
                        <div class="vitrina-empty">
                            <div class="vitrina-empty-icon">
                                <i class="ri-archive-drawer-line"></i>
                            </div>
                            <p class="vitrina-empty-texto">Todavía no hay productos con categoría que mostrar.</p>
                        </div>
                    </div>
                </div>
            @endforelse

        </div>
    </div>
@endsection
