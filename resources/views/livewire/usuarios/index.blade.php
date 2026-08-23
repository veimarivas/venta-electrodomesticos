{{--
    "personas-modulo" aporta los estilos compartidos de este tipo de módulo
    (encabezado, tabla, modales, paginación), definidos en _personas.scss.
--}}
<div class="personas-modulo usuarios-modulo">

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 personas-encabezado">
        <div class="card-body p-0">
            <div class="p-4 personas-hero">
                <div class="personas-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 personas-chip">
                            <i class="ri-shield-user-line me-1"></i> Acceso al sistema
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title personas-tile text-white rounded-3 fs-3">
                                    <i class="ri-shield-user-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Usuarios</h4>
                                <p class="text-white-50 mb-0">
                                    Quién entra al panel y con qué rol. Los permisos de cada rol se definen en
                                    <a href="{{ route('roles.index') }}" class="text-white text-decoration-underline">Roles y permisos</a>.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('usuarios.crear')
                                <button type="button" class="btn btn-light personas-nueva-hero" wire:click="abrirCrear">
                                    <i class="ri-user-add-line align-bottom me-1"></i> Nuevo usuario
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4 personas-kpis">
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Cuentas registradas" value="{{ $totalUsuarios }}" icon="bx-user"
                color="primary" caption="Total en el sistema" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Cuentas activas" value="{{ $totalActivos }}" icon="bx-check-shield"
                color="success" caption="Pueden iniciar sesión" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Roles definidos" value="{{ $totalRoles }}" icon="bx-key"
                color="info" caption="Perfiles de permisos" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Personas sin cuenta" value="{{ $personasSinCuenta }}" icon="bx-link-alt"
                color="warning" caption="Disponibles para vincular" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm personas-listado">
        <div class="card-header bg-transparent py-3 personas-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Listado de usuarios
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $usuarios->total() }}
                        {{ $usuarios->total() === 1 ? 'usuario encontrado' : 'usuarios encontrados' }}
                    </small>
                </div>

                <div class="col-md-4">
                    <div class="search-box">
                        <input type="text" class="form-control personas-busqueda"
                            placeholder="Buscar por nombre o correo..." wire:model.live.debounce.400ms="buscar">
                        <i class="ri-search-line search-icon"></i>
                        @if ($buscar !== '')
                            <button type="button"
                                class="btn btn-sm btn-link text-muted position-absolute end-0 top-50 translate-middle-y me-1 p-1"
                                wire:click="$set('buscar', '')" title="Limpiar búsqueda">
                                <i class="ri-close-circle-fill fs-16"></i>
                            </button>
                        @endif
                    </div>
                </div>

                <div class="col-md-3">
                    <select class="form-select" wire:model.live="filtroRol">
                        <option value="">Todos los roles</option>
                        @foreach ($this->rolesDisponibles as $rol)
                            <option value="{{ $rol->name }}">{{ ucfirst($rol->name) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <select class="form-select" wire:model.live="filtroEstado">
                        <option value="todos">Todo estado</option>
                        <option value="activos">Activos</option>
                        <option value="inactivos">Inactivos</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-personas"
                    wire:loading.class="opacity-50" wire:target="buscar, ordenar, filtroRol, filtroEstado, gotoPage, previousPage, nextPage">
                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4" role="button" wire:click="ordenar('name')">
                                Usuario <x-sort-icon :field="'name'" :current="$ordenarPor" :direction="$direccionOrden" />
                            </th>
                            <th scope="col">Roles</th>
                            <th scope="col">Persona vinculada</th>
                            <th scope="col" role="button" wire:click="ordenar('last_login_at')">
                                Último acceso <x-sort-icon :field="'last_login_at'" :current="$ordenarPor" :direction="$direccionOrden" />
                            </th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($usuarios as $usuario)
                            <tr wire:key="usuario-{{ $usuario->id }}" class="{{ $usuario->is_active ? '' : 'fila-inactiva' }}">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-xs flex-shrink-0">
                                            <img src="{{ $usuario->avatar_url }}" alt=""
                                                class="rounded-circle avatar-xs">
                                        </div>
                                        <div class="min-w-0">
                                            <h6 class="mb-0 text-truncate">
                                                {{ $usuario->name }}
                                                @if ($usuario->id === auth()->id())
                                                    <span class="badge bg-info-subtle text-info fs-10 ms-1">Tú</span>
                                                @endif
                                            </h6>
                                            <small class="text-muted text-truncate d-block">{{ $usuario->email }}</small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    @forelse ($usuario->roles as $rol)
                                        <span class="badge {{ $rol->name === 'admin' ? 'bg-danger-subtle text-danger' : 'bg-primary-subtle text-primary' }} fs-12 me-1">
                                            {{ ucfirst($rol->name) }}
                                        </span>
                                    @empty
                                        <span class="badge bg-warning-subtle text-warning fs-12">Sin rol</span>
                                    @endforelse
                                </td>

                                <td>
                                    @if ($usuario->persona)
                                        <div class="text-truncate">{{ $usuario->persona->nombre_completo }}</div>
                                        <small class="text-muted">CI {{ $usuario->persona->carnet }}</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($usuario->last_login_at)
                                        <div>{{ $usuario->last_login_at->format('d/m/Y H:i') }}</div>
                                        <small class="text-muted">{{ $usuario->last_login_at->diffForHumans() }}</small>
                                    @else
                                        <span class="text-muted">Nunca ingresó</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($usuario->is_active)
                                        <span class="badge bg-success-subtle text-success fs-12">
                                            <i class="ri-checkbox-circle-line align-bottom me-1"></i> Activo
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary fs-12">
                                            <i class="ri-forbid-2-line align-bottom me-1"></i> Inactivo
                                        </span>
                                    @endif
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @can('usuarios.editar')
                                            @if ($usuario->id !== auth()->id())
                                                <button type="button"
                                                    class="btn btn-sm btn-icon rounded-circle {{ $usuario->is_active ? 'btn-ghost-secondary' : 'btn-ghost-success' }}"
                                                    wire:click="alternarEstado({{ $usuario->id }})"
                                                    title="{{ $usuario->is_active ? 'Desactivar cuenta' : 'Activar cuenta' }}"
                                                    aria-label="{{ $usuario->is_active ? 'Desactivar' : 'Activar' }} la cuenta de {{ $usuario->name }}">
                                                    <i class="{{ $usuario->is_active ? 'ri-toggle-fill' : 'ri-toggle-line' }} fs-16"></i>
                                                </button>
                                            @endif

                                            <button type="button"
                                                class="btn btn-sm btn-ghost-primary btn-icon rounded-circle persona-accion-editar"
                                                wire:click="abrirEditar({{ $usuario->id }})"
                                                title="Editar" aria-label="Editar a {{ $usuario->name }}">
                                                <i class="ri-pencil-line fs-16"></i>
                                            </button>
                                        @endcan

                                        @can('usuarios.eliminar')
                                            @if ($usuario->id !== auth()->id())
                                                <button type="button"
                                                    class="btn btn-sm btn-ghost-danger btn-icon rounded-circle persona-accion-eliminar"
                                                    wire:click="confirmarEliminar({{ $usuario->id }})"
                                                    title="Eliminar" aria-label="Eliminar a {{ $usuario->name }}">
                                                    <i class="ri-delete-bin-line fs-16"></i>
                                                </button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="text-center py-5">
                                        <div class="personas-empty-icon mx-auto mb-4">
                                            <span class="avatar-title rounded-circle fs-1">
                                                <i class="{{ $buscar !== '' ? 'ri-search-eye-line' : 'ri-shield-user-line' }}"></i>
                                            </span>
                                        </div>
                                        @if ($buscar !== '' || $filtroRol !== '' || $filtroEstado !== 'todos')
                                            <h5 class="mb-1">Sin resultados</h5>
                                            <p class="text-muted mb-3">Prueba a quitar los filtros o cambiar la búsqueda.</p>
                                        @else
                                            <h5 class="mb-1">Todavía no hay usuarios</h5>
                                            <p class="text-muted mb-3">Crea la primera cuenta de acceso al panel.</p>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($usuarios->total() > 0)
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando <span class="fw-semibold">{{ $usuarios->firstItem() }}</span>–<span
                            class="fw-semibold">{{ $usuarios->lastItem() }}</span>
                        de <span class="fw-semibold">{{ $usuarios->total() }}</span> usuarios
                    </p>

                    @if ($usuarios->hasPages())
                        <div class="paginacion-compacta">
                            {{ $usuarios->onEachSide(1)->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal alta / edición ===================== --}}
    <div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true" wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-persona-dialog">
            <div class="modal-content border-0 modal-persona-content {{ $usuarioId ? 'modal-editar-persona' : '' }}">
                <div class="modal-header modal-persona-header p-4">
                    <div class="modal-persona-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-persona-icon rounded-circle fs-4">
                                <i class="{{ $usuarioId ? 'ri-pencil-line' : 'ri-user-add-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">
                                {{ $usuarioId ? 'Editar usuario' : 'Nuevo usuario' }}
                            </h5>
                            <small>
                                {{ $usuarioId ? 'Deja la contraseña vacía para conservar la actual.' : 'Define sus credenciales y qué rol tendrá.' }}
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-persona-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-persona-body p-4">

                        <h6 class="modal-section-title mb-3"><i class="ri-user-line"></i> Datos de la cuenta</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="u-name" class="form-label">Nombre completo <span class="text-danger">*</span></label>
                                <input type="text" id="u-name" wire:model.live.debounce.400ms="name"
                                    class="form-control @error('name') is-invalid @elseif ($name !== '') is-valid @enderror"
                                    placeholder="Juan Carlos Rivas" maxlength="100">
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="u-email" class="form-label">Correo electrónico <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="ri-mail-line"></i></span>
                                    <input type="email" id="u-email" wire:model.live.debounce.400ms="email"
                                        class="form-control border-start-0 ps-0 @error('email') is-invalid @elseif ($email !== '') is-valid @enderror"
                                        placeholder="juan@tienda.com">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="u-phone" class="form-label">
                                    Teléfono <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="ri-phone-line"></i></span>
                                    <input type="text" id="u-phone" wire:model.live.debounce.400ms="phone"
                                        class="form-control border-start-0 ps-0 @error('phone') is-invalid @enderror"
                                        placeholder="71234567" maxlength="8" inputmode="numeric">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="u-persona" class="form-label">
                                    Persona <span class="text-danger">*</span>
                                </label>
                                <select id="u-persona" wire:model.live="persona_id"
                                    class="form-select @error('persona_id') is-invalid @enderror">
                                    <option value="">Seleccione una persona...</option>
                                    @foreach ($this->personasVinculables as $persona)
                                        <option value="{{ $persona->id }}">
                                            {{ $persona->nombre_completo }} — CI {{ $persona->carnet }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('persona_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Cada cuenta requiere su ficha del módulo de personas.</small>
                            </div>
                        </div>

                        <h6 class="modal-section-title mt-4 mb-3"><i class="ri-lock-2-line"></i> Contraseña</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="u-password" class="form-label">
                                    {{ $usuarioId ? 'Nueva contraseña' : 'Contraseña' }}
                                    @if ($usuarioId)
                                        <span class="text-muted fw-normal fs-12">(dejar vacía para no cambiarla)</span>
                                    @else
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <input type="password" id="u-password" wire:model.live.debounce.500ms="password"
                                    autocomplete="new-password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    placeholder="Mínimo 8 caracteres con letras y números">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="u-password-confirm" class="form-label">Confirmar contraseña</label>
                                <input type="password" id="u-password-confirm"
                                    wire:model.live.debounce.500ms="password_confirmation" autocomplete="new-password"
                                    class="form-control" placeholder="Repite la contraseña">
                            </div>
                        </div>

                        <h6 class="modal-section-title mt-4 mb-3"><i class="ri-shield-keyhole-line"></i> Roles y acceso</h6>

                        @error('roles')
                            <div class="alert alert-danger alert-borderless py-2 fs-13">{{ $message }}</div>
                        @enderror

                        <div class="row g-2">
                            @foreach ($this->rolesDisponibles as $rol)
                                <div class="col-md-6">
                                    <label class="tarjeta-rol {{ in_array($rol->name, $roles, true) ? 'seleccionado' : '' }}"
                                        for="rol-{{ $rol->id }}">
                                        <input class="form-check-input mt-0" type="checkbox" id="rol-{{ $rol->id }}"
                                            value="{{ $rol->name }}" wire:model.live="roles">
                                        <span class="min-w-0">
                                            <span class="d-block fw-semibold">{{ ucfirst($rol->name) }}</span>
                                            <span class="d-block text-muted fs-12">
                                                @if ($rol->name === 'admin')
                                                    Acceso total al sistema
                                                @else
                                                    {{ $rol->permissions_count }}
                                                    {{ $rol->permissions_count === 1 ? 'permiso' : 'permisos' }}
                                                @endif
                                            </span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>

                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="u-activo"
                                wire:model.live="is_active">
                            <label class="form-check-label" for="u-activo">
                                Cuenta activa
                                <small class="d-block text-muted">Si se desactiva, no podrá iniciar sesión.</small>
                            </label>
                        </div>
                        @error('is_active')
                            <div class="text-danger fs-13 mt-1">{{ $message }}</div>
                        @enderror
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
                                        {{ $usuarioId ? 'Guardar cambios' : 'Crear usuario' }}
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

    {{-- ===================== Modal eliminar ===================== --}}
    <div class="modal fade zoomIn" id="modalEliminarUsuario" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1"><i class="ri-delete-bin-line"></i></span>
                    </div>

                    <span class="modal-eliminar-etiqueta">Confirmación</span>
                    <h5 class="mb-2">¿Eliminar esta cuenta?</h5>
                    <p class="text-muted mb-4">
                        <strong class="modal-eliminar-nombre">{{ $eliminarNombre }}</strong> perderá el acceso al panel.
                        Su ficha personal queda libre y se conserva en el módulo de personas.
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
                </div>
            </div>
        </div>
    </div>
</div>
