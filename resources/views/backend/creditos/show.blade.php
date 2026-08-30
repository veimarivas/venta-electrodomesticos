@extends('backend.layouts.master')

@section('title', 'Crédito')

@section('content')
    @livewire('creditos.show', ['credito' => $credito])
@endsection
