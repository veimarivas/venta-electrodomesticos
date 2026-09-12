<!doctype html>
<html lang="es" data-bs-theme="light">

<head>
    @include('backend.layouts.partials.head')
    @include('backend.layouts.partials.head-css')
</head>

<body class="tienda-body">

    {{-- Barra superior: marca, catálogo y acceso del personal. --}}
    <header class="tienda-nav">
        <div class="container">
            <div class="tienda-nav-inner">
                <a href="{{ route('tienda.index') }}" class="tienda-logo" aria-label="{{ config('app.name') }}">
                    <img src="{{ asset('assets/images/marca-sidebar.png') }}" alt="{{ config('app.name') }}">
                </a>

                <nav class="tienda-nav-menu" aria-label="Principal">
                    <a href="{{ route('tienda.index') }}"
                        class="tienda-nav-link {{ request()->routeIs('tienda.index') && !request()->query() ? 'is-active' : '' }}">
                        Catálogo
                    </a>
                    <a href="{{ route('tienda.index') }}?disponible=1"
                        class="tienda-nav-link {{ request()->query('disponible') ? 'is-active' : '' }}">
                        Disponibles
                    </a>
                </nav>

                <div class="tienda-nav-actions">
                    @auth
                        <a href="{{ route('dashboard') }}" class="tienda-btn tienda-btn-ghost">
                            <i class="ri-dashboard-3-line"></i> Panel
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="tienda-btn tienda-btn-primary">
                            <i class="ri-lock-2-line"></i> Acceso
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </header>

    <main class="tienda-main">
        @yield('content')
    </main>

    <footer class="tienda-footer">
        <div class="container">
            <div class="tienda-footer-inner">
                <div class="tienda-footer-brand">
                    <img src="{{ asset('assets/images/marca-sidebar.png') }}"
                        alt="{{ config('app.name') }}">
                    <p>Tecnología para tu vida. Catálogo de electrodomésticos de la tienda.</p>
                </div>

                <nav class="tienda-footer-links" aria-label="Enlaces del pie">
                    <a href="{{ route('tienda.index') }}">Catálogo</a>
                    <a href="{{ route('tienda.index') }}?disponible=1">Disponibles</a>
                    <a href="{{ route('login') }}">Acceso al panel</a>
                </nav>
            </div>

            <div class="tienda-footer-bottom">
                &copy; {{ date('Y') }} {{ config('app.name') }} &middot; Todos los derechos reservados.
            </div>
        </div>
    </footer>

    <script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @stack('js')
</body>

</html>
