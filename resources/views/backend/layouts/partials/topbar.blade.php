@php
    $user = auth()->user();
    $unread = $user?->unreadNotifications ?? collect();
@endphp

<header id="page-topbar">
    <div class="layout-width">
        <div class="navbar-header">
            <div class="d-flex">
                <!-- LOGO -->
                <div class="navbar-brand-box horizontal-logo">
                    <a href="{{ route('dashboard') }}" class="logo logo-dark">
                        <span class="logo-sm">
                            <img src="{{ asset('assets/images/marca-sidebar.png') }}" alt="{{ config('app.name') }}" height="34">
                        </span>
                        <span class="logo-lg">
                            <img src="{{ asset('assets/images/marca-sidebar.png') }}" alt="{{ config('app.name') }}" height="54">
                        </span>
                    </a>
                    <a href="{{ route('dashboard') }}" class="logo logo-light">
                        <span class="logo-sm">
                            <img src="{{ asset('assets/images/marca-sidebar.png') }}" alt="{{ config('app.name') }}" height="34">
                        </span>
                        <span class="logo-lg">
                            <img src="{{ asset('assets/images/marca-sidebar.png') }}" alt="{{ config('app.name') }}" height="54">
                        </span>
                    </a>
                </div>

                <button type="button"
                    class="btn btn-sm px-3 fs-16 header-item vertical-menu-btn topnav-hamburger material-shadow-none"
                    id="topnav-hamburger-icon">
                    <span class="hamburger-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </button>

                <!-- Buscador global: productos, seriales, ventas -->
                <form class="app-search d-none d-md-block" action="{{ route('search') }}" method="GET"
                    autocomplete="off">
                    <div class="position-relative">
                        <input type="text" class="form-control" name="q" placeholder="Buscar producto, serial o venta..."
                            id="search-options" value="{{ request('q') }}">
                        <span class="mdi mdi-magnify search-widget-icon"></span>
                        <span class="mdi mdi-close-circle search-widget-icon search-widget-icon-close d-none"
                            id="search-close-options"></span>
                    </div>
                    <div class="dropdown-menu dropdown-menu-lg" id="search-dropdown">
                        <div data-simplebar style="max-height: 320px;">
                            <div class="dropdown-header">
                                <h6 class="text-overflow text-muted mb-0 text-uppercase">Accesos rápidos</h6>
                            </div>
                            <a href="{{ route('dashboard') }}" class="dropdown-item notify-item">
                                <i class="ri-dashboard-2-line align-middle fs-18 text-muted me-2"></i>
                                <span>Dashboard de ventas</span>
                            </a>
                            <a href="javascript:void(0);" class="dropdown-item notify-item">
                                <i class="ri-barcode-line align-middle fs-18 text-muted me-2"></i>
                                <span>Buscar por serial / código interno</span>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="d-flex align-items-center">

                <!-- Buscador en móvil -->
                <div class="dropdown d-md-none topbar-head-dropdown header-item">
                    <button type="button"
                        class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle"
                        id="page-header-search-dropdown" data-bs-toggle="dropdown" aria-haspopup="true"
                        aria-expanded="false">
                        <i class="bx bx-search fs-22"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                        aria-labelledby="page-header-search-dropdown">
                        <form class="p-3" action="{{ route('search') }}" method="GET">
                            <div class="form-group m-0">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="q" placeholder="Buscar...">
                                    <button class="btn btn-primary" type="submit"><i class="mdi mdi-magnify"></i></button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Pantalla completa -->
                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button"
                        class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle"
                        data-toggle="fullscreen">
                        <i class='bx bx-fullscreen fs-22'></i>
                    </button>
                </div>

                <!-- Modo claro / oscuro -->
                <div class="ms-1 header-item d-none d-sm-flex">
                    <button type="button"
                        class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle light-dark-mode">
                        <i class='bx bx-moon fs-22'></i>
                    </button>
                </div>

                <!-- Notificaciones de ventas (se actualizan en vivo por WebSocket) -->
                <div class="dropdown topbar-head-dropdown ms-1 header-item" id="notificationDropdown">
                    <button type="button"
                        class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle"
                        id="page-header-notifications-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                        aria-haspopup="true" aria-expanded="false">
                        <i class='bx bx-bell fs-22'></i>
                        <span
                            class="position-absolute topbar-badge fs-10 translate-middle badge rounded-pill bg-danger {{ $unread->isEmpty() ? 'd-none' : '' }}"
                            id="notification-count">{{ $unread->count() }}<span class="visually-hidden">notificaciones sin leer</span></span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-end p-0"
                        aria-labelledby="page-header-notifications-dropdown">

                        <div class="dropdown-head bg-primary bg-pattern rounded-top">
                            <div class="p-3">
                                <div class="row align-items-center">
                                    <div class="col">
                                        <h6 class="m-0 fs-16 fw-semibold text-white">Notificaciones</h6>
                                    </div>
                                    <div class="col-auto dropdown-tabs">
                                        <span class="badge bg-light text-body fs-13" id="notification-new-badge">{{ $unread->count() }} nuevas</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="py-2 ps-2" id="notification-list-wrapper">
                            <div data-simplebar style="max-height: 300px;" class="pe-2">
                                <div id="notification-list">
                                    @forelse ($unread as $notification)
                                        <div class="text-reset notification-item d-block dropdown-item position-relative"
                                            data-notification-id="{{ $notification->id }}">
                                            <div class="d-flex">
                                                <div class="avatar-xs me-3 flex-shrink-0">
                                                    <span class="avatar-title bg-success-subtle text-success rounded-circle fs-16">
                                                        <i class="bx bx-cart"></i>
                                                    </span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <a href="{{ $notification->data['url'] ?? '#' }}" class="stretched-link">
                                                        <h6 class="mt-0 mb-2 lh-base">{{ $notification->data['title'] ?? 'Nueva venta' }}</h6>
                                                    </a>
                                                    <p class="mb-0 fs-11 fw-medium text-uppercase text-muted">
                                                        <span><i class="mdi mdi-clock-outline"></i>
                                                            {{ $notification->created_at->diffForHumans() }}</span>
                                                    </p>
                                                </div>
                                                <div class="px-2 fs-15">
                                                    <div class="form-check notification-check">
                                                        <input class="form-check-input" type="checkbox"
                                                            aria-label="Seleccionar notificación">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center py-4" id="notification-empty">
                                            <i class="bx bx-bell-off fs-24 text-muted"></i>
                                            <p class="text-muted mb-0 mt-2">Sin notificaciones nuevas</p>
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        {{--
                            Acciones masivas. assets/js/app.js enlaza listeners a
                            #notification-actions, #select-content, #delete-notification
                            y #removeNotificationModal sin comprobar si existen, así que
                            este bloque debe estar presente aunque no haya notificaciones.
                        --}}
                        <div class="notification-actions" id="notification-actions">
                            <div class="d-flex text-muted justify-content-center">
                                Seleccionadas
                                <div id="select-content" class="text-body fw-semibold px-1">0</div>
                                <button type="button" class="btn btn-link link-danger p-0 ms-3" data-bs-toggle="modal"
                                    data-bs-target="#removeNotificationModal">Eliminar</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Usuario -->
                <div class="dropdown ms-sm-3 header-item topbar-user">
                    <button type="button" class="btn material-shadow-none" id="page-header-user-dropdown"
                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                            <img class="rounded-circle header-profile-user"
                                src="{{ $user?->avatar_url ?? asset('assets/images/users/avatar-1.jpg') }}"
                                alt="Avatar">
                            <span class="text-start ms-xl-2">
                                <span
                                    class="d-none d-xl-inline-block ms-1 fw-medium user-name-text">{{ $user?->name }}</span>
                                <span
                                    class="d-none d-xl-block ms-1 fs-12 text-muted user-name-sub-text">{{ $user?->getRoleNames()->first() ?? 'Usuario' }}</span>
                            </span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">¡Hola, {{ Str::before($user?->name ?? '', ' ') }}!</h6>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}">
                            <i class="mdi mdi-account-circle text-muted fs-16 align-middle me-1"></i>
                            <span class="align-middle">Mi perfil</span>
                        </a>
                        <a class="dropdown-item" href="{{ route('profile.edit') }}#password">
                            <i class="mdi mdi-lock-outline text-muted fs-16 align-middle me-1"></i>
                            <span class="align-middle">Cambiar contraseña</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="mdi mdi-logout text-muted fs-16 align-middle me-1"></i>
                                <span class="align-middle">Cerrar sesión</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

{{-- Confirmación de borrado de notificaciones (requerido por assets/js/app.js) --}}
<div id="removeNotificationModal" class="modal fade zoomIn" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"
                    id="NotificationModalbtn-close"></button>
            </div>
            <div class="modal-body">
                <div class="mt-2 text-center">
                    <i class="ri-delete-bin-line display-5 text-danger"></i>
                    <div class="mt-4 pt-2 fs-15 mx-4 mx-sm-5">
                        <h4>¿Estás seguro?</h4>
                        <p class="text-muted mx-4 mb-0">Se quitarán las notificaciones seleccionadas de la lista.</p>
                    </div>
                </div>
                <div class="d-flex gap-2 justify-content-center mt-4 mb-2">
                    <button type="button" class="btn w-sm btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn w-sm btn-danger" id="delete-notification">Sí, eliminar</button>
                </div>
            </div>
        </div>
    </div>
</div>
