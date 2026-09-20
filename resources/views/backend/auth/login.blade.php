@extends('backend.layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
    <style>
        /* ── Login refinements ─────────────────────────────────────── */
        .login-header {
            text-align: center;
            margin-bottom: clamp(1rem, 2vw, 1.5rem);
        }

        .login-header .login-logo-link {
            display: inline-block;
            margin-bottom: clamp(.6rem, 1.5vw, 1rem);
            transition: opacity .2s;
        }

        .login-header .login-logo-link:hover {
            opacity: .85;
        }

        .login-header .login-logo-img {
            display: block;
            max-height: clamp(3rem, 8vw, 4.5rem);
            width: auto;
            margin: 0 auto;
            object-fit: contain;
            filter: drop-shadow(0 .3rem .6rem rgba(0, 0, 0, .12));
        }

        .login-header h5 {
            margin: 0 0 .25rem;
            color: var(--auth-noche) !important;
            font-size: clamp(1.2rem, 2vw, 1.5rem);
            letter-spacing: -.035em;
            font-weight: 700;
            text-wrap: balance;
        }

        .login-header p {
            margin: 0;
            color: var(--auth-apagado) !important;
            font-size: clamp(.78rem, 1vw, .85rem);
            line-height: 1.4;
        }

        /* ── Formulario ───────────────────────────────────────────── */
        .login-form .form-group {
            margin-bottom: clamp(.8rem, 1.5vw, 1rem);
        }

        .login-form .form-label {
            display: flex;
            align-items: center;
            gap: .35rem;
            margin-bottom: .35rem;
            color: #2c3a4d;
            font-size: .78rem;
            font-weight: 650;
            letter-spacing: .01em;
        }

        .login-form .form-label .label-hint {
            margin-left: auto;
            font-weight: 500;
            color: var(--auth-azul);
            font-size: .72rem;
            opacity: .85;
        }

        .login-form .input-group {
            position: relative;
        }

        .login-form .input-icon {
            position: absolute;
            top: 50%;
            left: .75rem;
            transform: translateY(-50%);
            color: #93a1b2;
            font-size: .95rem;
            pointer-events: none;
            z-index: 2;
            transition: color .2s ease;
        }

        .login-form .input-group:focus-within .input-icon {
            color: var(--auth-azul);
        }

        .login-form .input-group .form-control {
            padding-left: 2.3rem;
        }

        .login-form .toggle-pass {
            position: absolute;
            top: 50%;
            right: .25rem;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            border: 0;
            border-radius: .45rem;
            background: transparent;
            color: #7d8b9c;
            font-size: 1rem;
            cursor: pointer;
            transition: background .18s, color .18s;
            z-index: 5;
        }

        .login-form .toggle-pass:hover {
            background: rgba(37, 73, 112, .06);
            color: var(--auth-azul);
        }

        .login-form .toggle-pass:focus-visible {
            outline: 2px solid var(--auth-azul);
            outline-offset: 1px;
        }

        /* ── Forgot link ──────────────────────────────────────────── */
        .login-forgot {
            display: inline-flex;
            align-items: center;
            gap: .2rem;
            color: var(--auth-azul);
            font-size: .72rem;
            font-weight: 600;
            text-decoration: none;
            transition: color .18s;
        }

        .login-forgot:hover {
            color: var(--auth-azul-hondo);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        /* ── Remember checkbox ────────────────────────────────────── */
        .login-remember {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem .7rem;
            margin-top: .1rem;
            border-radius: .5rem;
            background: #f6f8fb;
            cursor: pointer;
            transition: background .18s;
        }

        .login-remember:hover {
            background: #eef1f6;
        }

        .login-remember:has(.form-check-input:checked) {
            background: rgba(37, 73, 112, .06);
        }

        .login-remember .form-check-input {
            width: 1.1em;
            height: 1.1em;
            margin-top: .05em;
            flex-shrink: 0;
        }

        .login-remember .form-check-label {
            font-size: .78rem;
            color: #3d4e63;
            font-weight: 500;
            cursor: pointer;
        }

        /* ── Botón principal ──────────────────────────────────────── */
        .login-btn {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .45rem;
            width: 100%;
            min-height: 2.8rem;
            margin-top: .25rem;
            border: 0;
            border-radius: .6rem;
            background: linear-gradient(135deg, var(--auth-noche), var(--auth-azul));
            color: #fff;
            font-size: .88rem;
            font-weight: 650;
            letter-spacing: .015em;
            cursor: pointer;
            box-shadow:
                0 .5rem 1rem rgba(10, 24, 43, .2),
                inset 0 1px 0 rgba(255, 255, 255, .08);
            transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
            overflow: hidden;
        }

        .login-btn::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 1px;
            background: linear-gradient(90deg, transparent 8%, var(--auth-oro), transparent 92%);
            opacity: .75;
        }

        .login-btn:hover {
            filter: brightness(1.12);
            box-shadow:
                0 .7rem 1.3rem rgba(10, 24, 43, .28),
                inset 0 1px 0 rgba(255, 255, 255, .08);
            transform: translateY(-1px);
        }

        .login-btn:active {
            transform: translateY(0);
            box-shadow: 0 .3rem .6rem rgba(10, 24, 43, .18);
        }

        .login-btn:focus-visible {
            outline: 2px solid var(--auth-oro);
            outline-offset: 2px;
        }

        .login-btn .btn-arrow {
            display: inline-flex;
            transition: transform .25s ease;
        }

        .login-btn:hover .btn-arrow {
            transform: translateX(3px);
        }

        /* ── Nota de seguridad ────────────────────────────────────── */
        .login-secure {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .35rem;
            margin-top: auto;
            padding-top: .75rem;
            border-top: 1px solid var(--auth-linea);
            color: #93a1b2;
            font-size: .7rem;
            text-align: center;
        }

        .login-secure i {
            color: var(--auth-oro);
            font-size: .75rem;
            flex-shrink: 0;
        }

        /* ── Alertas ──────────────────────────────────────────────── */
        .login-alert {
            display: flex;
            align-items: flex-start;
            gap: .5rem;
            padding: .6rem .75rem;
            border-radius: .5rem;
            font-size: .78rem;
            line-height: 1.4;
            margin-bottom: .2rem;
        }

        .login-alert i {
            margin-top: .05rem;
            flex-shrink: 0;
            font-size: .85rem;
        }

        .login-alert-success {
            background: #eaf7ef;
            color: #1a6b3c;
            border: 1px solid #b8e4c9;
        }

        .login-alert-success i { color: #22a55a; }

        .login-alert-error {
            background: #fdf1f1;
            color: #991b1b;
            border: 1px solid #f5c6c6;
        }

        .login-alert-error i { color: #dc3545; }

        /* ── Micro-interacciones ──────────────────────────────────── */
        /* El campo enfocado enciende su etiqueta: confirma qué se está
           editando sin añadir texto. `:has()` donde el navegador lo entiende;
           donde no, la etiqueta se queda igual. */
        .login-form .form-group:has(.form-control:focus) .form-label {
            color: var(--auth-azul);
        }

        /* La alerta y el error entran deslizándose: aparecen después de enviar
           y conviene que el ojo los note. */
        @keyframes login-alert-in {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: none; }
        }

        .login-alert,
        .login-form .invalid-feedback {
            animation: login-alert-in .3s ease-out both;
        }

        /* El botón pasa un brillo corto al pasar por encima: una sola pasada,
           alineado con el de la tienda. */
        @keyframes login-shine {
            from { left: -140%; }
            to   { left: 140%; }
        }

        .login-btn::after {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            left: -140%;
            width: 55%;
            background: linear-gradient(115deg, transparent, rgba(255, 255, 255, .3), transparent);
            transform: skewX(-18deg);
            pointer-events: none;
        }

        .login-btn:hover::after {
            animation: login-shine .7s ease-out;
        }

        /* ── Stagger entrance ─────────────────────────────────────── */
        @keyframes login-fade-in {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: none; }
        }

        @media (prefers-reduced-motion: no-preference) {
            .login-header    { animation: login-fade-in .4s ease-out both; }
            .login-form .form-group:nth-child(1) { animation: login-fade-in .4s ease-out .08s both; }
            .login-form .form-group:nth-child(2) { animation: login-fade-in .4s ease-out .14s both; }
            .login-remember  { animation: login-fade-in .4s ease-out .20s both; }
            .login-btn       { animation: login-fade-in .4s ease-out .26s both; }
            .login-secure    { animation: login-fade-in .4s ease-out .32s both; }
        }

        @media (prefers-reduced-motion: reduce) {
            .login-header,
            .login-form .form-group,
            .login-remember,
            .login-btn,
            .login-secure,
            .login-alert,
            .login-form .invalid-feedback,
            .login-btn::after { animation: none; }
        }
    </style>

    {{-- ─── Encabezado ────────────────────────────────────────────── --}}
    <div class="login-header">
        <a href="{{ url('/') }}" class="login-logo-link">
            <img src="{{ asset('assets/images/marca-login.png') }}"
                 alt="{{ config('app.name') }}"
            class="login-logo-img"
            width="478" height="391">
        </a>
        <h5>Bienvenido de nuevo</h5>
        <p>Ingresa tus credenciales para acceder al panel de gestión.</p>
    </div>

    {{-- ─── Alertas ───────────────────────────────────────────────── --}}
    @if (session('status'))
        <div class="login-alert login-alert-success" role="alert">
            <i class="ri-check-double-line"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->has('email') && !$errors->has('password'))
        <div class="login-alert login-alert-error" role="alert">
            <i class="ri-error-warning-line"></i>
            <span>{{ $errors->first('email') }}</span>
        </div>
    @endif

    {{-- ─── Formulario ────────────────────────────────────────────── --}}
    <form method="POST" action="{{ route('login') }}" class="login-form" autocomplete="on">
        @csrf

        <div class="form-group">
            <label for="email" class="form-label">
                Usuario o correo
            </label>
            <div class="input-group">
                <i class="ri-user-3-line input-icon" aria-hidden="true"></i>
                {{-- type="text", no "email": el navegador rechazaría un nombre
                     de usuario como "jperezlopez" antes de enviar el formulario. --}}
                <input type="text"
                       name="email"
                       id="email"
                       value="{{ old('email') }}"
                       required
                       autofocus
                       autocomplete="username"
                       class="form-control @error('email') is-invalid @enderror"
                       placeholder="jperezlopez o nombre@empresa.com">
            </div>
            @error('email')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <div class="d-flex align-items-center justify-content-between" style="margin-bottom:.3rem">
                <label class="form-label mb-0" for="password-input">Contraseña</label>
                <a href="{{ route('password.request') }}" class="login-forgot">
                    <i class="ri-key-2-line" style="font-size:.7rem"></i> ¿Olvidaste tu contraseña?
                </a>
            </div>
            <div class="input-group">
                <i class="ri-lock-2-line input-icon" aria-hidden="true"></i>
                <input type="password"
                       name="password"
                       id="password-input"
                       required
                       autocomplete="current-password"
                       class="form-control pe-5 password-input @error('password') is-invalid @enderror"
                       placeholder="Ingresa tu contraseña">
                <button class="toggle-pass"
                        type="button"
                        id="password-addon"
                        aria-label="Mostrar u ocultar contraseña">
                    <i class="ri-eye-off-line"></i>
                </button>
            </div>
            @error('password')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
        </div>

        <label class="login-remember" for="auth-remember-check">
            <input class="form-check-input" type="checkbox" name="remember" value="1" id="auth-remember-check" {{ old('remember') ? 'checked' : '' }}>
            <span class="form-check-label">Mantener mi sesión iniciada</span>
        </label>

        <button class="login-btn" type="submit">
            Ingresar al panel
            <span class="btn-arrow" aria-hidden="true"><i class="ri-arrow-right-line"></i></span>
        </button>
    </form>

    <p class="login-secure">
        <i class="ri-shield-check-line"></i>
        Acceso personal y protegido · Tus datos están seguros
    </p>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.login-form .toggle-pass').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.closest('.input-group').querySelector('.password-input');
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('ri-eye-off-line', 'ri-eye-line');
            } else {
                input.type = 'password';
                icon.classList.replace('ri-eye-line', 'ri-eye-off-line');
            }
        });
    });
});
</script>
@endpush
