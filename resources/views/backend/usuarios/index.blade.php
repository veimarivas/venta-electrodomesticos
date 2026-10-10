@extends('backend.layouts.master')

@section('title', 'Usuarios')

@section('content')
    @livewire('usuarios.index')

    {{-- Seguridad de la app: minutos sin uso y teléfonos con huella. --}}
    @can('ajustes.editar')
        <div class="mt-4">
            @livewire('sistema.sesion-app')
        </div>
    @endcan
@endsection
