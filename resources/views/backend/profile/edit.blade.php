@extends('backend.layouts.master')

@section('title', 'Mi perfil')

@section('content')
    {{-- ===================== Hero / Cabecera del perfil ===================== --}}
    <div class="profile-hero mb-4">
        <div class="profile-hero-content">
            <div class="profile-hero-avatar">
                <img src="{{ $user->avatar_url }}" class="profile-avatar-img" alt="Avatar">
            </div>
            <div class="profile-hero-info">
                <h1 class="profile-hero-name">{{ $user->name }}</h1>
                <span class="profile-hero-role">
                    <i class="ri-shield-user-line"></i>
                    {{ $user->getRoleNames()->first() ?? 'Sin rol asignado' }}
                </span>
                <div class="profile-hero-meta">
                    <span class="profile-hero-meta-item">
                        <i class="ri-mail-line"></i> {{ $user->email }}
                    </span>
                    @if ($user->phone)
                        <span class="profile-hero-meta-item">
                            <i class="ri-phone-line"></i> {{ $user->phone }}
                        </span>
                    @endif
                    <span class="profile-hero-meta-item">
                        <i class="ri-time-line"></i>
                        {{ $user->last_login_at?->diffForHumans() ?? 'Primer acceso' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ===================== Sidebar ===================== --}}
        <div class="col-xxl-3">
            <div class="profile-sidebar">
                <div class="card">
                    <div class="profile-sidebar-card">
                        <div class="profile-sidebar-header">
                            <h6><i class="ri-user-settings-line me-1"></i> Información</h6>
                        </div>
                        <div class="profile-sidebar-body">
                            <div class="profile-info-row">
                                <span class="profile-info-icon profile-info-icon--role">
                                    <i class="ri-shield-user-line"></i>
                                </span>
                                <div class="profile-info-content">
                                    <span class="profile-info-label">Rol</span>
                                    <span class="profile-info-value">{{ $user->getRoleNames()->first() ?? 'Sin rol' }}</span>
                                </div>
                            </div>
                            <div class="profile-info-row">
                                <span class="profile-info-icon profile-info-icon--email">
                                    <i class="ri-mail-line"></i>
                                </span>
                                <div class="profile-info-content">
                                    <span class="profile-info-label">Correo</span>
                                    <span class="profile-info-value">{{ $user->email }}</span>
                                </div>
                            </div>
                            <div class="profile-info-row">
                                <span class="profile-info-icon profile-info-icon--phone">
                                    <i class="ri-phone-line"></i>
                                </span>
                                <div class="profile-info-content">
                                    <span class="profile-info-label">Teléfono</span>
                                    <span class="profile-info-value">{{ $user->phone ?: '—' }}</span>
                                </div>
                            </div>
                            <div class="profile-info-row">
                                <span class="profile-info-icon profile-info-icon--date">
                                    <i class="ri-calendar-check-line"></i>
                                </span>
                                <div class="profile-info-content">
                                    <span class="profile-info-label">Último acceso</span>
                                    <span class="profile-info-value">
                                        {{ $user->last_login_at?->diffForHumans() ?? 'Primer acceso' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Contenido principal ===================== --}}
        <div class="col-xxl-9">
            <div class="profile-main">
                <div class="card">
                    <div class="profile-tabs">
                        <ul class="nav nav-tabs-custom" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#datos" role="tab">
                                    <i class="ri-user-line"></i> Datos personales
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#password" role="tab">
                                    <i class="ri-lock-password-line"></i> Contraseña
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#seguridad" role="tab">
                                    <i class="ri-shield-keyhole-line"></i> Verificación en dos pasos
                                </a>
                            </li>
                        </ul>
                    </div>

                    <div class="card-body p-4">
                        <div class="tab-content">

                            {{-- Datos personales --}}
                            <div class="tab-pane active" id="datos" role="tabpanel">
                                @if (session('status') === 'profile-information-updated')
                                    <div class="alert alert-success alert-borderless d-flex align-items-center gap-2">
                                        <i class="ri-checkbox-circle-line fs-18"></i>
                                        <span>Datos actualizados correctamente.</span>
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('user-profile-information.update') }}" class="profile-form">
                                    @csrf
                                    @method('PUT')

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="name" class="form-label">Nombre completo</label>
                                                <input type="text" class="form-control @error('name', 'updateProfileInformation') is-invalid @enderror"
                                                    id="name" name="name" value="{{ old('name', $user->name) }}" required>
                                                @error('name', 'updateProfileInformation')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="phone" class="form-label">Teléfono</label>
                                                <input type="text" class="form-control" id="phone" name="phone"
                                                    value="{{ old('phone', $user->phone) }}">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Correo electrónico</label>
                                                <input type="email" class="form-control @error('email', 'updateProfileInformation') is-invalid @enderror"
                                                    id="email" name="email" value="{{ old('email', $user->email) }}" required>
                                                @error('email', 'updateProfileInformation')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-12 text-end">
                                            <button type="submit" class="profile-btn-primary">
                                                <i class="ri-save-line"></i> Guardar cambios
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            {{-- Contraseña --}}
                            <div class="tab-pane" id="password" role="tabpanel">
                                @if (session('status') === 'password-updated')
                                    <div class="alert alert-success alert-borderless d-flex align-items-center gap-2">
                                        <i class="ri-checkbox-circle-line fs-18"></i>
                                        <span>Contraseña actualizada correctamente.</span>
                                    </div>
                                @endif

                                <form method="POST" action="{{ route('user-password.update') }}" class="profile-form">
                                    @csrf
                                    @method('PUT')

                                    <div class="row g-3">
                                        <div class="col-lg-4">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Contraseña actual</label>
                                                <input type="password" name="current_password" id="current_password"
                                                    autocomplete="current-password"
                                                    class="form-control @error('current_password', 'updatePassword') is-invalid @enderror">
                                                @error('current_password', 'updatePassword')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">Nueva contraseña</label>
                                                <input type="password" name="password" id="new_password"
                                                    autocomplete="new-password"
                                                    class="form-control @error('password', 'updatePassword') is-invalid @enderror">
                                                @error('password', 'updatePassword')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                                                <input type="password" name="password_confirmation" id="confirm_password"
                                                    autocomplete="new-password" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-lg-12 text-end">
                                            <button type="submit" class="profile-btn-primary">
                                                <i class="ri-lock-password-line"></i> Cambiar contraseña
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>

                            {{-- 2FA --}}
                            <div class="tab-pane" id="seguridad" role="tabpanel">
                                <h5 class="fs-15 mb-1" style="color: var(--marca-tinta); font-weight: 650;">
                                    <i class="ri-shield-keyhole-line align-bottom me-1" style="color: var(--marca-azul-texto);"></i>
                                    Verificación en dos pasos
                                </h5>
                                <p class="text-muted mb-3">
                                    Agrega una segunda capa de seguridad: además de la contraseña se pedirá un código
                                    temporal de tu app de autenticación (Google Authenticator, Authy, etc.).
                                </p>

                                @if ($user->two_factor_secret)
                                    <div class="alert alert-success alert-borderless d-flex align-items-center gap-2">
                                        <i class="ri-shield-check-line fs-18"></i>
                                        <span>La verificación en dos pasos está <strong>activa</strong> en tu cuenta.</span>
                                    </div>

                                    @if (session('status') === 'two-factor-authentication-enabled')
                                        <div class="mb-4">
                                            <p class="mb-2 fw-semibold" style="color: var(--marca-tinta);">Escanea este código QR con tu aplicación de autenticación:</p>
                                            <div class="profile-2fa-qr">
                                                {!! $user->twoFactorQrCodeSvg() !!}
                                            </div>
                                        </div>
                                        <div class="mb-4">
                                            <p class="mb-2 fw-semibold" style="color: var(--marca-tinta);">Guarda estos códigos de recuperación en un lugar seguro:</p>
                                            <div class="profile-2fa-codes">
                                                @foreach (json_decode(decrypt($user->two_factor_recovery_codes), true) as $code)
                                                    <div>{{ $code }}</div>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <form method="POST" action="{{ route('two-factor.disable') }}"
                                        data-confirm="¿Desactivar la verificación en dos pasos?"
                                        data-confirm-text="Tu cuenta quedará protegida solo por la contraseña.">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="profile-btn-danger">
                                            <i class="ri-shield-cross-line"></i> Desactivar
                                        </button>
                                    </form>
                                @else
                                    <div class="profile-alert profile-alert--warning d-flex align-items-center gap-2 p-3 rounded-3 mb-3"
                                        style="color: #c98500; background: rgba(201, 133, 0, .06); border: 1px solid rgba(201, 133, 0, .2);">
                                        <i class="ri-alert-line fs-18"></i>
                                        <span>La verificación en dos pasos no está activada. Te recomendamos activarla para mayor seguridad.</span>
                                    </div>

                                    <form method="POST" action="{{ route('two-factor.enable') }}">
                                        @csrf
                                        <button type="submit" class="profile-btn-primary">
                                            <i class="ri-shield-keyhole-line"></i> Activar verificación en dos pasos
                                        </button>
                                    </form>
                                @endif
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
