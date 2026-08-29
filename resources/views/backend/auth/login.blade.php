@extends('backend.layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
    <style>
        /* ── Login refinements ─────────────────────────────────────── */
        .login-header {
            text-align: center;
            margin-bottom: clamp(1.75rem, 4vw, 2.5rem);
        }

        .login-header .login-logo-link {
            display: inline-block;
            margin-bottom: 1.15rem;
            transition: opacity .2s;
        }

        .login-header .login-logo-link:hover {
            opacity: .85;
        }

        .login-header .login-logo-img {
            display: block;
            max-height: 5rem;
            width: auto;
            margin: 0 auto;
            object-fit: contain;
            filter: drop-shadow(0 .4rem .8rem rgba(0, 0, 0, .15));
        }

        .login-header h5 {
            margin: 0 0 .4rem;
            color: var(--auth-noche) !important;
            font-size: clamp(1.4rem, 2vw, 1.7rem);
            letter-spacing: -.035em;
            font-weight: 700;
            text-wrap: balance;
        }

        .login-header p {
            margin: 0;
            color: var(--auth-apagado) !important;
            font-size: clamp(.85rem, 1vw, .93rem);
            line-height: 1.55;
        }

        /* ── Formulario ───────────────────────────────────────────── */
        .login-form .form-group {
            margin-bottom: 1.35rem;
        }

        .login-form .form-label {
            display: flex;
            align-items: center;
            gap: .35rem;
            margin-bottom: .45rem;
            color: #2c3a4d;
            font-size: .82rem;
            font-weight: 650;
            letter-spacing: .01em;
        }

        .login-form .form-label .label-hint {
            margin-left: auto;
            font-weight: 500;
            color: var(--auth-azul);
            font-size: .76rem;
            opacity: .85;
        }

        .login-form .input-group {
            position: relative;
        }

        .login-form .input-icon {
            position: absolute;
            top: 50%;
            left: .85rem;
            transform: translateY(-50%);
            color: #93a1b2;
            font-size: 1.05rem;
            pointer-events: none;
            z-index: 2;
            transition: color .2s ease;
        }

        .login-form .input-group:focus-within .input-icon {
            color: var(--auth-azul);
        }

        .login-form .input-group .form-control {
            padding-left: 2.6rem;
        }

        .login-form .toggle-pass {
            position: absolute;
            top: 50%;
            right: .35rem;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border: 0;
            border-radius: .55rem;
            background: transparent;
            color: #7d8b9c;
            font-size: 1.1rem;
            cursor: pointer;
            transition: background .18s, color .18s;
            z-index: 2;
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
            gap: .25rem;
            color: var(--auth-azul);
            font-size: .79rem;
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
            gap: .6rem;
            padding: .65rem .85rem;
            margin-top: .15rem;
            border-radius: .65rem;
            background: #f6f8fb;
            cursor: pointer;
            transition: background .18s;
        }

        .login-remember:hover {
            background: #eef1f6;
        }

        .login-remember .form-check-input {
            width: 1.15em;
            height: 1.15em;
            margin-top: .05em;
            flex-shrink: 0;
        }

        .login-remember .form-check-label {
            font-size: .84rem;
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
            gap: .55rem;
            width: 100%;
            min-height: 3.25rem;
            margin-top: .35rem;
            border: 0;
            border-radius: .75rem;
            background: linear-gradient(135deg, var(--auth-noche), var(--auth-azul));
            color: #fff;
            font-size: .95rem;
            font-weight: 650;
            letter-spacing: .015em;
            cursor: pointer;
            box-shadow:
                0 .6rem 1.15rem rgba(10, 24, 43, .2),
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
                0 .85rem 1.5rem rgba(10, 24, 43, .28),
                inset 0 1px 0 rgba(255, 255, 255, .08);
            transform: translateY(-1px);
        }

        .login-btn:active {
            transform: translateY(0);
            box-shadow: 0 .35rem .7rem rgba(10, 24, 43, .18);
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
            gap: .4rem;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--auth-linea);
            color: #93a1b2;
            font-size: .76rem;
            text-align: center;
        }

        .login-secure i {
            color: var(--auth-oro);
            font-size: .85rem;
            flex-shrink: 0;
        }

        /* ── Alertas ──────────────────────────────────────────────── */
        .login-alert {
            display: flex;
            align-items: flex-start;
            gap: .6rem;
            padding: .75rem .9rem;
            border-radius: .65rem;
            font-size: .84rem;
            line-height: 1.45;
            margin-bottom: .25rem;
        }

        .login-alert i {
            margin-top: .1rem;
            flex-shrink: 0;
            font-size: .95rem;
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
            .login-secure { animation: none; }
        }
    </style>

    {{-- ─── Encabezado ────────────────────────────────────────────── --}}
    <div class="login-header">
        <a href="{{ url('/') }}" class="login-logo-link">
            <img src="{{ asset('assets/images/marca-login.png') }}"
                 alt="{{ config('app.name') }}"
                 class="login-logo-img"
                 width="478" height="357">
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
            <div class="d-flex align-items-center justify-content-between" style="margin-bottom:.45rem">
                <label class="form-label mb-0" for="password-input">Contraseña</label>
                <a href="{{ route('password.request') }}" class="login-forgot">
                    <i class="ri-key-2-line" style="font-size:.8rem"></i> ¿Olvidaste tu contraseña?
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
