@extends('backend.layouts.auth')

@section('title', 'Confirmar contraseña')

@section('content')
    <div>
        <h5 class="text-primary">Confirma tu identidad</h5>
        <p class="text-muted">Esta es un área protegida. Vuelve a ingresar tu contraseña para continuar.</p>
    </div>

    <div class="p-2 mt-4">
        <form method="POST" action="{{ route('password.confirm') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="password-input">Contraseña</label>
                <div class="position-relative auth-pass-inputgroup mb-3">
                    <input type="password" name="password" id="password-input" required autofocus
                        autocomplete="current-password"
                        class="form-control pe-5 password-input @error('password') is-invalid @enderror"
                        placeholder="Ingresa tu contraseña">
                    <button
                        class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none"
                        type="button"><i class="ri-eye-fill align-middle"></i></button>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mt-3">
                <button class="btn btn-success w-100" type="submit">Confirmar</button>
            </div>
        </form>
    </div>
@endsection
