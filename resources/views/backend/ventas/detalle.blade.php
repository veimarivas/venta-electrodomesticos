@extends('backend.layouts.master')

@section('title', $title)

@section('content')
    @livewire('ventas.show', ['venta' => $venta])
@endsection
