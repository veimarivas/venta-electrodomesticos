@extends('backend.layouts.auth')

@section('title', 'Nueva contraseña')

@section('content')
    <div>
        <h5 class="text-primary">Crear nueva contraseña</h5>
        <p class="text-muted">Debe tener al menos 8 caracteres y no haber sido filtrada en brechas conocidas.</p>
    </div>

    <div class="p-2">
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <input type="hidden" name="email" value="{{ $request->email }}">

            <div class="mb-3">
                <label class="form-label" for="password-input">Nueva contraseña</label>
                <div class="position-relative auth-pass-inputgroup">
                    <input type="password" name="password" id="password-input" required autocomplete="new-password"
                        class="form-control pe-5 password-input @error('password') is-invalid @enderror"
                        placeholder="Ingresa la contraseña">
                    <button
                        class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none"
                        type="button" id="password-addon"><i class="ri-eye-fill align-middle"></i></button>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="confirm-password-input">Confirmar contraseña</label>
                <div class="position-relative auth-pass-inputgroup mb-3">
                    <input type="password" name="password_confirmation" id="confirm-password-input" required
                        autocomplete="new-password" class="form-control pe-5 password-input"
                        placeholder="Repite la contraseña">
                    <button
                        class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted password-addon material-shadow-none"
                        type="button"><i class="ri-eye-fill align-middle"></i></button>
                </div>
            </div>

            <div class="mt-4">
                <button class="btn btn-success w-100" type="submit">Guardar contraseña</button>
            </div>
        </form>
    </div>

    <div class="mt-5 text-center">
        <p class="mb-0">¿Recordaste tu contraseña?
            <a href="{{ route('login') }}" class="fw-semibold text-primary text-decoration-underline">Iniciar sesión</a>
        </p>
    </div>
@endsection
