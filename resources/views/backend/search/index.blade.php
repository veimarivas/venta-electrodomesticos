@extends('backend.layouts.master')

@section('title', 'Búsqueda')

@section('content')
    <x-card>
        @if ($query === '')
            <div class="text-center py-5">
                <i class="ri-search-2-line display-5 text-muted"></i>
                <h5 class="mt-3">Escribe algo para buscar</h5>
                <p class="text-muted mb-0">Puedes buscar por nombre de producto, serial, código interno o número de venta.</p>
            </div>
        @elseif (empty($results))
            <div class="text-center py-5">
                <i class="ri-file-search-line display-5 text-muted"></i>
                <h5 class="mt-3">Sin resultados para «{{ $query }}»</h5>
                <p class="text-muted mb-0">Revisa la ortografía o prueba con menos palabras.</p>
            </div>
        @endif
    </x-card>
@endsection
