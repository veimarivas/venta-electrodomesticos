<div class="marcas-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-store-2-line me-1"></i>
                            Catálogo · Marcas
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-store-2-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Marcas</h4>
                                <p class="text-white-50 mb-0">
                                    El catálogo se vende por marca: registra las que trabajas.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('marcas.crear')
                                <button type="button" class="btn btn-light crud-nueva-hero" wire:click="abrirCrear">
                                    <i class="ri-add-line align-bottom me-1"></i> Nueva marca
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
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Marcas" value="{{ $totalMarcas }}" icon="bx-store"
                color="primary" caption="Total registradas" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Activas" value="{{ $activas }}" icon="bx-check-circle"
                color="success" caption="Visibles en el catálogo" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Inactivas" value="{{ $inactivas }}" icon="bx-hide"
                color="danger" caption="Ocultas temporalmente" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Productos" value="{{ $totalProductos }}" icon="bx-package"
                color="info" caption="Con esta marca o sin ella" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm crud-listado marcas-listado">
        <div class="card-header bg-transparent py-3 crud-toolbar marcas-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Marcas registradas
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $marcas->total() }}
                        {{ $marcas->total() === 1 ? 'marca' : 'marcas' }}
                        @if ($buscar !== '')
                            para «{{ $buscar }}»
                        @endif
                    </small>
                </div>

                <div class="col-md-8">
                    <div class="search-box">
                        <input type="text" class="form-control crud-busqueda"
                            placeholder="Buscar marca por nombre..." wire:model.live.debounce.400ms="buscar">
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
                <table class="table table-hover align-middle mb-0 tabla-crud tabla-marcas border-top"
                    wire:loading.class="opacity-50" wire:target="buscar">
                    <thead class="table-light">
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4">Logo</th>
                            <th scope="col" class="categorizable" style="cursor:pointer" wire:click="ordenar('nombre')">
                                Marca
                                @if ($ordenarPor === 'nombre')
                                    <i class="ri-arrow-{{ $direccionOrden === 'asc' ? 'up' : 'down' }}-line align-middle"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-center">Productos</th>
                            <th scope="col" class="text-center">Estado</th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($marcas as $marca)
                            <tr wire:key="marca-{{ $marca->id }}" class="marca-fila">
                                <td class="ps-4">
                                    <div class="marca-logo">
                                        @if ($marca->logo_ruta)
                                            <img src="{{ asset('storage/'.$marca->logo_ruta) }}" alt="Logo de {{ $marca->nombre }}" class="img-fluid object-fit-contain w-100 h-100">
                                        @else
                                            <span class="avatar-title rounded-3 marca-logo-sin-imagen"><i class="ri-building-4-line"></i></span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <h6 class="mb-0 marca-nombre">{{ $marca->nombre }}</h6>
                                    <span class="text-muted fs-12">{{ $marca->slug }}</span>
                                </td>

                                <td class="text-center">
                                    <span class="marca-productos {{ $marca->productos_count > 0 ? 'marca-productos-contenido' : '' }}">
                                        <i class="ri-box-3-line align-middle me-1"></i>{{ $marca->productos_count }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="marca-estado {{ $marca->activa ? 'marca-estado-activa' : 'marca-estado-inactiva' }}">
                                        <span class="marca-estado-dot"></span>
                                        {{ $marca->activa ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @can('marcas.editar')
                                            <button type="button" class="btn btn-sm btn-ghost-primary btn-icon rounded-circle crud-accion-editar"
                                                wire:click="abrirEditar({{ $marca->id }})" title="Editar" aria-label="Editar a {{ $marca->nombre }}">
                                                <i class="ri-pencil-line fs-16"></i>
                                            </button>
                                        @endcan
                                        @can('marcas.eliminar')
                                            <button type="button" class="btn btn-sm btn-ghost-danger btn-icon rounded-circle crud-accion-eliminar"
                                                wire:click="confirmarEliminar({{ $marca->id }})" title="Eliminar" aria-label="Eliminar a {{ $marca->nombre }}">
                                                <i class="ri-delete-bin-line fs-16"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-4">
                                            <div class="avatar-title bg-light text-primary rounded-circle fs-1 shadow-sm">
                                                <i class="{{ $buscar !== '' ? 'ri-search-eye-line' : 'ri-store-2-line' }}"></i>
                                            </div>
                                        </div>
                                        @if ($buscar !== '')
                                            <h5 class="mb-1">Sin resultados para «{{ $buscar }}»</h5>
                                            <p class="text-muted mb-3">Revisa la ortografía o prueba con menos palabras.</p>
                                            <button type="button" class="btn btn-soft-secondary btn-sm" wire:click="$set('buscar', '')">
                                                <i class="ri-close-line align-bottom me-1"></i> Limpiar búsqueda
                                            </button>
                                        @else
                                            <h5 class="mb-1 fw-semibold text-primary">Todavía no hay marcas</h5>
                                            <p class="text-muted mb-3">Registra la primera para empezar a etiquetar productos.</p>
                                            @can('marcas.crear')
                                                <button type="button" class="btn btn-success btn-sm rounded-pill shadow-sm" wire:click="abrirCrear">
                                                    <i class="ri-add-line align-bottom me-1"></i> Crear marca
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

        @if ($marcas->hasPages())
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando {{ $marcas->firstItem() }}-{{ $marcas->lastItem() }} de {{ $marcas->total() }}
                    </p>
                    <div class="crud-paginacion">
                        {{ $marcas->onEachSide(1)->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal registro / edición ===================== --}}
    <div class="modal fade" id="modalMarca" tabindex="-1" aria-hidden="true" wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-crud-dialog">
            <div class="modal-content border-0 modal-crud-content {{ $marcaId ? 'modal-editar-crud' : '' }}">
                <div class="modal-header modal-crud-header p-4">
                    <div class="modal-crud-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-crud-icon rounded-circle fs-4">
                                <i class="{{ $marcaId ? 'ri-pencil-line' : 'ri-trademark-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">{{ $marcaId ? 'Editar marca' : 'Nueva marca' }}</h5>
                            <small class="text-muted">
                                {{ $marcaId ? 'Modifica los datos y guarda los cambios.' : 'Registra una marca para el catálogo.' }}
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-crud-body p-4">
                        <h6 class="crud-section-title mb-3"><i class="ri-trademark-line"></i> Datos de la marca</h6>
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="nombre" class="form-label">
                                    Nombre <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-building-line"></i></span>
                                    <input type="text" id="nombre" wire:model.live.debounce.400ms="nombre"
                                        class="form-control border-start-0 ps-0 @error('nombre') is-invalid @elseif ($nombre !== '') is-valid @enderror"
                                        placeholder="Ej. Samsung" maxlength="100">
                                    @error('nombre')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label for="slug" class="form-label">
                                    Slug <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="ri-link"></i></span>
                                    <input type="text" id="slug" wire:model.live.debounce.400ms="slug"
                                        class="form-control border-start-0 ps-0 @error('slug') is-invalid @elseif ($slug !== '') is-valid @enderror"
                                        placeholder="samsung" maxlength="120">
                                    @error('slug')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-text">Se genera solo desde el nombre. Escríbelo para personalizarlo.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Logo <span class="text-muted fw-normal fs-12">(opcional)</span></label>
                                <div class="crud-upload">
                                    <div class="crud-upload-preview">
                                        @if ($logo)
                                            <img src="{{ $logo->temporaryUrl() }}" alt="Nuevo logo">
                                        @elseif ($logoActual !== '')
                                            <img src="{{ asset('storage/'.$logoActual) }}" alt="Logo actual">
                                        @else
                                            <span class="avatar-title"><i class="ri-building-4-line"></i></span>
                                        @endif
                                    </div>

                                    <div class="flex-grow-1 min-w-0">
                                        <input type="file" wire:model="logo" accept="image/jpeg,image/png,image/webp"
                                            class="form-control @error('logo') is-invalid @enderror">
                                        @error('logo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div wire:loading wire:target="logo" class="form-text text-primary">
                                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                            Subiendo logo...
                                        </div>
                                        <div class="form-text">JPG, PNG o WebP de hasta 2 MB.</div>
                                    </div>

                                    @if ($logoActual !== '' && ! $logo)
                                        <div class="form-check">
                                            <input type="checkbox" id="quitar_logo" class="form-check-input" wire:model.live="quitarLogo">
                                            <label for="quitar_logo" class="form-check-label text-danger">Quitar</label>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <div class="form-check form-switch form-switch-lg">
                                    <input type="checkbox" id="activo" class="form-check-input" wire:model.live="isActive">
                                    <label for="activo" class="form-check-label">Marca activa</label>
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
                                        {{ $marcaId ? 'Guardar cambios' : 'Crear marca' }}
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
    <div class="modal fade zoomIn" id="modalEliminarMarca" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 shadow-lg modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1"><i class="ri-delete-bin-line"></i></span>
                    </div>

                    <h5 class="mb-2">¿Eliminar esta marca?</h5>
                    <p class="text-muted mb-4">
                        Se quitará <strong class="modal-eliminar-nombre">{{ $eliminarNombre }}</strong> del catálogo.
                        @if ($eliminarProductos > 0)
                            <span class="d-block mt-1 text-danger">
                                <i class="ri-error-warning-line align-bottom me-1"></i>
                                {{ $eliminarProductos }} producto(s) la usan: primero muévelos o elimínalos.
                            </span>
                        @endif
                    </p>

                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Cancelar</button>
                        @if ($eliminarProductos === 0)
                            <button type="button" class="btn btn-danger modal-eliminar-btn w-100" wire:click="eliminar"
                                wire:loading.attr="disabled" wire:target="eliminar">
                                <span wire:loading.remove wire:target="eliminar">Sí, eliminar</span>
                                <span wire:loading wire:target="eliminar">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Eliminando...
                                </span>
                            </button>
                        @else
                            <button type="button" class="btn btn-light w-100" data-bs-dismiss="modal">Entendido</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
