@extends('backend.layouts.master')

@section('title', $proveedor->nombre)

@section('content')
    @livewire('proveedores.show', ['proveedor' => $proveedor])
@endsection