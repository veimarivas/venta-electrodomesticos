@php
    $p = $this->pendientes;

    // El componente no se monta en el propio POS (lo decide la barra superior),
    // así que aquí basta con que haya algo apartado.
    $mostrar = $p['cantidad'] > 0;
@endphp

<div class="ms-1 header-item" id="carrito-pendiente" wire:poll.60s="refrescar">
    @if ($mostrar)
        <a href="{{ route('ventas.create') }}"
            class="btn btn-icon btn-topbar material-shadow-none btn-ghost-secondary rounded-circle"
            id="carrito-pendiente-boton"
            title="Tienes {{ $p['cantidad'] }} {{ $p['cantidad'] === 1 ? 'aparato apartado' : 'aparatos apartados' }}. Se cierran solos en {{ $p['minutos'] }} min si no cobras.">
            <i class='bx bx-cart fs-22'></i>
            <span
                class="position-absolute topbar-badge fs-10 translate-middle badge rounded-pill bg-warning">{{ $p['cantidad'] }}<span
                    class="visually-hidden">aparatos apartados</span></span>
        </a>
    @endif
</div>
