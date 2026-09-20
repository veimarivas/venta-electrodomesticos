<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('backend.layouts.partials.head')
    @include('backend.layouts.partials.head-css')
    <style>
        /*
            Login del panel.

            Es la primera pantalla del sistema y la única que ve alguien que aún
            no ha entrado, así que es donde la marca tiene que quedar clara. Los
            colores salen del logo y viven en resources/scss/components/
            _marca.scss; aquí se repiten como respaldo para que la pantalla se
            vea bien aunque el CSS compilado tarde en llegar.

            ---------------------------------------------------------------
            Responsive
            ---------------------------------------------------------------
            Todo usa clamp() contra el ancho de la ventana para que el tamaño
            se interpole suavemente entre un móvil de 320px y un monitor de 1920px.
        */
        .auth-body {
            --auth-noche: var(--marca-noche, #0a182b);
            --auth-noche-alta: var(--marca-noche-alta, #10233c);
            --auth-azul: var(--marca-azul, #254970);
            --auth-azul-hondo: var(--marca-azul-hondo, #1b3757);
            --auth-oro: var(--marca-oro, #c5a162);
            --auth-oro-claro: var(--marca-oro-claro, #d8bb85);
            --auth-crema: var(--marca-crema, #e7e2c2);
            --auth-apagado: #6b778a;
            --auth-linea: #e3e9f0;

            margin: 0;
            background: #fff;
            color: var(--auth-noche);
            font-family: Inter, "Segoe UI", sans-serif;
            /* iOS Safari ignora el zoom-out del formulario si el texto baja de
               16 px; con menos, al tocar un campo la página da un salto. */
            -webkit-text-size-adjust: 100%;
        }

        .auth-body ::selection { background: rgba(197, 161, 98, .28); }

        .auth-shell {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
        }

        /* ---------- Banda de marca (ocultada) ---------- */

        .auth-showcase { display: none; }

        /* ---------- Panel del formulario ---------- */

        .auth-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            padding: clamp(1rem, 3vw, 2rem) clamp(1rem, 3vw, 2rem);
            padding-bottom: max(clamp(1rem, 3vw, 2rem), env(safe-area-inset-bottom));
            background:
                radial-gradient(ellipse at 20% 0%, rgba(37, 73, 112, .06), transparent 50%),
                radial-gradient(ellipse at 80% 100%, rgba(197, 161, 98, .05), transparent 50%),
                linear-gradient(180deg, #fafbfc 0%, #fff 100%);
        }

        .auth-card {
            width: min(100%, 24rem);
            display: flex;
            flex-direction: column;
            max-height: 100%;
        }

        .auth-card > div:first-child h5 {
            margin: 0 0 .5rem;
            color: var(--auth-noche) !important;
            font-size: clamp(1.45rem, 2vw, 1.75rem);
            letter-spacing: -.035em;
            font-weight: 700;
            text-wrap: balance;
        }

        .auth-card > div:first-child p {
            margin: 0;
            color: var(--auth-apagado) !important;
            font-size: clamp(.88rem, 1vw, .95rem);
            line-height: 1.55;
        }

        .auth-card .mt-4 { margin-top: 1.75rem !important; }

        .auth-card .form-label {
            margin-bottom: .5rem;
            color: #2c3a4d;
            font-size: .84rem;
            font-weight: 650;
        }

        .auth-card .form-control {
            min-height: 2.6rem;
            border: 1px solid var(--auth-linea);
            border-radius: .6rem;
            /* 16 px exactos: por debajo, iOS hace zoom al enfocar el campo y
               descuadra la pantalla entera. */
            font-size: .9rem;
            color: var(--auth-noche);
            background: #fbfcfe;
            box-shadow: none;
            transition: border-color .18s, box-shadow .18s, background .18s;
            caret-color: var(--auth-azul);
        }

        .auth-card .form-control::placeholder { color: #a3aebd; }

        .auth-card .form-control:focus {
            border-color: var(--auth-azul);
            background: #fff;
            box-shadow: 0 0 0 .22rem rgba(37, 73, 112, .12);
        }

        /* El icono del campo se enciende con el foco: confirma dónde se escribe
           sin añadir ningún texto más a la pantalla. */
        .auth-campo { position: relative; }
        .auth-campo > i:first-child {
            color: #93a1b2;
            transition: color .18s;
            pointer-events: none;
            z-index: 2;
        }
        .auth-campo:focus-within > i:first-child { color: var(--auth-azul); }

        .auth-card .password-addon {
            height: 3.1rem;
            padding: 0 1rem;
            color: #7d8b9c !important;
        }

        .auth-card .form-check-input { border-color: #b6c2d1; }
        .auth-card .form-check-input:checked {
            background-color: var(--auth-azul);
            border-color: var(--auth-azul);
        }
        .auth-card .form-check-input:focus {
            border-color: var(--auth-azul);
            box-shadow: 0 0 0 .2rem rgba(37, 73, 112, .15);
        }

        .auth-card .form-check-label,
        .auth-card .text-muted { color: var(--auth-apagado) !important; font-size: .88rem; }

        .auth-card a { color: var(--auth-azul) !important; font-weight: 600; text-decoration: none; }
        .auth-card a:hover { color: var(--auth-azul-hondo) !important; text-decoration: underline; }

        /*
            El botón es el azul de la marca. El hilo dorado de arriba es el único
            oro del formulario: repite la identidad del logo justo donde está la
            acción, sin teñir el botón de un color que competiría con el azul.
        */
        .auth-card .btn-success {
            position: relative;
            overflow: hidden;
            min-height: 2.8rem;
            border: 0;
            border-radius: .6rem;
            background: linear-gradient(135deg, var(--auth-noche), var(--auth-azul));
            box-shadow: 0 .5rem 1rem rgba(10, 24, 43, .22);
            font-weight: 650;
            letter-spacing: .01em;
            transition: transform .18s, box-shadow .18s, filter .18s;
        }

        .auth-card .btn-success::after {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--auth-oro), transparent);
            opacity: .85;
        }

        .auth-card .btn-success:hover,
        .auth-card .btn-success:focus {
            filter: brightness(1.12);
            box-shadow: 0 .85rem 1.6rem rgba(10, 24, 43, .3);
            transform: translateY(-1px);
        }

        .auth-card .btn-success:active { transform: translateY(0); }

        .auth-card .alert { border-radius: .7rem; font-size: .88rem; }

        .auth-nota {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            margin: 1.5rem 0 0;
            color: #97a3b2;
            font-size: .78rem;
            text-align: center;
        }
        .auth-nota i { color: var(--auth-oro); flex: 0 0 auto; }

        /* ---------------------------------------------------------------
           Responsive
           --------------------------------------------------------------- */

        /* Dedos, no ratón: los objetivos crecen a lo que se puede tocar. */
        @media (pointer: coarse) {
            .auth-card .form-control,
            .auth-card .password-addon,
            .auth-card .btn-success { min-height: 3.4rem; }
            .auth-card .form-check-input { width: 1.3em; height: 1.3em; }
        }

        @media (prefers-reduced-motion: reduce) {
            .auth-card .btn-success,
            .auth-card .form-control,
            .auth-campo > i:first-child { transition: none; }
        }

        @keyframes auth-rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: no-preference) {
            .auth-card { animation: auth-rise .45s ease-out both; }
        }
    </style>
</head>
<body class="auth-body">
    <main class="auth-shell">
        <aside class="auth-showcase" aria-label="Información de {{ config('app.name') }}">
            <a href="{{ url('/') }}" class="auth-logo">
                {{-- Recorte ajustado de logo_hogar.png: se le quitan los
                     márgenes vacíos, que en una imagen centrada solo sirven
                     para empequeñecer el logotipo. El logo va completo, con su
                     tira de categorías. --}}
                <img src="{{ asset('assets/images/marca-login.png') }}"
                     width="478" height="391"
                     alt="{{ config('app.name') }} · Tecnología para tu vida">
            </a>

            <div class="auth-showcase-content">
                <span class="auth-eyebrow"><i class="ri-flashlight-fill"></i> Panel de gestión</span>
                <h1>Todo tu negocio, <span>siempre bajo control.</span></h1>
                <p>Inventario, compras y ventas en un solo lugar, con la información al día para decidir con confianza.</p>
                <ul class="auth-points">
                    <li><i class="ri-checkbox-circle-fill"></i> Inventario actualizado en tiempo real</li>
                    <li><i class="ri-checkbox-circle-fill"></i> Acceso seguro para cada persona del equipo</li>
                    <li><i class="ri-checkbox-circle-fill"></i> Ventas y compras con historial completo</li>
                </ul>
            </div>

            <div class="auth-showcase-footer">© {{ date('Y') }} {{ config('app.name') }} · Gestión que conecta.</div>
        </aside>

        <section class="auth-panel">
            <div class="auth-card">
                @yield('content')
            </div>
        </section>
    </main>
    <script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/js/pages/password-addon.init.js') }}"></script>
    @stack('js')
</body>
</html>
