<div class="card border-0 shadow-sm mb-4 sesion-app">
    <div class="card-body">
        <div class="row g-4">
            <div class="col-lg-5">
                <h6 class="mb-1"><i class="ri-smartphone-line me-1"></i> Sesión en la app del teléfono</h6>
                <p class="text-muted fs-13 mb-3">
                    Si nadie toca la app durante este tiempo, la sesión se cierra sola. Un minuto antes avisa, y quien
                    tenga la huella activada vuelve a entrar con un toque.
                </p>
                <label for="sesion-minutos" class="form-label fs-12 mb-1">Cerrar tras</label>
                <select id="sesion-minutos" class="form-select form-select-sm" style="max-width: 12rem;" wire:model.live="minutos">
                    @foreach ($opciones as $o)
                        <option value="{{ $o }}">{{ $o }} minutos sin uso</option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-7">
                <h6 class="mb-1"><i class="ri-fingerprint-line me-1"></i> Teléfonos que entran con huella o rostro</h6>
                <p class="text-muted fs-13 mb-3">
                    Cada persona lo activa en su teléfono al entrar con la contraseña. Si cambia la contraseña o se
                    bloquea la cuenta, se borra solo. Quita el de un teléfono perdido.
                </p>

                @if ($this->accesos->isEmpty())
                    <div class="sesion-app-vacio">Ningún teléfono registrado todavía.</div>
                @else
                    <ul class="list-unstyled mb-0 sesion-app-lista">
                        @foreach ($this->accesos as $a)
                            <li wire:key="acceso-{{ $a->id }}">
                                <div class="min-w-0">
                                    <span class="fw-semibold">{{ $a->user?->name ?? '—' }}</span>
                                    <small class="text-muted d-block text-truncate">
                                        {{ $a->nombre ?: 'Teléfono' }}
                                        · usado {{ $a->ultimo_uso_en?->diffForHumans() ?? 'nunca' }}
                                    </small>
                                </div>
                                <button type="button" class="btn btn-sm btn-soft-danger"
                                    wire:click="quitar({{ $a->id }})"
                                    wire:confirm="¿Quitar el ingreso con huella de este teléfono?">
                                    Quitar
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
