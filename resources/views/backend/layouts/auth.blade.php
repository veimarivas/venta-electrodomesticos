<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('backend.layouts.partials.head')
    @include('backend.layouts.partials.head-css')
    <style>
        :root { --auth-ink: #14243d; --auth-muted: #6b778a; --auth-line: #dfe6ee; --auth-accent: #f59e0b; --auth-teal: #0f766e; }
        body.auth-body { min-height: 100vh; min-height: 100dvh; margin: 0; background: #f5f7fa; color: var(--auth-ink); font-family: Inter, "Segoe UI", sans-serif; }
        body.auth-body ::selection { background: rgba(15, 118, 110, .22); }
        .auth-shell { min-height: 100vh; min-height: 100dvh; display: grid; grid-template-columns: 1fr; grid-template-rows: auto 1fr; }
        .auth-showcase { position: relative; overflow: hidden; padding: max(1.1rem, env(safe-area-inset-top)) 1.5rem 1.25rem; color: #fff; background: linear-gradient(160deg, #0f2540, #112a46 55%, #12314f); display: flex; align-items: center; }
        .auth-showcase::before { content: ""; position: absolute; width: 34rem; height: 34rem; border: 1px solid rgba(255,255,255,.13); border-radius: 50%; top: -16rem; right: -12rem; box-shadow: 0 0 0 4rem rgba(255,255,255,.035), 0 0 0 8rem rgba(255,255,255,.025); }
        .auth-showcase::after { content: ""; position: absolute; width: 22rem; height: 22rem; border-radius: 50%; background: radial-gradient(circle, rgba(245,158,11,.2), transparent 66%); bottom: -9rem; left: -8rem; }
        .auth-brand, .auth-showcase-content, .auth-showcase-footer { position: relative; z-index: 1; }
        .auth-brand { display: inline-flex; align-items: center; gap: .75rem; color: #fff; text-decoration: none; font-size: 1.05rem; font-weight: 700; letter-spacing: -.02em; }
        .auth-brand-mark { display: grid; place-items: center; width: 2.5rem; height: 2.5rem; border-radius: .7rem; color: #112a46; background: var(--auth-accent); font-size: 1.25rem; font-weight: 800; box-shadow: 0 8px 20px rgba(0,0,0,.2); }
        .auth-showcase-content { max-width: 30rem; }
        .auth-eyebrow { display: inline-flex; align-items: center; gap: .45rem; padding: .42rem .7rem; border: 1px solid rgba(255,255,255,.2); border-radius: 99px; color: #c9d9ea; font-size: .72rem; font-weight: 700; letter-spacing: .09em; text-transform: uppercase; }
        .auth-eyebrow i { color: var(--auth-accent); font-size: .85rem; }
        .auth-showcase h1 { margin: 1.3rem 0 1rem; max-width: 27rem; color: #fff; font-size: clamp(2rem, 3.5vw, 3.4rem); line-height: 1.08; letter-spacing: -.055em; font-weight: 700; text-wrap: balance; }
        .auth-showcase h1 span { color: #fbbf24; }
        .auth-showcase p { max-width: 26rem; margin: 0; color: #b8c8d9; font-size: 1rem; line-height: 1.7; }
        .auth-points { display: grid; gap: .75rem; margin: 2.25rem 0 0; padding: 0; list-style: none; color: #e8f0f8; font-size: .9rem; }
        .auth-points li { display: flex; align-items: center; gap: .7rem; }
        .auth-points i { display: grid; place-items: center; width: 1.45rem; height: 1.45rem; border-radius: 50%; background: rgba(245,158,11,.18); color: #fbbf24; font-size: .9rem; }
        .auth-showcase-footer { color: #91a7bd; font-size: .78rem; }
        .auth-panel { display: flex; align-items: center; justify-content: center; padding: 2rem 1.5rem max(2.25rem, env(safe-area-inset-bottom)); background: #fff; }
        .auth-card { width: min(100%, 29rem); }
        .auth-card > div:first-child h5 { margin: 0 0 .55rem; color: var(--auth-ink) !important; font-size: 1.7rem; letter-spacing: -.04em; font-weight: 700; text-wrap: balance; }
        .auth-card > div:first-child p { margin: 0; color: var(--auth-muted) !important; line-height: 1.6; }
        .auth-card .mt-4 { margin-top: 2.25rem !important; }
        .auth-card .form-label { margin-bottom: .55rem; color: #344155; font-size: .84rem; font-weight: 650; }
        .auth-card .form-control { min-height: 3.2rem; border: 1px solid var(--auth-line); border-radius: .65rem; color: var(--auth-ink); background: #fff; box-shadow: none; transition: border-color .2s, box-shadow .2s; caret-color: var(--auth-teal); }
        .auth-card .form-control::placeholder { color: #a7b1bf; }
        .auth-card .form-control:focus { border-color: var(--auth-teal); box-shadow: 0 0 0 .22rem rgba(15,118,110,.1); }
        .auth-card .password-addon { height: 3.2rem; padding: 0 1rem; color: #718096 !important; }
        .auth-card .form-check-input { border-color: #b8c4d2; }
        .auth-card .form-check-input:checked { background-color: var(--auth-teal); border-color: var(--auth-teal); }
        .auth-card .form-check-label, .auth-card .text-muted { color: var(--auth-muted) !important; font-size: .88rem; }
        .auth-card a { color: var(--auth-teal) !important; font-weight: 600; text-decoration: none; }
        .auth-card a:hover { color: #095c56 !important; text-decoration: underline; }
        .auth-card .btn-success { min-height: 3.25rem; border: 0; border-radius: .65rem; background: var(--auth-ink); box-shadow: 0 10px 18px rgba(20,36,61,.16); font-weight: 650; transition: transform .2s, background .2s, box-shadow .2s; }
        .auth-card .btn-success:hover, .auth-card .btn-success:focus { background: #0c1a2d; box-shadow: 0 13px 22px rgba(20,36,61,.22); transform: translateY(-1px); }
        .auth-card .btn-success:active { transform: translateY(0); }
        .auth-card .alert { border-radius: .65rem; font-size: .88rem; }

        .auth-showcase-content, .auth-showcase-footer { display: none; }

        @media (pointer: coarse) {
            .auth-card .form-control, .auth-card .password-addon, .auth-card .btn-success { min-height: 3.5rem; }
            .auth-card .form-check-input { width: 1.35em; height: 1.35em; }
        }

        @media (max-height: 560px) and (orientation: landscape) {
            .auth-showcase { padding: max(.9rem, env(safe-area-inset-top)) 1.5rem .9rem; }
            .auth-panel { padding: 1.5rem max(1.5rem, env(safe-area-inset-right)) max(1.5rem, env(safe-area-inset-bottom)) max(1.5rem, env(safe-area-inset-left)); }
        }

        @media (min-width: 576px) {
            .auth-showcase { padding: max(1.5rem, env(safe-area-inset-top)) clamp(2rem, 7vw, 5rem) 1.5rem; }
            .auth-panel { padding: clamp(2rem, 6vw, 4rem) clamp(1.5rem, 6vw, 4rem) max(2.5rem, env(safe-area-inset-bottom)); }
        }

        @media (min-width: 768px) {
            .auth-shell { grid-template-columns: minmax(320px, 42%) 1fr; grid-template-rows: 1fr; }
            .auth-showcase { padding: clamp(2rem, 4vw, 4rem); align-items: stretch; flex-direction: column; }
            .auth-showcase-content { display: block; margin: auto 0; padding: 4rem 0 2.5rem; }
            .auth-showcase-content h1 { font-size: clamp(1.9rem, 3.4vw, 3rem); }
            .auth-showcase-footer { display: block; }
            .auth-panel { min-height: 100vh; min-height: 100dvh; }
        }

        @media (min-width: 992px) {
            .auth-shell { grid-template-columns: minmax(340px, 46%) 1fr; }
            .auth-showcase-content h1 { font-size: clamp(2rem, 3.5vw, 3.4rem); }
            .auth-points { display: grid; }
        }

        @media (min-width: 1200px) {
            .auth-shell { grid-template-columns: minmax(380px, 640px) 1fr; }
            .auth-showcase p { display: block; }
        }

        @media (prefers-reduced-motion: reduce) {
            .auth-card .btn-success { transition: none; }
        }

        @keyframes auth-rise { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
        @media (min-width: 992px) and (prefers-reduced-motion: no-preference) {
            .auth-card { animation: auth-rise .5s ease-out both; }
        }
    </style>
</head>
<body class="auth-body">
    <main class="auth-shell">
        <aside class="auth-showcase" aria-label="Información de {{ config('app.name') }}">
            <a href="{{ url('/') }}" class="auth-brand"><span class="auth-brand-mark">E</span><span>{{ config('app.name') }}</span></a>
            <div class="auth-showcase-content">
                <span class="auth-eyebrow"><i class="ri-flashlight-fill"></i> Operación inteligente</span>
                <h1>Todo tu negocio, <span>siempre bajo control.</span></h1>
                <p>Una forma clara y segura de gestionar inventario, compras y ventas desde un solo lugar.</p>
                <ul class="auth-points">
                    <li><i class="ri-checkbox-circle-fill"></i> Inventario actualizado en tiempo real</li>
                    <li><i class="ri-checkbox-circle-fill"></i> Acceso seguro para tu equipo</li>
                    <li><i class="ri-checkbox-circle-fill"></i> Decisiones con información confiable</li>
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