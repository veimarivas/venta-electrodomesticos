@extends('backend.layouts.master')

@section('title', $title)

@section('content')
    @livewire('gastos.index')
@endsection
