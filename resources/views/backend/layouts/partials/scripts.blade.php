<!-- JAVASCRIPT base de la plantilla -->
<script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/libs/node-waves/waves.min.js') }}"></script>
<script src="{{ asset('assets/libs/feather-icons/feather.min.js') }}"></script>
<script src="{{ asset('assets/js/pages/plugins/lord-icon-2.1.0.js') }}"></script>

{{--
    assets/js/plugins.js NO se carga a propósito: usa document.writeln() con
    rutas relativas ("assets/libs/...") que se rompen en cualquier ruta
    anidada como /inventario/items, y además trae toastify desde un CDN
    externo. Sus librerías (flatpickr, SweetAlert2) las provee Vite.
--}}

<!-- Librerías específicas de la página -->
@stack('vendor-js')

<!-- App js de la plantilla (menú, layout, scroll) -->
<script src="{{ asset('assets/js/app.js') }}"></script>

<!-- Bundle propio del proyecto (Echo, helpers, confirmaciones) -->
@vite(['resources/js/app.js'])

{{-- Mensajes flash de la sesión convertidos en toast --}}
@if (session()->hasAny(['success', 'error', 'warning', 'info']))
    @php
        $flashType = collect(['success', 'error', 'warning', 'info'])->first(fn ($type) => session()->has($type));
    @endphp
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.toast(@json($flashType), @json(session($flashType)));
        });
    </script>
@endif

@stack('js')
