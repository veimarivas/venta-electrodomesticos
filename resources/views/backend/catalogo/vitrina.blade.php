@extends('backend.layouts.master')

@section('title', 'Vitrina')

@section('content')
    <div class="row g-4">

        {{-- ===================== Recomendados ===================== --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header align-items-center d-flex">
                    <div class="flex-grow-1">
                        <h4 class="card-title mb-0">
                            <i class="ri-fire-line align-bottom me-1 text-danger"></i> Recomendados
                        </h4>
                        <p class="text-muted mb-0 fs-13">Los más vendidos de este mes, para reponer y para recomendar</p>
                    </div>
                </div>
                <div class="card-body">
                    @if ($recomendados->isEmpty())
                        <p class="text-muted mb-0">Todavía no hay ventas que recomendar este mes.</p>
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
                <div class="card">
                    <div class="card-header align-items-center d-flex">
                        <div class="flex-grow-1">
                            <h4 class="card-title mb-0">{{ $categoria->nombre }}</h4>
                            <p class="text-muted mb-0 fs-13">
                                {{ $categoria->productos->count() }}
                                {{ $categoria->productos->count() === 1 ? 'producto' : 'productos' }}
                            </p>
                        </div>
                    </div>
                    <div class="card-body">
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
                <div class="card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="ri-archive-drawer-line fs-1 d-block mb-2 opacity-50"></i>
                        Todavía no hay productos con categoría que mostrar.
                    </div>
                </div>
            </div>
        @endforelse

    </div>
@endsection