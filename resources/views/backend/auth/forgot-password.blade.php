@extends('backend.layouts.auth')

@section('title', 'Recuperar contraseña')

@section('content')
    <div>
        <h5 class="text-primary">¿Olvidaste tu contraseña?</h5>
        <p class="text-muted">Te enviaremos un enlace para restablecerla.</p>
    </div>

    <div class="alert alert-borderless alert-warning text-center mb-2 mt-3" role="alert">
        Revisa también la carpeta de correo no deseado.
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-borderless" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <div class="p-2">
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="mb-4">
                <label class="form-label" for="email">Correo electrónico</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                    class="form-control @error('email') is-invalid @enderror" placeholder="usuario@tienda.com">
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="text-center mt-4">
                <button class="btn btn-success w-100" type="submit">Enviar enlace de recuperación</button>
            </div>
        </form>
    </div>

    <div class="mt-5 text-center">
        <p class="mb-0">¿Ya la recordaste?
            <a href="{{ route('login') }}" class="fw-semibold text-primary text-decoration-underline">Iniciar sesión</a>
        </p>
    </div>
@endsection
