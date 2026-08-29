@php
    $menu = app(\App\Support\MenuBuilder::class)->build();
@endphp

<div class="app-menu navbar-menu">
    <!-- LOGO -->
    <div class="navbar-brand-box">
        <a href="{{ route('dashboard') }}" class="logo logo-dark">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/marca-sidebar.png') }}" alt="{{ config('app.name') }}" height="34">
            </span>
            <span class="logo-lg">
                <img src="{{ asset('assets/images/marca-sidebar.png') }}" alt="{{ config('app.name') }}" height="84">
            </span>
        </a>
        <a href="{{ route('dashboard') }}" class="logo logo-light">
            <span class="logo-sm">
                <img src="{{ asset('assets/images/marca-sidebar.png') }}" alt="{{ config('app.name') }}" height="34">
            </span>
            <span class="logo-lg">
                <img src="{{ asset('assets/images/marca-sidebar.png') }}" alt="{{ config('app.name') }}" height="84">
            </span>
        </a>
        <button type="button" class="btn btn-sm p-0 fs-20 header-item float-end btn-vertical-sm-hover"
            id="vertical-hover">
            <i class="ri-record-circle-line"></i>
        </button>
    </div>

    <div id="scrollbar">
        <div class="container-fluid">

            <div id="two-column-menu"></div>

            <ul class="navbar-nav" id="navbar-nav">
                @foreach ($menu as $item)
                    @include('backend.layouts.partials.menu-item', ['item' => $item, 'level' => 0])
                @endforeach
            </ul>
        </div>
    </div>

    <div class="sidebar-background"></div>
</div>
