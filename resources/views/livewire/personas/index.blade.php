<div class="personas-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 personas-encabezado">
        <div class="card-body p-0">
            <div class="p-4 p-lg-4 personas-hero">
                <div class="personas-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 personas-chip">
                            <i class="ri-team-line me-1"></i> Registro de personas
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title bg-white bg-opacity-25 text-white rounded-3 fs-3">
                                    <i class="ri-team-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Personas</h4>
                                <p class="text-white-50 mb-0">
                                    Clientes, trabajadores y contactos de la tienda en un solo registro.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('personas.crear')
                                <button type="button" class="btn btn-light personas-nueva-hero" wire:click="abrirCrear">
                                    <i class="ri-add-line align-bottom me-1"></i> Nueva persona
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
            <x-stat-card label="Personas registradas" value="{{ $totalPersonas }}" icon="bx-group"
                color="primary" caption="Total en el sistema" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Con correo" value="{{ $totalConCorreo }}" icon="bx-envelope"
                color="success" caption="Tienen correo registrado" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Con celular" value="{{ $totalConCelular }}" icon="bx-phone"
                color="info" caption="Tienen celular registrado" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Cumplen años este mes" value="{{ $cumpleaniosMes }}" icon="bx-gift"
                color="warning" caption="{{ ucfirst(now()->translatedFormat('F Y')) }}" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm personas-listado">
        <div class="card-header bg-transparent py-3 personas-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Listado de personas
                        {{-- Indicador de carga en línea: nunca cubre la tabla,
                             así que jamás puede bloquear los botones de acción. --}}
                        <span class="spinner-border spinner-border-sm text-primary" role="status"
                            wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $personas->total() }}
                        {{ $personas->total() === 1 ? 'persona encontrada' : 'personas encontradas' }}
                    </small>
                </div>

                <div class="col-md-8">
                    <div class="search-box">
                        <input type="text" class="form-control personas-busqueda"
                            placeholder="Buscar por carnet, nombre, celular o correo..."
                            wire:model.live.debounce.400ms="buscar">
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
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                {{-- Durante la carga la tabla se atenúa; sin capas por encima. --}}
                <table class="table table-hover align-middle mb-0 tabla-personas"
                    wire:loading.class="opacity-50" wire:target="buscar, ordenar, gotoPage, previousPage, nextPage">
                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4" role="button" wire:click="ordenar('apellido_paterno')">
                                Persona <x-sort-icon :field="'apellido_paterno'" :current="$ordenarPor" :direction="$direccionOrden" />
                            </th>
                            <th scope="col" role="button" wire:click="ordenar('carnet')">
                                Carnet <x-sort-icon :field="'carnet'" :current="$ordenarPor" :direction="$direccionOrden" />
                            </th>
                            <th scope="col">Contacto</th>
                            <th scope="col" role="button" wire:click="ordenar('fecha_nacimiento')">
                                Nacimiento <x-sort-icon :field="'fecha_nacimiento'" :current="$ordenarPor" :direction="$direccionOrden" />
                            </th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($personas as $persona)
                            <tr wire:key="persona-{{ $persona->id }}">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-xs flex-shrink-0 position-relative">
                                            <span
                                                class="avatar-title rounded-circle bg-{{ $persona->color_avatar }}-subtle text-{{ $persona->color_avatar }} fw-semibold">
                                                {{ $persona->iniciales }}
                                            </span>
                                            @if ($persona->user)
                                                <span class="persona-cuenta-dot" role="img"
                                                    title="Tiene cuenta de acceso al sistema"></span>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <h6 class="mb-0 text-truncate">{{ $persona->nombres }} {{ $persona->apellidos }}</h6>
                                            <small class="text-muted text-truncate d-block">
                                                {{ $persona->direccion ?: 'Sin dirección registrada' }}
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge bg-light text-body border fs-12 col-codigo">
                                        {{ $persona->carnet }}
                                    </span>
                                </td>

                                <td>
                                    @if ($persona->celular)
                                        <div class="d-flex align-items-center gap-1 text-body">
                                            <i class="ri-phone-line text-muted"></i> {{ $persona->celular }}
                                        </div>
                                    @endif
                                    @if ($persona->correo)
                                        <div class="d-flex align-items-center gap-1">
                                            <i class="ri-mail-line text-muted"></i>
                                            <a href="mailto:{{ $persona->correo }}"
                                                class="text-muted text-truncate">{{ $persona->correo }}</a>
                                        </div>
                                    @endif
                                    @unless ($persona->celular || $persona->correo)
                                        <span class="text-muted">—</span>
                                    @endunless
                                </td>

                                <td>
                                    @if ($persona->fecha_nacimiento)
                                        <div>{{ $persona->fecha_nacimiento->format('d/m/Y') }}</div>
                                        <small class="text-muted">{{ $persona->edad }} años</small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @can('personas.editar')
                                            <button type="button" class="btn btn-sm btn-ghost-primary btn-icon rounded-circle persona-accion-editar"
                                                wire:click="abrirEditar({{ $persona->id }})"
                                                title="Editar" aria-label="Editar a {{ $persona->nombres }}">
                                                <i class="ri-pencil-line fs-16"></i>
                                            </button>
                                        @endcan
                                        @can('personas.eliminar')
                                            @if (! $persona->user)
                                                <button type="button" class="btn btn-sm btn-ghost-danger btn-icon rounded-circle persona-accion-eliminar"
                                                    wire:click="confirmarEliminar({{ $persona->id }})"
                                                    title="Eliminar" aria-label="Eliminar a {{ $persona->nombres }}">
                                                    <i class="ri-delete-bin-line fs-16"></i>
                                                </button>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="text-center py-5">
                                        <div class="personas-empty-icon mx-auto mb-4">
                                            <span class="avatar-title rounded-circle fs-1">
                                                <i class="{{ $buscar !== '' ? 'ri-search-eye-line' : 'ri-user-add-line' }}"></i>
                                            </span>
                                        </div>
                                        @if ($buscar !== '')
                                            <h5 class="mb-1">Sin resultados para «{{ $buscar }}»</h5>
                                            <p class="text-muted mb-3">Revisa la ortografía o prueba con menos palabras.</p>
                                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                                wire:click="$set('buscar', '')">
                                                <i class="ri-close-line align-bottom me-1"></i> Limpiar búsqueda
                                            </button>
                                        @else
                                            <h5 class="mb-1">Todavía no hay personas registradas</h5>
                                            <p class="text-muted mb-3">Empieza agregando la primera persona al sistema.</p>
                                            @can('personas.crear')
                                                <button type="button" class="btn btn-success btn-sm" wire:click="abrirCrear">
                                                    <i class="ri-add-line align-bottom me-1"></i> Registrar persona
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($personas->total() > 0)
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando <span class="fw-semibold">{{ $personas->firstItem() }}</span>–<span
                            class="fw-semibold">{{ $personas->lastItem() }}</span>
                        de <span class="fw-semibold">{{ $personas->total() }}</span> personas
                    </p>

                    @if ($personas->hasPages())
                        <div class="paginacion-compacta">
                            {{ $personas->onEachSide(1)->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal registro / edición ===================== --}}
    <div class="modal fade" id="modalPersona" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-persona-dialog">
            <div class="modal-content border-0 modal-persona-content {{ $personaId ? 'modal-editar-persona' : 'modal-registrar-persona' }}">
                <div class="modal-header modal-persona-header p-4">
                    <div class="modal-persona-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-persona-icon rounded-circle fs-4">
                                <i class="{{ $personaId ? 'ri-pencil-line' : 'ri-user-add-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">
                                {{ $personaId ? 'Editar persona' : 'Registrar nueva persona' }}
                            </h5>
                            <small class="text-muted">
                                {{ $personaId ? 'Modifica los datos y guarda los cambios.' : 'Completa los campos obligatorios para habilitar el registro.' }}
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-persona-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-persona-body p-4">

                        <h6 class="modal-section-title mb-3"><i class="ri-profile-line"></i> Identificación</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="carnet" class="form-label">
                                    Carnet de identidad <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="ri-profile-line"></i></span>
                                    <input type="text" id="carnet" wire:model.live.debounce.400ms="carnet"
                                        class="form-control border-start-0 ps-0 @error('carnet') is-invalid @elseif ($carnet !== '') is-valid @enderror"
                                        placeholder="8123456" maxlength="11" inputmode="numeric">
                                    @error('carnet')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-8">
                                <label for="nombres" class="form-label">
                                    Nombres <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="nombres" wire:model.live.debounce.400ms="nombres"
                                    class="form-control @error('nombres') is-invalid @elseif ($nombres !== '') is-valid @enderror"
                                    placeholder="Juan Carlos" maxlength="100">
                                @error('nombres')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="apellido_paterno" class="form-label">
                                    Apellido paterno <span class="text-muted fw-normal fs-12">(al menos uno)</span>
                                </label>
                                <input type="text" id="apellido_paterno" wire:model.live.debounce.400ms="apellido_paterno"
                                    class="form-control @error('apellido_paterno') is-invalid @elseif ($apellido_paterno !== '') is-valid @enderror"
                                    placeholder="Rivas" maxlength="60">
                                @error('apellido_paterno')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="apellido_materno" class="form-label">
                                    Apellido materno <span class="text-muted fw-normal fs-12">(al menos uno)</span>
                                </label>
                                <input type="text" id="apellido_materno" wire:model.live.debounce.400ms="apellido_materno"
                                    class="form-control @error('apellido_materno') is-invalid @enderror"
                                    placeholder="Quispe" maxlength="60">
                                @error('apellido_materno')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="fecha_nacimiento" class="form-label">
                                    Fecha de nacimiento <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <input type="date" id="fecha_nacimiento" wire:model.live="fecha_nacimiento"
                                    max="{{ now()->subDay()->format('Y-m-d') }}"
                                    class="form-control @error('fecha_nacimiento') is-invalid @enderror">
                                @error('fecha_nacimiento')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <h6 class="modal-section-title mt-4 mb-3"><i class="ri-contacts-line"></i> Información de contacto</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="celular" class="form-label">
                                    Celular <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="ri-phone-line"></i></span>
                                    <input type="text" id="celular" wire:model.live.debounce.400ms="celular"
                                        class="form-control border-start-0 ps-0 @error('celular') is-invalid @enderror"
                                        placeholder="71234567" maxlength="8" inputmode="numeric">
                                    @error('celular')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="correo" class="form-label">
                                    Correo electrónico <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="ri-mail-line"></i></span>
                                    <input type="email" id="correo" wire:model.live.debounce.400ms="correo"
                                        class="form-control border-start-0 ps-0 @error('correo') is-invalid @enderror"
                                        placeholder="juan@correo.com">
                                    @error('correo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="direccion" class="form-label">
                                    Dirección <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <textarea id="direccion" rows="2" wire:model.live.debounce.400ms="direccion"
                                    class="form-control @error('direccion') is-invalid @enderror"
                                    placeholder="Av. Siempre Viva #742, Zona Central"></textarea>
                                @error('direccion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
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
                                <button type="submit" class="btn btn-success modal-guardar" @disabled(! $this->formularioValido)
                                    wire:loading.attr="disabled" wire:target="guardar">
                                    <span wire:loading.remove wire:target="guardar">
                                        <i class="ri-save-line align-bottom me-1"></i>
                                        {{ $personaId ? 'Guardar cambios' : 'Registrar' }}
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

    {{-- ===================== Modal confirmación de borrado ===================== --}}
    <div class="modal fade zoomIn" id="modalEliminarPersona" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1">
                            <i class="ri-delete-bin-line"></i>
                        </span>
                    </div>

                    <span class="modal-eliminar-etiqueta">Confirmación</span>
                    <h5 class="mb-2">¿Eliminar a esta persona?</h5>
                    <p class="text-muted mb-4">
                        Se quitará <strong class="modal-eliminar-nombre">{{ $eliminarNombre }}</strong> del listado.
                        Sus datos quedan archivados y no se pierden.
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
