<div class="proveedores-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-truck-line me-1"></i>
                            Compras · Proveedores
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-truck-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Proveedores</h4>
                                <p class="text-white-50 mb-0">
                                    De quién compras la mercadería. Cada compra parte de aquí.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('proveedores.crear')
                                <button type="button" class="btn btn-light crud-nueva-hero"
                                    wire:click="abrirCrear">
                                    <i class="ri-add-line align-bottom me-1"></i> Nuevo proveedor
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4 crud-kpis">
        <div class="col-xl-4 col-md-6">
            <x-stat-card label="Proveedores" value="{{ $totalProveedores }}" icon="bx-store"
                color="primary" caption="Registrados en el sistema" />
        </div>
        <div class="col-xl-4 col-md-6">
            <x-stat-card label="Activos" value="{{ $totalActivos }}" icon="bx-check-circle"
                color="success" caption="Disponibles al comprar" />
        </div>
        <div class="col-xl-4 col-md-6">
            <x-stat-card label="Con compras" value="{{ $conCompras }}" icon="bx-receipt"
                color="info" caption="Ya nos vendieron algo" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm crud-listado">
        <div class="card-header bg-transparent py-3 crud-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Proveedores registrados
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $proveedores->total() }}
                        {{ $proveedores->total() === 1 ? 'proveedor' : 'proveedores' }}
                        @if ($buscar !== '')
                            para «{{ $buscar }}»
                        @endif
                    </small>
                </div>

                <div class="col-md-5">
                    <div class="search-box">
                        <input type="text" class="form-control crud-busqueda"
                            placeholder="Buscar por nombre, NIT, contacto o teléfono..."
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

                <div class="col-md-3">
                    <select class="form-select" wire:model.live="filtroEstado">
                        <option value="todos">Todo estado</option>
                        <option value="activos">Solo activos</option>
                        <option value="inactivos">Solo inactivos</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-crud border-top"
                    wire:loading.class="opacity-50" wire:target="buscar, filtroEstado, ordenar, gotoPage, previousPage, nextPage">
                    <thead class="table-light">
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4" style="cursor:pointer" wire:click="ordenar('nombre')">
                                Proveedor
                                @if ($ordenarPor === 'nombre')
                                    <i class="ri-arrow-{{ $direccionOrden === 'asc' ? 'up' : 'down' }}-line align-middle"></i>
                                @endif
                            </th>
                            <th scope="col">NIT</th>
                            <th scope="col">Contacto</th>
                            <th scope="col" class="text-center">Compras</th>
                            <th scope="col" class="text-center">Estado</th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($proveedores as $proveedor)
                            <tr wire:key="proveedor-{{ $proveedor->id }}" class="{{ $proveedor->activo ? '' : 'fila-inactiva' }}">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-sm flex-shrink-0">
                                            <span class="avatar-title rounded-circle fw-semibold proveedor-avatar proveedor-avatar-{{ $proveedor->color_avatar }}">
                                                {{ $proveedor->iniciales }}
                                            </span>
                                        </div>
                                        <div class="min-w-0">
                                            <h6 class="mb-0 text-truncate">{{ $proveedor->nombre }}</h6>
                                            <small class="text-muted text-truncate d-block">
                                                {{ $proveedor->direccion ?: 'Sin dirección registrada' }}
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    @if ($proveedor->nit)
                                        <span class="badge bg-light text-body border fs-12 col-codigo">{{ $proveedor->nit }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($proveedor->contacto)
                                        <div class="text-truncate">{{ $proveedor->contacto }}</div>
                                    @endif
                                    @if ($proveedor->telefono)
                                        <small class="text-muted d-flex align-items-center gap-1">
                                            <i class="ri-phone-line"></i> {{ $proveedor->telefono }}
                                        </small>
                                    @endif
                                    @unless ($proveedor->contacto || $proveedor->telefono)
                                        <span class="text-muted">—</span>
                                    @endunless
                                </td>

                                <td class="text-center">
                                    <span class="proveedor-compras {{ $proveedor->compras_count > 0 ? 'proveedor-compras-contenido' : '' }}">
                                        <i class="ri-bill-line align-middle me-1"></i>{{ $proveedor->compras_count }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="proveedor-estado {{ $proveedor->activo ? 'proveedor-estado-activo' : 'proveedor-estado-inactivo' }}">
                                        <span class="proveedor-estado-dot"></span>
                                        {{ $proveedor->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @can('proveedores.ver')
                                            <a href="{{ route('proveedores.show', $proveedor) }}"
                                                class="btn btn-sm btn-ghost-info btn-icon rounded-circle crud-accion-ver"
                                                title="Ver ficha" aria-label="Ver la ficha de {{ $proveedor->nombre }}">
                                                <i class="ri-eye-line fs-16"></i>
                                            </a>
                                        @endcan
                                        @can('compras.crear')
                                            <a href="{{ route('compras.index') }}?proveedor={{ $proveedor->id }}"
                                                class="btn btn-sm btn-ghost-success btn-icon rounded-circle crud-accion-compra"
                                                title="Nueva compra con {{ $proveedor->nombre }}"
                                                aria-label="Crear compra para {{ $proveedor->nombre }}">
                                                <i class="ri-shopping-bag-3-line fs-16"></i>
                                            </a>
                                        @endcan
                                        @can('proveedores.editar')
                                            <button type="button"
                                                class="btn btn-sm btn-icon rounded-circle crud-accion-toggle {{ $proveedor->activo ? 'crud-accion-toggle-off' : 'crud-accion-toggle-on' }}"
                                                wire:click="alternarEstado({{ $proveedor->id }})"
                                                title="{{ $proveedor->activo ? 'Desactivar' : 'Activar' }}"
                                                aria-label="{{ $proveedor->activo ? 'Desactivar' : 'Activar' }} a {{ $proveedor->nombre }}">
                                                <i class="{{ $proveedor->activo ? 'ri-toggle-fill' : 'ri-toggle-line' }} fs-16"></i>
                                            </button>

                                            <button type="button" class="btn btn-sm btn-ghost-primary btn-icon rounded-circle crud-accion-editar"
                                                wire:click="abrirEditar({{ $proveedor->id }})" title="Editar"
                                                aria-label="Editar a {{ $proveedor->nombre }}">
                                                <i class="ri-pencil-line fs-16"></i>
                                            </button>
                                        @endcan
                                        @can('proveedores.eliminar')
                                            <button type="button" class="btn btn-sm btn-ghost-danger btn-icon rounded-circle crud-accion-eliminar"
                                                wire:click="confirmarEliminar({{ $proveedor->id }})" title="Eliminar"
                                                aria-label="Eliminar a {{ $proveedor->nombre }}">
                                                <i class="ri-delete-bin-line fs-16"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-4">
                                            <div class="avatar-title bg-light text-primary rounded-circle fs-1 shadow-sm">
                                                <i class="{{ $buscar !== '' ? 'ri-search-eye-line' : 'ri-truck-line' }}"></i>
                                            </div>
                                        </div>
                                        @if ($buscar !== '' || $filtroEstado !== 'todos')
                                            <h5 class="mb-1">Sin resultados</h5>
                                            <p class="text-muted mb-3">Prueba a quitar los filtros o cambiar la búsqueda.</p>
                                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                                wire:click="$set('buscar', ''); $set('filtroEstado', 'todos')">
                                                <i class="ri-close-line align-bottom me-1"></i> Limpiar filtros
                                            </button>
                                        @else
                                            <h5 class="mb-1 fw-semibold text-primary">Todavía no hay proveedores</h5>
                                            <p class="text-muted mb-3">Registra el primero para poder cargar compras.</p>
                                            @can('proveedores.crear')
                                                <button type="button" class="btn btn-success btn-sm rounded-pill shadow-sm" wire:click="abrirCrear">
                                                    <i class="ri-add-line align-bottom me-1"></i> Crear proveedor
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

        @if ($proveedores->hasPages())
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando {{ $proveedores->firstItem() }}-{{ $proveedores->lastItem() }} de {{ $proveedores->total() }}
                    </p>
                    <div class="crud-paginacion">
                        {{ $proveedores->onEachSide(1)->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal alta / edición ===================== --}}
    <div class="modal fade" id="modalProveedor" tabindex="-1" aria-hidden="true" wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-crud-dialog">
            <div class="modal-content border-0 modal-crud-content {{ $proveedorId ? 'modal-editar-crud' : '' }}">
                <div class="modal-header modal-crud-header p-4">
                    <div class="modal-crud-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-crud-icon rounded-circle fs-4">
                                <i class="{{ $proveedorId ? 'ri-pencil-line' : 'ri-truck-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">{{ $proveedorId ? 'Editar proveedor' : 'Nuevo proveedor' }}</h5>
                            <small class="text-muted">
                                {{ $proveedorId ? 'Modifica los datos y guarda los cambios.' : 'Solo el nombre es obligatorio.' }}
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-crud-body p-4">

                        <h6 class="crud-section-title mb-3"><i class="ri-building-line"></i> Datos de la empresa</h6>
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="p-name" class="form-label">Nombre o razón social <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-building-line"></i></span>
                                    <input type="text" id="p-name" wire:model.live.debounce.400ms="nombre"
                                        class="form-control border-start-0 ps-0 @error('nombre') is-invalid @elseif ($nombre !== '') is-valid @enderror"
                                        placeholder="Ej. Importadora Andina S.R.L." maxlength="150">
                                    @error('nombre')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label for="p-tax" class="form-label">
                                    NIT <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="ri-file-list-3-line"></i></span>
                                    <input type="text" id="p-tax" wire:model.live.debounce.400ms="nit"
                                        class="form-control border-start-0 ps-0 @error('nit') is-invalid @enderror"
                                        placeholder="1023456789" maxlength="30">
                                    @error('nit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="p-address" class="form-label">
                                    Dirección <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-map-pin-line"></i></span>
                                    <input type="text" id="p-address" wire:model.live.debounce.400ms="direccion"
                                        class="form-control border-start-0 ps-0 @error('direccion') is-invalid @enderror"
                                        placeholder="Av. Comercio #123, Zona Central" maxlength="255">
                                    @error('direccion')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <h6 class="crud-section-title mb-3"><i class="ri-contacts-line"></i> Persona de contacto</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="p-contact" class="form-label">
                                    Nombre <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-user-line"></i></span>
                                    <input type="text" id="p-contact" wire:model.live.debounce.400ms="contacto"
                                        class="form-control border-start-0 ps-0 @error('contacto') is-invalid @enderror"
                                        placeholder="Ej. Carlos Mendoza" maxlength="120">
                                    @error('contacto')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="p-phone" class="form-label">
                                    Teléfono <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="ri-phone-line"></i></span>
                                    <input type="text" id="p-phone" wire:model.live.debounce.400ms="telefono"
                                        class="form-control border-start-0 ps-0 @error('telefono') is-invalid @enderror"
                                        placeholder="71234567" maxlength="30">
                                    @error('telefono')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="p-email" class="form-label">
                                    Correo <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="ri-mail-line"></i></span>
                                    <input type="correo" id="p-email" wire:model.live.debounce.400ms="correo"
                                        class="form-control border-start-0 ps-0 @error('correo') is-invalid @enderror"
                                        placeholder="ventas@proveedor.com" maxlength="150">
                                    @error('correo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="p-notes" class="form-label">
                                    Notas <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 align-items-start"><i
                                            class="ri-sticky-note-line"></i></span>
                                    <textarea id="p-notes" rows="2" wire:model.live.debounce.400ms="notas"
                                        class="form-control border-start-0 @error('notas') is-invalid @enderror"
                                        placeholder="Condiciones de pago, plazos de entrega, observaciones..."></textarea>
                                    @error('notas')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch form-switch-lg">
                                    <input class="form-check-input" type="checkbox" role="switch" id="p-activo"
                                        wire:model.live="activo">
                                    <label class="form-check-label" for="p-activo">
                                        Proveedor activo
                                        <small class="d-block text-muted">Si se desactiva, no aparecerá al registrar compras nuevas.</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer modal-crud-footer p-4">
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
                                        {{ $proveedorId ? 'Guardar cambios' : 'Crear proveedor' }}
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
    <div class="modal fade zoomIn" id="modalEliminarProveedor" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 shadow-lg modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4 {{ $eliminarCompras > 0 ? 'modal-eliminar-icon-aviso' : '' }}">
                        <span class="avatar-title rounded-circle fs-1"><i class="{{ $eliminarCompras > 0 ? 'ri-lock-line' : 'ri-delete-bin-line' }}"></i></span>
                    </div>

                    @if ($eliminarCompras > 0)
                        {{-- No se puede borrar: el histórico de costos quedaría sin origen. --}}
                        <h5 class="mb-2">Este proveedor tiene historial</h5>
                        <p class="text-muted mb-4">
                            <strong class="modal-eliminar-nombre">{{ $eliminarNombre }}</strong> tiene
                            <strong>{{ $eliminarCompras }}</strong> {{ $eliminarCompras === 1 ? 'compra registrada' : 'compras registradas' }}.
                            Borrarlo dejaría sin origen el costo de las unidades que trajo.
                            <span class="d-block mt-2">Desactívalo para que no aparezca en compras nuevas.</span>
                        </p>
                        <button type="button" class="btn btn-light w-100" data-bs-dismiss="modal">Entendido</button>
                    @else
                        <h5 class="mb-2">¿Eliminar este proveedor?</h5>
                        <p class="text-muted mb-4">
                            Se quitará <strong class="modal-eliminar-nombre">{{ $eliminarNombre }}</strong> de la lista.
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
