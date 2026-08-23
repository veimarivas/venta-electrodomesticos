@extends('backend.layouts.auth')

@section('title', 'Verificación en dos pasos')

@section('content')
    <div>
        <h5 class="text-primary">Verificación en dos pasos</h5>
        <p class="text-muted">Ingresa el código de 6 dígitos de tu aplicación de autenticación.</p>
    </div>

    @error('code')
        <div class="alert alert-danger alert-borderless mt-3 mb-0" role="alert">{{ $message }}</div>
    @enderror
    @error('recovery_code')
        <div class="alert alert-danger alert-borderless mt-3 mb-0" role="alert">{{ $message }}</div>
    @enderror

    <div class="p-2 mt-4">
        {{-- Formulario con código del autenticador --}}
        <form method="POST" action="{{ route('two-factor.login') }}" id="form-otp">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="code">Código de autenticación</label>
                <input type="text" name="code" id="code" inputmode="numeric" autocomplete="one-time-code" autofocus
                    class="form-control form-control-lg text-center" placeholder="000000" maxlength="6">
            </div>
            <div class="mt-3">
                <button class="btn btn-success w-100" type="submit">Verificar</button>
            </div>
        </form>

        {{-- Alternativa: código de recuperación --}}
        <form method="POST" action="{{ route('two-factor.login') }}" id="form-recovery" class="d-none mt-3">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="recovery_code">Código de recuperación</label>
                <input type="text" name="recovery_code" id="recovery_code" class="form-control"
                    placeholder="xxxxxxxx-xxxxxxxx">
            </div>
            <div class="mt-3">
                <button class="btn btn-success w-100" type="submit">Verificar</button>
            </div>
        </form>

        <div class="mt-4 text-center">
            <button type="button" class="btn btn-link text-muted p-0" id="toggle-recovery">
                Usar un código de recuperación
            </button>
        </div>
    </div>
@endsection

@push('js')
    <script>
        document.getElementById('toggle-recovery').addEventListener('click', function () {
            const otp = document.getElementById('form-otp');
            const recovery = document.getElementById('form-recovery');
            const usingOtp = !otp.classList.contains('d-none');

            otp.classList.toggle('d-none', usingOtp);
            recovery.classList.toggle('d-none', !usingOtp);
            this.textContent = usingOtp ? 'Usar el código del autenticador' : 'Usar un código de recuperación';
            (usingOtp ? document.getElementById('recovery_code') : document.getElementById('code')).focus();
        });
    </script>
@endpush
