@extends('backend.layouts.master')

@section('title', 'Búsqueda')

@section('content')
    @if ($query === '')
        <x-card>
            <div class="text-center py-5">
                <i class="ri-search-2-line display-5 text-muted"></i>
                <h5 class="mt-3">Escribe algo para buscar</h5>
                <p class="text-muted mb-0">Puedes buscar por nombre de producto, serial, código interno o número de
                    venta.</p>
            </div>
        </x-card>
    @elseif (empty($results))
        <x-card>
            <div class="text-center py-5">
                <i class="ri-file-search-line display-5 text-muted"></i>
                <h5 class="mt-3">Sin resultados para «{{ $query }}»</h5>
                <p class="text-muted mb-0">Revisa la ortografía o prueba con menos palabras.</p>
            </div>
        </x-card>
    @else
        <p class="text-muted">Resultados para «<span class="fw-semibold">{{ $query }}</span>»</p>

        @foreach ($results as $grupo)
            <x-card class="border-0 shadow-sm mb-4">
                <h5 class="card-title mb-3 d-flex align-items-center gap-2">
                    <i class="{{ $grupo['icono'] }}"></i> {{ $grupo['titulo'] }}
                    <span class="badge bg-light text-body">{{ count($grupo['items']) }}</span>
                </h5>

                <div class="list-group list-group-flush">
                    @foreach ($grupo['items'] as $item)
                        {{-- Sin destino permitido la fila sigue siendo útil —dice que
                             el aparato existe y en qué estado— pero no finge ser un
                             enlace que no lleva a ninguna parte. --}}
                        @if ($item['url'])
                            <a href="{{ $item['url'] }}"
                                class="list-group-item list-group-item-action d-flex align-items-center gap-3 px-0">
                                @include('backend.search.partials.fila', ['item' => $item])
                            </a>
                        @else
                            <div class="list-group-item d-flex align-items-center gap-3 px-0">
                                @include('backend.search.partials.fila', ['item' => $item])
                            </div>
                        @endif
                    @endforeach
                </div>
            </x-card>
        @endforeach
    @endif
@endsection
