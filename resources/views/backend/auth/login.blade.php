@extends('backend.layouts.auth')

@section('title', 'Iniciar sesión')

@section('content')
    <div>
        <h5>Bienvenido de nuevo</h5>
        <p>Ingresa tus datos para acceder al panel de gestión.</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-borderless mt-3 mb-0" role="alert">{{ session('status') }}</div>
    @endif

    @if ($errors->has('email') && !$errors->has('password'))
        <div class="alert alert-danger alert-borderless mt-3 mb-0" role="alert">{{ $errors->first('email') }}</div>
    @endif

    <div class="mt-4">
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="mb-3">
                <label for="email" class="form-label">Usuario o correo</label>
                <div class="position-relative">
                    <i class="ri-user-line position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                    {{-- type="text", no "email": el navegador rechazaría un nombre
                         de usuario como "jperezlopez" antes de enviar el formulario. --}}
                    <input type="text" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                        class="form-control ps-5 @error('email') is-invalid @enderror" placeholder="jperezlopez o nombre@empresa.com">
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between">
                    <label class="form-label" for="password-input">Contraseña</label>
                    <a href="{{ route('password.request') }}" class="small mb-2">¿La olvidaste?</a>
                </div>
                <div class="position-relative auth-pass-inputgroup">
                    <i class="ri-lock-2-line position-absolute top-50 translate-middle-y ms-3 text-muted"></i>
                    <input type="password" name="password" id="password-input" required autocomplete="current-password"
                        class="form-control ps-5 pe-5 password-input @error('password') is-invalid @enderror" placeholder="Ingresa tu contraseña">
                    <button class="btn btn-link position-absolute end-0 top-0 text-decoration-none password-addon material-shadow-none"
                        type="button" id="password-addon" aria-label="Mostrar u ocultar contraseña"><i class="ri-eye-fill align-middle"></i></button>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="form-check mt-4">
                <input class="form-check-input" type="checkbox" name="remember" value="1" id="auth-remember-check" {{ old('remember') ? 'checked' : '' }}>
                <label class="form-check-label" for="auth-remember-check">Mantener mi sesión iniciada</label>
            </div>

            <div class="mt-4">
                <button class="btn btn-success w-100" type="submit">
                    <span class="d-inline-flex align-items-center justify-content-center gap-2">Ingresar al panel <i class="ri-arrow-right-line"></i></span>
                </button>
            </div>
        </form>
    </div>
@endsection
