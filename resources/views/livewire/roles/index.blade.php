{{--
    "personas-modulo" aporta los estilos compartidos de este tipo de módulo
    (encabezado, tarjetas, modales), definidos en _personas.scss.
--}}
<div class="personas-modulo roles-modulo">

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 personas-encabezado">
        <div class="card-body p-0">
            <div class="p-4 personas-hero">
                <div class="personas-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 personas-chip">
                            <i class="ri-shield-keyhole-line me-1"></i> Control de acceso
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title personas-tile text-white rounded-3 fs-3">
                                    <i class="ri-shield-keyhole-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Roles y permisos</h4>
                                <p class="text-white-50 mb-0">
                                    Define qué puede hacer cada perfil. Los roles se asignan desde
                                    <a href="{{ route('usuarios.index') }}" class="text-white text-decoration-underline">Usuarios</a>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('roles.crear')
                                <button type="button" class="btn btn-light personas-nueva-hero" wire:click="abrirCrear">
                                    <i class="ri-add-line align-bottom me-1"></i> Nuevo rol
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Tarjetas de rol ===================== --}}
    <div class="row g-3">
        @foreach ($roles as $rol)
            @php $esAdmin = $rol->name === \App\Livewire\Roles\Index::ROL_PROTEGIDO; @endphp

            <div class="col-xl-4 col-md-6" wire:key="rol-{{ $rol->id }}">
                <div class="card border-0 shadow-sm h-100 tarjeta-rol-permisos {{ $esAdmin ? 'rol-protegido' : '' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3 mb-3">
                            <div class="avatar-sm flex-shrink-0">
                                <span class="avatar-title rounded-circle fs-4 {{ $esAdmin ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' }}">
                                    <i class="{{ $esAdmin ? 'ri-shield-star-line' : 'ri-shield-user-line' }}"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <h5 class="mb-1 text-truncate">{{ ucfirst($rol->name) }}</h5>
                                <div class="text-muted fs-13">
                                    {{ $rol->users_count }}
                                    {{ $rol->users_count === 1 ? 'usuario' : 'usuarios' }}
                                </div>
                            </div>

                            @if ($esAdmin)
                                <span class="badge bg-danger-subtle text-danger flex-shrink-0">Protegido</span>
                            @endif
                        </div>

                        @if ($esAdmin)
                            {{-- Gate::before le concede todo: su matriz es informativa. --}}
                            <div class="alert alert-danger alert-borderless fs-13 mb-3">
                                <i class="ri-information-line align-bottom me-1"></i>
                                Tiene <strong>acceso total</strong> al sistema de forma permanente. No necesita
                                permisos asignados ni se le pueden quitar.
                            </div>
                        @else
                            <div class="mb-2 d-flex justify-content-between align-items-center">
                                <small class="text-muted">Permisos asignados</small>
                                <small class="fw-semibold">{{ $rol->permissions_count }} / {{ $this->totalPermisos }}</small>
                            </div>
                            <div class="progress mb-3 barra-permisos">
                                <div class="progress-bar bg-primary" role="progressbar"
                                    style="width: {{ $this->totalPermisos > 0 ? round($rol->permissions_count / $this->totalPermisos * 100) : 0 }}%"
                                    aria-valuenow="{{ $rol->permissions_count }}" aria-valuemin="0"
                                    aria-valuemax="{{ $this->totalPermisos }}"></div>
                            </div>
                        @endif
                    </div>

                    <div class="card-footer bg-transparent border-top-dashed">
                        <div class="d-flex gap-2 justify-content-end">
                            @can('roles.editar')
                                @unless ($esAdmin)
                                    <button type="button" class="btn btn-sm btn-soft-primary"
                                        wire:click="abrirPermisos({{ $rol->id }})">
                                        <i class="ri-key-2-line align-bottom me-1"></i> Permisos
                                    </button>
                                    <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon rounded-circle"
                                        wire:click="abrirEditar({{ $rol->id }})" title="Renombrar"
                                        aria-label="Renombrar el rol {{ $rol->name }}">
                                        <i class="ri-pencil-line fs-16"></i>
                                    </button>
                                @endunless
                            @endcan

                            @can('roles.eliminar')
                                @unless ($esAdmin)
                                    <button type="button" class="btn btn-sm btn-ghost-danger btn-icon rounded-circle"
                                        wire:click="confirmarEliminar({{ $rol->id }})" title="Eliminar"
                                        aria-label="Eliminar el rol {{ $rol->name }}">
                                        <i class="ri-delete-bin-line fs-16"></i>
                                    </button>
                                @endunless
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ===================== Modal alta / edición del rol ===================== --}}
    <div class="modal fade" id="modalRol" tabindex="-1" aria-hidden="true" wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-persona-dialog">
            <div class="modal-content border-0 modal-persona-content {{ $rolId ? 'modal-editar-persona' : '' }}">
                <div class="modal-header modal-persona-header p-4">
                    <div class="modal-persona-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-persona-icon rounded-circle fs-4">
                                <i class="{{ $rolId ? 'ri-pencil-line' : 'ri-shield-user-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">{{ $rolId ? 'Renombrar rol' : 'Nuevo rol' }}</h5>
                            <small>
                                {{ $rolId ? 'Los permisos asignados se conservan.' : 'Después podrás asignarle permisos.' }}
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-persona-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-persona-body p-4">
                        <h6 class="modal-section-title mb-3"><i class="ri-shield-user-line"></i> Datos del rol</h6>

                        <label for="nombre" class="form-label">Nombre del rol <span class="text-danger">*</span></label>
                        <input type="text" id="nombre" wire:model.live.debounce.400ms="nombre"
                            class="form-control @error('nombre') is-invalid @elseif ($nombre !== '') is-valid @enderror"
                            placeholder="Ej. supervisor" maxlength="50">
                        @error('nombre')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">
                            En minúsculas y sin acentos: este nombre también se usa desde el código.
                        </small>
                    </div>

                    <div class="modal-footer modal-persona-footer p-4">
                        <div class="d-flex align-items-center justify-content-between w-100 gap-3 flex-wrap">
                            <span class="modal-pista-guardar {{ $this->formularioValido ? 'modal-pista-ok' : '' }}">
                                @if ($this->formularioValido)
                                    <i class="ri-checkbox-circle-fill"></i> Listo para guardar
                                @else
                                    <i class="ri-information-line"></i>
                                    Completa los campos con <span class="text-danger">*</span>
                                @endif
                            </span>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-success modal-guardar"
                                    @disabled(! $this->formularioValido) wire:loading.attr="disabled" wire:target="guardar">
                                    <span wire:loading.remove wire:target="guardar">
                                        <i class="ri-save-line align-bottom me-1"></i>
                                        {{ $rolId ? 'Guardar cambios' : 'Crear rol' }}
                                    </span>
                                    <span wire:loading wire:target="guardar">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                        Guardando...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===================== Modal matriz de permisos ===================== --}}
    <div class="modal fade" id="modalPermisosRol" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-persona-dialog">
            <div class="modal-content border-0 modal-persona-content">
                <div class="modal-header modal-persona-header p-4">
                    <div class="modal-persona-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-persona-icon rounded-circle fs-4">
                                <i class="ri-key-2-line"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">Permisos de «{{ ucfirst($permisosRolNombre) }}»</h5>
                            <small>Marca lo que este rol podrá hacer en cada módulo.</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-persona-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body modal-persona-body p-4">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <span class="badge bg-primary-subtle text-primary fs-13">
                                {{ count($permisosSeleccionados) }} de {{ $this->totalPermisos }} permisos
                            </span>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-soft-primary" wire:click="marcarTodos">
                                <i class="ri-checkbox-multiple-line align-bottom me-1"></i> Marcar todos
                            </button>
                            <button type="button" class="btn btn-sm btn-soft-secondary" wire:click="desmarcarTodos">
                                <i class="ri-checkbox-multiple-blank-line align-bottom me-1"></i> Desmarcar todos
                            </button>
                        </div>
                    </div>

                    <div class="row g-3">
                        @foreach ($this->permisosPorModulo as $modulo => $permisos)
                            @php
                                $nombresModulo = $permisos->pluck('name')->all();
                                $marcadosModulo = count(array_intersect($nombresModulo, $permisosSeleccionados));
                                $todosDelModulo = $marcadosModulo === count($nombresModulo);
                            @endphp

                            <div class="col-lg-6" wire:key="modulo-{{ $modulo }}">
                                <div class="modulo-permisos h-100 {{ $marcadosModulo > 0 ? 'con-permisos' : '' }}">
                                    <div class="d-flex align-items-center justify-content-between mb-3">
                                        <div class="d-flex align-items-center gap-2 min-w-0">
                                            <i class="ri-folder-2-line text-primary"></i>
                                            <h6 class="mb-0 text-capitalize text-truncate">{{ $modulo }}</h6>
                                            <span class="badge bg-light text-muted border fs-11">
                                                {{ $marcadosModulo }}/{{ count($nombresModulo) }}
                                            </span>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-link p-0 text-nowrap"
                                            wire:click="alternarModulo('{{ $modulo }}')">
                                            {{ $todosDelModulo ? 'Quitar todo' : 'Marcar todo' }}
                                        </button>
                                    </div>

                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach ($permisos as $permiso)
                                            @php $accion = Str::after($permiso->name, '.'); @endphp

                                            <label class="chip-permiso {{ in_array($permiso->name, $permisosSeleccionados, true) ? 'activo' : '' }}"
                                                for="permiso-{{ $permiso->id }}">
                                                <input type="checkbox" id="permiso-{{ $permiso->id }}"
                                                    value="{{ $permiso->name }}" wire:model.live="permisosSeleccionados"
                                                    class="form-check-input mt-0">
                                                <span>{{ ucfirst(str_replace('_', ' ', $accion)) }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="modal-footer modal-persona-footer p-4">
                    <div class="d-flex align-items-center justify-content-between w-100 gap-3">
                        <small class="text-muted">
                            <i class="ri-information-line align-bottom me-1"></i>
                            Los cambios se aplican en cuanto el usuario recargue la página.
                        </small>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-success modal-guardar" wire:click="guardarPermisos"
                                wire:loading.attr="disabled" wire:target="guardarPermisos">
                                <span wire:loading.remove wire:target="guardarPermisos">
                                    <i class="ri-save-line align-bottom me-1"></i> Guardar permisos
                                </span>
                                <span wire:loading wire:target="guardarPermisos">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Guardando...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Modal eliminar rol ===================== --}}
    <div class="modal fade zoomIn" id="modalEliminarRol" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1">
                            <i class="{{ $eliminarUsuarios > 0 ? 'ri-lock-line' : 'ri-delete-bin-line' }}"></i>
                        </span>
                    </div>

                    <span class="modal-eliminar-etiqueta">Confirmación</span>
                    @if ($eliminarUsuarios > 0)
                        <h5 class="mb-2">Este rol está en uso</h5>
                        <p class="text-muted mb-4">
                            <strong class="modal-eliminar-nombre">{{ ucfirst($eliminarNombre) }}</strong> está asignado a
                            <strong>{{ $eliminarUsuarios }}</strong>
                            {{ $eliminarUsuarios === 1 ? 'usuario' : 'usuarios' }}.
                            Cámbiales el rol antes de eliminarlo.
                        </p>
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Entendido</button>
                    @else
                        <h5 class="mb-2">¿Eliminar este rol?</h5>
                        <p class="text-muted mb-4">
                            Se eliminará <strong class="modal-eliminar-nombre">{{ ucfirst($eliminarNombre) }}</strong>
                            y sus permisos asignados.
                        </p>

                        <div class="d-flex gap-2 justify-content-center">
                            <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-danger modal-eliminar-btn w-100" wire:click="eliminar"
                                wire:loading.attr="disabled" wire:target="eliminar">
                                <span wire:loading.remove wire:target="eliminar">Sí, eliminar</span>
                                <span wire:loading wire:target="eliminar">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Eliminando...
                                </span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
