{{--
    "personas-modulo" no es un descuido: en _personas.scss viven los estilos
    compartidos por los módulos de este tipo (encabezado, tabla, modales,
    paginación). Se reutilizan tal cual para no duplicar CSS; "cargos-modulo"
    queda disponible para los ajustes propios de esta pantalla.
--}}
<div class="personas-modulo cargos-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 personas-encabezado">
        <div class="card-body p-0">
            <div class="p-4 personas-hero">
                <div class="personas-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 personas-chip">
                            <i class="ri-briefcase-line me-1"></i> Estructura del personal
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title personas-tile text-white rounded-3 fs-3">
                                    <i class="ri-briefcase-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Cargos</h4>
                                <p class="text-white-50 mb-0">
                                    Los puestos que se pueden asignar a los trabajadores de la tienda.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('cargos.crear')
                                <button type="button" class="btn btn-light personas-nueva-hero" wire:click="abrirCrear">
                                    <i class="ri-add-line align-bottom me-1"></i> Nuevo cargo
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
        <div class="col-xl-4 col-md-6">
            <x-stat-card label="Cargos registrados" value="{{ $totalCargos }}" icon="bx-briefcase"
                color="primary" caption="Puestos disponibles" />
        </div>
        <div class="col-xl-4 col-md-6">
            <x-stat-card label="Cargos en uso" value="{{ $cargosEnUso }}" icon="bx-check-circle"
                color="success" caption="Con al menos un trabajador" />
        </div>
        <div class="col-xl-4 col-md-6">
            <x-stat-card label="Trabajadores asignados" value="{{ $totalAsignaciones }}" icon="bx-user-check"
                color="info" caption="Total en la tienda" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm personas-listado">
        <div class="card-header bg-transparent py-3 personas-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Listado de cargos
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $cargos->total() }} {{ $cargos->total() === 1 ? 'cargo encontrado' : 'cargos encontrados' }}
                    </small>
                </div>

                <div class="col-md-8">
                    <div class="search-box">
                        <input type="text" class="form-control personas-busqueda"
                            placeholder="Buscar cargo por nombre..." wire:model.live.debounce.400ms="buscar">
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
                <table class="table table-hover align-middle mb-0 tabla-personas"
                    wire:loading.class="opacity-50" wire:target="buscar, ordenar, gotoPage, previousPage, nextPage">
                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4" role="button" wire:click="ordenar('nombre')">
                                Cargo <x-sort-icon :field="'nombre'" :current="$ordenarPor" :direction="$direccionOrden" />
                            </th>
                            <th scope="col">Trabajadores</th>
                            <th scope="col" role="button" wire:click="ordenar('created_at')">
                                Creado <x-sort-icon :field="'created_at'" :current="$ordenarPor" :direction="$direccionOrden" />
                            </th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cargos as $cargo)
                            <tr wire:key="cargo-{{ $cargo->id }}">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-xs flex-shrink-0">
                                            <span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-16">
                                                <i class="ri-briefcase-line"></i>
                                            </span>
                                        </div>
                                        <div class="min-w-0">
                                            <h6 class="mb-0 text-truncate">{{ $cargo->nombre }}</h6>
                                            <small class="text-muted">
                                                @if ($cargo->trabajadores_activos > 0)
                                                    Cargo en uso
                                                @elseif ($cargo->trabajadores_total > 0)
                                                    Solo referenciado por trabajadores dados de baja
                                                @else
                                                    Sin trabajadores asignados
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    @if ($cargo->trabajadores_activos > 0)
                                        <span class="badge bg-success-subtle text-success fs-12">
                                            <i class="ri-team-line align-bottom me-1"></i>
                                            {{ $cargo->trabajadores_activos }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-muted border fs-12">0</span>
                                    @endif
                                </td>

                                <td>
                                    <span class="text-muted">{{ $cargo->created_at?->format('d/m/Y') ?? '—' }}</span>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @can('cargos.editar')
                                            <button type="button"
                                                class="btn btn-sm btn-ghost-primary btn-icon rounded-circle persona-accion-editar"
                                                wire:click="abrirEditar({{ $cargo->id }})"
                                                title="Editar" aria-label="Editar el cargo {{ $cargo->nombre }}">
                                                <i class="ri-pencil-line fs-16"></i>
                                            </button>
                                        @endcan
                                        @can('cargos.eliminar')
                                            <button type="button"
                                                class="btn btn-sm btn-ghost-danger btn-icon rounded-circle persona-accion-eliminar"
                                                wire:click="confirmarEliminar({{ $cargo->id }})"
                                                title="Eliminar" aria-label="Eliminar el cargo {{ $cargo->nombre }}">
                                                <i class="ri-delete-bin-line fs-16"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">
                                    <div class="text-center py-5">
                                        <div class="personas-empty-icon mx-auto mb-4">
                                            <span class="avatar-title rounded-circle fs-1">
                                                <i class="{{ $buscar !== '' ? 'ri-search-eye-line' : 'ri-briefcase-line' }}"></i>
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
                                            <h5 class="mb-1">Todavía no hay cargos registrados</h5>
                                            <p class="text-muted mb-3">
                                                Los cargos son necesarios para poder registrar trabajadores.
                                            </p>
                                            @can('cargos.crear')
                                                <button type="button" class="btn btn-success btn-sm" wire:click="abrirCrear">
                                                    <i class="ri-add-line align-bottom me-1"></i> Registrar cargo
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

        @if ($cargos->total() > 0)
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando <span class="fw-semibold">{{ $cargos->firstItem() }}</span>–<span
                            class="fw-semibold">{{ $cargos->lastItem() }}</span>
                        de <span class="fw-semibold">{{ $cargos->total() }}</span> cargos
                    </p>

                    @if ($cargos->hasPages())
                        <div class="paginacion-compacta">
                            {{ $cargos->onEachSide(1)->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal registro / edición ===================== --}}
    <div class="modal fade" id="modalCargo" tabindex="-1" aria-hidden="true" wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-persona-dialog">
            <div class="modal-content border-0 modal-persona-content {{ $cargoId ? 'modal-editar-persona' : '' }}">
                <div class="modal-header modal-persona-header p-4">
                    <div class="modal-persona-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-persona-icon rounded-circle fs-4">
                                <i class="{{ $cargoId ? 'ri-pencil-line' : 'ri-briefcase-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">
                                {{ $cargoId ? 'Editar cargo' : 'Registrar nuevo cargo' }}
                            </h5>
                            <small>
                                {{ $cargoId ? 'Modifica el nombre y guarda los cambios.' : 'Escribe el nombre del puesto de trabajo.' }}
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-persona-close" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-persona-body p-4">
                        <h6 class="modal-section-title mb-3"><i class="ri-briefcase-line"></i> Datos del cargo</h6>

                        <div class="row g-3">
                            <div class="col-12">
                                <label for="nombre" class="form-label">
                                    Nombre del cargo <span class="text-danger">*</span>
                                </label>
                                <input type="text" id="nombre" wire:model.live.debounce.400ms="nombre"
                                    class="form-control @error('nombre') is-invalid @elseif ($nombre !== '') is-valid @enderror"
                                    placeholder="Ej. Encargado de almacén" maxlength="80">
                                @error('nombre')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">
                                    Este nombre aparecerá al asignar el cargo a un trabajador.
                                </small>
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
                                <button type="submit" class="btn btn-success modal-guardar"
                                    @disabled(! $this->formularioValido) wire:loading.attr="disabled" wire:target="guardar">
                                    <span wire:loading.remove wire:target="guardar">
                                        <i class="ri-save-line align-bottom me-1"></i>
                                        {{ $cargoId ? 'Guardar cambios' : 'Registrar' }}
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
    <div class="modal fade zoomIn" id="modalEliminarCargo" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1">
                            <i class="{{ $eliminarTrabajadoresTotal > 0 ? 'ri-lock-line' : 'ri-delete-bin-line' }}"></i>
                        </span>
                    </div>

                    <span class="modal-eliminar-etiqueta">Confirmación</span>
                    @if ($eliminarTrabajadoresTotal > 0)
                        {{-- Bloqueado: la FK es restrictOnDelete y cuenta también a los
                             trabajadores dados de baja, así que se avisa antes en lugar
                             de dejar que falle la base de datos. --}}
                        <h5 class="mb-2">Este cargo está en uso</h5>
                        <p class="text-muted mb-4">
                            <strong class="modal-eliminar-nombre">{{ $eliminarNombre }}</strong> está asignado a
                            <strong>{{ $eliminarTrabajadoresTotal }}</strong>
                            {{ $eliminarTrabajadoresTotal === 1 ? 'trabajador' : 'trabajadores' }}
                            {{ $eliminarTrabajadoresActivos > 0 && $eliminarTrabajadoresActivos < $eliminarTrabajadoresTotal ? '(incluidos los dados de baja)' : '' }}.
                            Cámbiales el cargo antes de eliminarlo.
                        </p>
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">
                            Entendido
                        </button>
                    @else
                        <h5 class="mb-2">¿Eliminar este cargo?</h5>
                        <p class="text-muted mb-4">
                            Se quitará <strong class="modal-eliminar-nombre">{{ $eliminarNombre }}</strong> de la lista
                            de puestos disponibles.
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
