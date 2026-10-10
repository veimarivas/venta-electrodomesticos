<div class="tiendas-modulo">

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-store-2-line me-1"></i> Personal · Tiendas
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-map-pin-2-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Tiendas</h4>
                                <p class="text-white-50 mb-0">
                                    Dónde está cada tienda y en qué radio se puede marcar asistencia desde el teléfono.
                                    Hoy hay {{ $enTurno }} {{ $enTurno === 1 ? 'persona' : 'personas' }} en turno.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5 d-flex justify-content-lg-end">
                        @can('tiendas.crear')
                            <button type="button" class="btn btn-light crud-nueva-hero" wire:click="nueva">
                                <i class="ri-add-line align-bottom me-1"></i> Nueva tienda
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Tiendas ===================== --}}
    @if ($this->tiendas->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5 px-3">
                <div class="crud-empty-icon mx-auto mb-3">
                    <span class="avatar-title fs-2"><i class="ri-store-2-line"></i></span>
                </div>
                <h6 class="mb-1">Todavía no hay tiendas</h6>
                <p class="text-muted mb-0 fs-13">Registra cada tienda con su ubicación para que el personal pueda marcar asistencia.</p>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($this->tiendas as $t)
                <div class="col-xl-4 col-md-6" wire:key="tienda-{{ $t->id }}">
                    <div @class(['card border-0 shadow-sm h-100 tienda-tarjeta', 'tienda-tarjeta--inactiva' => ! $t->activa])>
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                                <div class="min-w-0">
                                    <h5 class="mb-0 text-truncate">{{ $t->nombre }}</h5>
                                    <small class="text-muted">{{ $t->direccion ?: 'Sin dirección' }}</small>
                                </div>
                                <span @class(['tienda-estado', 'tienda-estado--activa' => $t->activa])>
                                    {{ $t->activa ? 'Activa' : 'Inactiva' }}
                                </span>
                            </div>

                            <ul class="list-unstyled tienda-datos mb-3">
                                <li>
                                    <i class="ri-map-pin-line"></i>
                                    @if ($t->tieneUbicacion())
                                        <a href="{{ $t->enlaceMapa() }}" target="_blank" rel="noopener" class="font-monospace">
                                            {{ number_format($t->latitud, 5) }}, {{ number_format($t->longitud, 5) }}
                                        </a>
                                    @else
                                        <span class="text-danger">Sin ubicación: no se puede marcar aquí</span>
                                    @endif
                                </li>
                                <li><i class="ri-focus-3-line"></i> Radio de {{ $t->radio_metros }} m</li>
                                <li>
                                    <i class="ri-time-line"></i>
                                    @if ($t->horaEntradaCorta())
                                        Entrada {{ $t->horaEntradaCorta() }} · tolerancia {{ $t->tolerancia_minutos }} min
                                    @else
                                        Sin hora de entrada (no cuenta atrasos)
                                    @endif
                                </li>
                                <li><i class="ri-user-follow-line"></i> {{ $t->hoy }} {{ $t->hoy === 1 ? 'turno' : 'turnos' }} hoy</li>
                            </ul>

                            <div class="d-flex flex-wrap gap-2">
                                @can('tiendas.editar')
                                    <button type="button" class="btn btn-sm btn-soft-primary" wire:click="editar({{ $t->id }})">
                                        <i class="ri-pencil-line me-1"></i> Editar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-soft-secondary" wire:click="alternar({{ $t->id }})">
                                        {{ $t->activa ? 'Desactivar' : 'Activar' }}
                                    </button>
                                @endcan
                                @can('tiendas.eliminar')
                                    <button type="button" class="btn btn-sm btn-ghost-danger btn-icon ms-auto"
                                        wire:click="confirmarEliminar({{ $t->id }})" title="Quitar" aria-label="Quitar {{ $t->nombre }}">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="tiendas-ayuda mt-4">
        <i class="ri-smartphone-line"></i>
        <span>
            La ubicación más exacta se fija <strong>desde el teléfono, estando dentro de la tienda</strong>:
            en la app, <em>Asistencia → Tiendas → Fijar aquí</em>.
        </span>
    </div>

    {{-- ===================== Modal tienda ===================== --}}
    <div class="modal fade" id="modalTienda" tabindex="-1" aria-hidden="true" wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-crud-dialog">
            <div class="modal-content border-0 modal-crud-content">
                <div class="modal-header modal-crud-header p-4">
                    <div class="modal-crud-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-crud-icon rounded-circle fs-4">
                                <i class="{{ $tiendaId ? 'ri-pencil-line' : 'ri-store-2-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">{{ $tiendaId ? 'Editar tienda' : 'Nueva tienda' }}</h5>
                            <small class="text-muted">Ubicación, radio y hora de entrada.</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-crud-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="t-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" id="t-nombre" maxlength="120" wire:model="nombre"
                                    class="form-control @error('nombre') is-invalid @enderror" placeholder="Ej. Tienda Centro">
                                @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-6">
                                <label for="t-direccion" class="form-label">Dirección <span class="text-muted fw-normal fs-12">(opcional)</span></label>
                                <input type="text" id="t-direccion" maxlength="255" wire:model="direccion" class="form-control"
                                    placeholder="Calle, número, zona">
                            </div>

                            <div class="col-12">
                                <div class="tienda-ubicacion" x-data="{ buscando: false, error: '' }">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                        <span class="form-label mb-0">Ubicación</span>
                                        <button type="button" class="btn btn-sm btn-soft-primary" :disabled="buscando"
                                            x-on:click="
                                                if (!navigator.geolocation) { error = 'Este navegador no da la ubicación.'; return; }
                                                buscando = true; error = '';
                                                navigator.geolocation.getCurrentPosition(
                                                    p => { $wire.set('latitud', p.coords.latitude.toFixed(7)); $wire.set('longitud', p.coords.longitude.toFixed(7)); buscando = false; },
                                                    () => { error = 'No se pudo leer la ubicación (¿permiso del navegador?).'; buscando = false; },
                                                    { enableHighAccuracy: true, timeout: 15000 }
                                                );">
                                            <i class="ri-crosshair-2-line me-1"></i>
                                            <span x-text="buscando ? 'Buscando…' : 'Usar mi ubicación actual'"></span>
                                        </button>
                                    </div>
                                    <small class="text-danger d-block mb-2" x-show="error" x-text="error"></small>

                                    <div class="row g-2">
                                        <div class="col-12">
                                            <input type="text" wire:model.live.debounce.400ms="pegado"
                                                class="form-control form-control-sm @error('pegado') is-invalid @enderror"
                                                placeholder="O pega aquí las coordenadas o el enlace de Google Maps">
                                            @error('pegado') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-6">
                                            <label for="t-lat" class="form-label fs-12 mb-1">Latitud</label>
                                            <input type="text" id="t-lat" wire:model.live.debounce.500ms="latitud" inputmode="decimal"
                                                class="form-control form-control-sm font-monospace @error('latitud') is-invalid @enderror" placeholder="-17.7833">
                                            @error('latitud') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                        <div class="col-6">
                                            <label for="t-lng" class="form-label fs-12 mb-1">Longitud</label>
                                            <input type="text" id="t-lng" wire:model.live.debounce.500ms="longitud" inputmode="decimal"
                                                class="form-control form-control-sm font-monospace @error('longitud') is-invalid @enderror" placeholder="-63.1821">
                                            @error('longitud') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        </div>
                                    </div>

                                    @if (is_numeric($latitud) && is_numeric($longitud))
                                        <div class="tienda-mapa mt-3">
                                            <iframe title="Ubicación de la tienda" referrerpolicy="no-referrer"
                                                src="https://maps.google.com/maps?q={{ (float) $latitud }},{{ (float) $longitud }}&z=18&output=embed"></iframe>
                                        </div>
                                        <small class="text-muted">
                                            Revisa que el punto caiga dentro del local ·
                                            <a href="https://www.google.com/maps?q={{ (float) $latitud }},{{ (float) $longitud }}" target="_blank" rel="noopener">
                                                Abrir en Google Maps <i class="ri-external-link-line"></i>
                                            </a>
                                        </small>
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="t-radio" class="form-label">Radio para marcar</label>
                                <div class="input-group has-validation">
                                    <input type="number" id="t-radio" min="10" max="500" wire:model="radio"
                                        class="form-control @error('radio') is-invalid @enderror">
                                    <span class="input-group-text">m</span>
                                    @error('radio') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-text">30 m cubre el error normal del GPS dentro del local.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="t-hora" class="form-label">Hora de entrada <span class="text-muted fw-normal fs-12">(opcional)</span></label>
                                <input type="time" id="t-hora" wire:model="horaEntrada"
                                    class="form-control @error('horaEntrada') is-invalid @enderror">
                                @error('horaEntrada') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Sin hora no se cuentan atrasos.</div>
                            </div>
                            <div class="col-md-4">
                                <label for="t-tolerancia" class="form-label">Tolerancia</label>
                                <div class="input-group has-validation">
                                    <input type="number" id="t-tolerancia" min="0" max="120" wire:model="tolerancia"
                                        class="form-control @error('tolerancia') is-invalid @enderror">
                                    <span class="input-group-text">min</span>
                                    @error('tolerancia') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                                <div class="form-text">Llegar dentro de este margen no es atraso.</div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input type="checkbox" id="t-activa" class="form-check-input" wire:model="activa">
                                    <label for="t-activa" class="form-check-label">Activa: se puede marcar asistencia aquí</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer modal-crud-footer p-4">
                        <div class="d-flex gap-2 ms-auto">
                            <button type="button" class="btn btn-light modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success modal-guardar" wire:loading.attr="disabled" wire:target="guardar">
                                <i class="ri-save-line align-bottom me-1"></i> {{ $tiendaId ? 'Guardar cambios' : 'Registrar tienda' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===================== Modal quitar ===================== --}}
    <div class="modal fade zoomIn" id="modalEliminarTienda" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 shadow-lg modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1"><i class="ri-delete-bin-line"></i></span>
                    </div>
                    <h5 class="mb-2">¿Quitar esta tienda?</h5>
                    <p class="text-muted mb-4">Ya no se podrá marcar asistencia aquí. El historial de asistencia se conserva.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger modal-eliminar-btn w-100" wire:click="eliminar">Sí, quitar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
