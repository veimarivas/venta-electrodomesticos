@extends('backend.layouts.master')

@section('title', $title)

@section('content')
    @livewire('compras.verificar', ['compra' => $compra])
@endsection
