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
            Cómo se adapta al dispositivo
            ---------------------------------------------------------------
            La regla es una sola: **el tamaño se interpola, no salta**. Todo lo
            que crece —el logo, los títulos, los márgenes— usa clamp() contra el
            ancho de la ventana, así que entre un móvil de 320 px y un monitor
            de 1920 no hay ningún punto en el que el diseño «se rompa y vuelva a
            montarse». Los @media solo cambian la ESTRUCTURA (una columna o
            dos), no las medidas.

            Y hay dos ejes, no uno. Un móvil en horizontal tiene ancho de
            tablet y alto de nada: sin mirar también la altura, la banda de
            marca se come la pantalla y el formulario queda fuera. Por eso hay
            consultas de `max-height` que encogen el logo y los espacios.
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

            /* Ancho del logo. Es la pieza que más manda en el equilibrio de la
               pantalla, así que se declara una vez y se ajusta por tramos. */
            --auth-logo: clamp(11rem, 62vw, 17rem);

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
            display: grid;
            grid-template-columns: 1fr;
            min-height: 100vh;
            min-height: 100dvh;
        }

        /* ---------- Banda de marca ---------- */

        .auth-showcase {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: clamp(1.25rem, 4vh, 2.5rem);
            padding: clamp(1.75rem, 5vw, 3.5rem) clamp(1.5rem, 5vw, 3.5rem);
            color: #fff;
            /* El mismo azul del que se diseñó el logotipo: el dorado cae sobre
               su fondo natural. */
            background:
                radial-gradient(120% 80% at 50% 0%, #1b3757 0%, transparent 60%),
                linear-gradient(160deg, #0a182b, #10233c 55%, #142c4a);
        }

        /* Anillo dorado: la misma geometría del arco del logotipo. */
        .auth-showcase::before {
            content: "";
            position: absolute;
            width: 38rem;
            height: 38rem;
            top: -19rem;
            right: -13rem;
            border: 1px solid rgba(197, 161, 98, .16);
            border-radius: 50%;
            box-shadow: 0 0 0 4.5rem rgba(197, 161, 98, .045),
                        0 0 0 9rem rgba(197, 161, 98, .025);
            pointer-events: none;
        }

        /* Halo: el reflejo del oro del logotipo. */
        .auth-showcase::after {
            content: "";
            position: absolute;
            width: 26rem;
            height: 26rem;
            bottom: -12rem;
            left: -9rem;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(197, 161, 98, .22), transparent 68%);
            pointer-events: none;
        }

        .auth-logo,
        .auth-showcase-content,
        .auth-showcase-footer { position: relative; z-index: 1; }

        /*
            El logo va SIN marco. El archivo tiene el fondo recortado, así que
            el dorado cae directo sobre la banda. Enmarcarlo lo convertiría en
            una estampita pegada encima; suelto, la banda entera ES la marca.

            La sombra proyectada le da el mismo relieve que tiene el oro del
            propio logotipo, para que no se vea plano sobre el degradado.
        */
        .auth-logo {
            display: block;
            width: var(--auth-logo);
            margin: 0 auto;
            filter: drop-shadow(0 .75rem 1.5rem rgba(0, 0, 0, .45));
        }

        /* `height: auto` con las medidas en el <img>: el navegador reserva el
           hueco exacto antes de descargarlo y la pantalla no da un tirón. */
        .auth-logo img { display: block; width: 100%; height: auto; }

        .auth-showcase-content { max-width: 32rem; }

        .auth-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .42rem .75rem;
            border: 1px solid rgba(197, 161, 98, .32);
            border-radius: 99px;
            color: var(--auth-crema);
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .09em;
            text-transform: uppercase;
        }

        .auth-eyebrow i { color: var(--auth-oro); font-size: .85rem; }

        .auth-showcase h1 {
            margin: 1.15rem 0 .9rem;
            max-width: 26rem;
            color: #fff;
            font-size: clamp(1.75rem, 2.6vw, 2.9rem);
            line-height: 1.1;
            letter-spacing: -.045em;
            font-weight: 700;
            text-wrap: balance;
        }

        .auth-showcase h1 span { color: var(--auth-oro-claro); }

        .auth-showcase p {
            max-width: 27rem;
            margin: 0;
            color: #b6c6d8;
            font-size: clamp(.9rem, 1vw, 1rem);
            line-height: 1.65;
        }

        .auth-points {
            display: grid;
            gap: .75rem;
            margin: 1.75rem 0 0;
            padding: 0;
            list-style: none;
            color: #e6eef7;
            font-size: clamp(.83rem, .9vw, .9rem);
        }

        .auth-points li { display: flex; align-items: center; gap: .7rem; }

        .auth-points i {
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            background: rgba(197, 161, 98, .18);
            color: var(--auth-oro-claro);
            font-size: .9rem;
        }

        .auth-showcase-footer { color: #8ba1b8; font-size: .78rem; }

        /* ---------- Panel del formulario ---------- */

        .auth-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(1.75rem, 5vw, 3.5rem) clamp(1.5rem, 5vw, 3.5rem);
            padding-bottom: max(clamp(1.75rem, 5vw, 3.5rem), env(safe-area-inset-bottom));
            background: #fff;
        }

        .auth-card { width: min(100%, 26rem); }

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
            min-height: 3.1rem;
            border: 1px solid var(--auth-linea);
            border-radius: .7rem;
            /* 16 px exactos: por debajo, iOS hace zoom al enfocar el campo y
               descuadra la pantalla entera. */
            font-size: 1rem;
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
            min-height: 3.2rem;
            border: 0;
            border-radius: .7rem;
            background: linear-gradient(135deg, var(--auth-noche), var(--auth-azul));
            box-shadow: 0 .65rem 1.25rem rgba(10, 24, 43, .22);
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
           Estructura: una columna hasta que de verdad caben dos
           --------------------------------------------------------------- */

        /* En una columna, el texto de escaparate sobra: quita sitio al
           formulario, que es a lo que se viene. Queda solo el logo. */
        .auth-showcase-content,
        .auth-showcase-footer { display: none; }

        /*
            Pantallas bajas: la banda se comprime para que el formulario siga
            alcanzable.

            Va atada a `orientation: landscape` a propósito. Un móvil pequeño en
            VERTICAL también mide menos de 700 px de alto, y sin esa condición
            se llevaba el logo encogido sin ninguna razón: ahí sobra ancho y el
            problema es al revés. Quien tiene poca altura de verdad es el
            teléfono tumbado y el portátil de pantalla corta.
        */
        @media (max-height: 700px) and (orientation: landscape) {
            .auth-body { --auth-logo: clamp(8rem, 34vw, 12rem); }
            .auth-showcase { gap: 1rem; padding-top: 1.25rem; padding-bottom: 1.25rem; }
        }

        @media (max-height: 520px) and (orientation: landscape) {
            .auth-body { --auth-logo: clamp(6.5rem, 26vw, 9rem); }
            .auth-showcase { padding-top: .9rem; padding-bottom: .9rem; }
            .auth-panel { padding-top: 1.25rem; padding-bottom: 1.25rem; }
        }

        /*
            Dos columnas a partir de 62rem (992 px) y NO de 768: en una tablet
            en vertical, dos columnas dejan el formulario en 300 px, más
            estrecho que en un móvil. El punto de corte se fija en rem para que
            siga la letra del usuario: quien la agranda necesita el cambio
            antes, no en el mismo píxel.
        */
        @media (min-width: 62rem) {
            .auth-shell {
                grid-template-columns: minmax(22rem, 1.05fr) minmax(24rem, 1fr);
                /* Alto fijo con cada panel desplazándose por su cuenta: así un
                   formulario con errores nunca empuja la banda ni deja la
                   página con dos barras de desplazamiento. */
                height: 100vh;
                height: 100dvh;
            }

            /* Solo el formulario se desplaza. La banda se queda en
               `overflow: hidden` porque sus adornos son `position: absolute` y
               asoman 12rem por debajo del borde: con `auto` dejarían de
               recortarse y le saldría una barra de desplazamiento a un panel
               que no tiene nada que desplazar. */
            .auth-panel {
                min-height: 0;
                overflow-y: auto;
            }

            .auth-showcase { min-height: 0; }

            .auth-body { --auth-logo: clamp(12rem, 20vw, 19rem); }

            .auth-showcase {
                justify-content: center;
                align-items: center;
                text-align: center;
                gap: clamp(1.5rem, 3vh, 3rem);
            }

            .auth-logo { margin: 0 auto; }

            .auth-showcase-content,
            .auth-showcase-footer { display: block; }
            .auth-showcase-content { max-width: 28rem; }
            .auth-showcase-content .auth-points li { justify-content: center; }
            .auth-showcase-footer { margin-top: auto; }
        }

        /* Con poca altura no hay sitio para la lista de ventajas aunque el
           ancho dé para dos columnas. */
        @media (min-width: 62rem) and (max-height: 640px) {
            .auth-points { display: none; }
            .auth-showcase p { display: none; }
        }

        @media (min-width: 100rem) {
            .auth-shell { grid-template-columns: minmax(28rem, 1fr) minmax(30rem, 1fr); }
        }

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
        @media (min-width: 62rem) and (prefers-reduced-motion: no-preference) {
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
                     width="478" height="357"
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
