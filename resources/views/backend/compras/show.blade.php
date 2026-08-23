@extends('backend.layouts.master')

@section('title', $title)

@section('content')
    @livewire('compras.show', ['compra' => $compra])
@endsection