<div class="categorias-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 categorias-encabezado">
        <div class="card-body p-0">
            <div class="p-4 categorias-hero">
                <div class="categorias-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title categorias-tile text-white rounded-3 fs-3">
                                    <i class="ri-archive-drawer-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Categorías</h4>
                                <p class="text-white-50 mb-0">
                                    Organiza el catálogo en una jerarquía de hasta los niveles que necesites.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('categorias.crear')
                                <button type="button" class="btn btn-light categorias-nueva-hero" wire:click="abrirCrear">
                                    <i class="ri-add-line align-bottom me-1"></i> Nueva categoría
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4 categorias-kpis">
        <div class="col-xl-4 col-md-4">
            <x-stat-card label="Categorías" value="{{ $totalCategorias }}" icon="bx-category" color="primary"
                caption="Total en el sistema" />
        </div>
        <div class="col-xl-4 col-md-4">
            <x-stat-card label="Activas" value="{{ $activas }}" icon="bx-check-circle" color="success"
                caption="Visibles en el catálogo" />
        </div>
        <div class="col-xl-4 col-md-4">
            <x-stat-card label="Inactivas" value="{{ $inactivas }}" icon="bx-hide" color="danger"
                caption="Ocultas temporalmente" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm categorias-listado">
        <div class="card-header bg-transparent py-3 categorias-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Estructura de categorías
                        {{-- Indicador de carga en línea: nunca cubre la tabla,
                             así que jamás puede bloquear los botones de acción. --}}
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        @if ($buscar === '')
                            {{ $totalCategorias }}
                            {{ $totalCategorias === 1 ? 'categoría en el catálogo' : 'categorías en el catálogo' }}
                        @else
                            {{ count($filas) }}
                            {{ count($filas) === 1 ? 'resultado' : 'resultados' }} para «{{ $buscar }}»
                        @endif
                    </small>
                    @if ($puedeOrdenar && $totalCategorias > 1)
                        <small class="text-muted fs-12 d-block categorias-pista-arrastre">
                            <i class="ri-drag-move-2-line align-middle me-1"></i>
                            Arrastra el asa de una fila: suéltala <strong>sobre</strong> otra categoría
                            para anidarla, o entre dos filas para cambiar su orden.
                        </small>
                    @endif
                </div>

                <div class="col-md-8">
                    <div class="search-box">
                        <input type="text" class="form-control categorias-busqueda"
                            placeholder="Buscar categoría por nombre o descripción..."
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
            <div class="overflow-hidden">
                <table class="table table-hover align-middle mb-0 tabla-categorias"
                    @if ($puedeOrdenar) data-categorias-ordenables @endif wire:loading.class="opacity-50"
                    wire:target="buscar,moverCategoria">
                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4">Categoría</th>
                            <th scope="col">Slug</th>
                            <th scope="col">Descripción</th>
                            <th scope="col" class="text-center">Estado</th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            // Paleta estable por ID: cada categoría conserva su color entre recargas.
                            // Los tonos se escogieron con contraste suficiente sobre sus fondos claros.
                            $paletaCategorias = [
                                ['color' => '#405189', 'suave' => '#e2e5ed', 'texto' => '#364574'],
                                ['color' => '#087f73', 'suave' => '#d7f3f0', 'texto' => '#075f56'],
                                ['color' => '#1b77a5', 'suave' => '#dff0fa', 'texto' => '#155d80'],
                                ['color' => '#9a6700', 'suave' => '#fef4e4', 'texto' => '#785000'],
                                ['color' => '#5b4bb7', 'suave' => '#e9e5ff', 'texto' => '#463991'],
                                ['color' => '#b03e72', 'suave' => '#fce6ef', 'texto' => '#862d57'],
                            ];
                        @endphp
                        @forelse ($filas as $fila)
                            @php
                                $categoria = $fila['categoria'];
                                $tonoCategoria = $paletaCategorias[($categoria->id - 1) % count($paletaCategorias)];
                            @endphp
                            <tr wire:key="categoria-{{ $categoria->id }}" class="categoria-fila-color"
                                data-categoria-id="{{ $categoria->id }}" data-padre-id="{{ $categoria->padre_id }}"
                                data-profundidad="{{ $fila['profundidad'] }}"
                                style="--categoria-color: {{ $tonoCategoria['color'] }}; --categoria-suave: {{ $tonoCategoria['suave'] }}; --categoria-texto: {{ $tonoCategoria['texto'] }};">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3 categorias-fila"
                                        style="--nivel: {{ $fila['profundidad'] }}">
                                        @if ($puedeOrdenar)
                                            {{-- El asa es lo único arrastrable: así la fila sigue
                                                 permitiendo seleccionar texto y pulsar los botones. --}}
                                            <span class="categoria-asa flex-shrink-0" draggable="true"
                                                data-categoria-asa role="button" tabindex="-1"
                                                title="Arrastra para mover {{ $categoria->nombre }}"
                                                aria-label="Arrastrar {{ $categoria->nombre }} para reubicarla">
                                                <i class="ri-drag-move-2-line"></i>
                                            </span>
                                        @endif
                                        <div class="avatar-xs flex-shrink-0 categorias-icono">
                                            <span
                                                class="avatar-title categoria-identidad rounded {{ $categoria->activo ? '' : 'categoria-identidad-inactiva' }}"
                                                title="Color identificador de {{ $categoria->nombre }}">
                                                <i
                                                    class="{{ $fila['tieneHijos'] ? 'ri-folder-2-line' : 'ri-folder-line' }}"></i>
                                            </span>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="d-flex align-items-center gap-2 min-w-0">
                                                <h6 class="mb-0 text-truncate">{{ $categoria->nombre }}</h6>
                                                @if ($fila['numProductos'] > 0)
                                                    <span class="categoria-productos-chip flex-shrink-0"
                                                        title="{{ $fila['numProductos'] }} {{ $fila['numProductos'] === 1 ? 'producto' : 'productos' }}">
                                                        <i class="ri-price-tag-3-line"></i>
                                                        {{ $fila['numProductos'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if (isset($fila['ruta']) && $fila['ruta'] !== '')
                                                <small class="text-muted text-truncate d-block">
                                                    <i class="ri-arrow-right-up-line align-middle me-1"></i>
                                                    En {{ $fila['ruta'] }}
                                                </small>
                                            @elseif ($fila['numHijos'] > 0)
                                                <small class="text-muted">
                                                    <i class="ri-corner-down-right-line align-middle me-1"></i>
                                                    {{ $fila['numHijos'] }}
                                                    {{ $fila['numHijos'] === 1 ? 'subcategoría' : 'subcategorías' }}
                                                </small>
                                            @elseif ($fila['numProductos'] > 0)
                                                <small class="text-muted">
                                                    <i class="ri-arrow-right-s-line align-middle me-1"></i>
                                                    Categoría directa
                                                </small>
                                            @else
                                                <small class="text-muted text-truncate d-block">
                                                    Categoría sin productos
                                                </small>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <code class="fs-12">{{ $categoria->slug }}</code>
                                </td>

                                <td>
                                    @if ($categoria->descripcion)
                                        <span class="text-muted text-truncate d-block">
                                            {{ $categoria->descripcion }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <span
                                        class="categoria-estado {{ $categoria->activo ? 'categoria-estado-activa' : 'categoria-estado-inactiva' }}">
                                        <span class="categoria-estado-dot" aria-hidden="true"></span>
                                        {{ $categoria->activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @can('categorias.crear')
                                            <button type="button"
                                                class="btn btn-sm btn-ghost-success btn-icon rounded-circle categoria-accion-hijo"
                                                wire:click="abrirCrearHijo({{ $categoria->id }})"
                                                title="Agregar subcategoría"
                                                aria-label="Agregar subcategoría en {{ $categoria->nombre }}">
                                                <i class="ri-add-line fs-16"></i>
                                            </button>
                                        @endcan
                                        @can('productos.ver')
                                            <button type="button" wire:click="verProductos({{ $categoria->id }})"
                                                class="btn btn-sm btn-ghost-info btn-icon rounded-circle categoria-accion-ver"
                                                title="Ver productos"
                                                aria-label="Ver productos de {{ $categoria->nombre }}">
                                                <i class="ri-eye-line fs-16"></i>
                                            </button>
                                        @endcan
                                        @can('categorias.editar')
                                            <button type="button"
                                                class="btn btn-sm btn-ghost-primary btn-icon rounded-circle categoria-accion-editar"
                                                wire:click="abrirEditar({{ $categoria->id }})" title="Editar"
                                                aria-label="Editar a {{ $categoria->nombre }}">
                                                <i class="ri-pencil-line fs-16"></i>
                                            </button>
                                        @endcan
                                        @can('categorias.eliminar')
                                            <button type="button"
                                                class="btn btn-sm btn-ghost-danger btn-icon rounded-circle categoria-accion-eliminar"
                                                wire:click="confirmarEliminar({{ $categoria->id }})" title="Eliminar"
                                                aria-label="Eliminar a {{ $categoria->nombre }}">
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
                                        <div class="categorias-empty-icon mx-auto mb-4">
                                            <span class="avatar-title rounded-circle fs-1">
                                                <i
                                                    class="{{ $buscar !== '' ? 'ri-search-eye-line' : 'ri-folder-add-line' }}"></i>
                                            </span>
                                        </div>
                                        @if ($buscar !== '')
                                            <h5 class="mb-1">Sin resultados para «{{ $buscar }}»</h5>
                                            <p class="text-muted mb-3">Revisa la ortografía o prueba con menos
                                                palabras.</p>
                                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                                wire:click="$set('buscar', '')">
                                                <i class="ri-close-line align-bottom me-1"></i> Limpiar búsqueda
                                            </button>
                                        @else
                                            <h5 class="mb-1">Todavía no hay categorías</h5>
                                            <p class="text-muted mb-3">Crea la primera para empezar a organizar el
                                                catálogo.</p>
                                            @can('categorias.crear')
                                                <button type="button" class="btn btn-success btn-sm"
                                                    wire:click="abrirCrear">
                                                    <i class="ri-add-line align-bottom me-1"></i> Crear categoría
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

        @if ($totalCategorias > 0)
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        {{ $totalCategorias }} categorías ·
                        <span class="fw-semibold text-success">{{ $activas }}</span> activas ·
                        <span class="fw-semibold text-muted">{{ $inactivas }}</span> inactivas
                    </p>

                    @if ($buscar !== '')
                        <button type="button" class="btn btn-soft-secondary btn-sm" wire:click="$set('buscar', '')">
                            <i class="ri-close-line align-bottom me-1"></i> Limpiar búsqueda
                        </button>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal registro / edición ===================== --}}
    <div class="modal fade" id="modalCategoria" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-categoria-dialog">
            <div
                class="modal-content border-0 modal-categoria-content {{ $categoriaId ? 'modal-editar-categoria' : 'modal-registrar-categoria' }}">
                <div class="modal-header modal-categoria-header p-4">
                    <div class="modal-categoria-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-categoria-icon rounded-circle fs-4">
                                <i class="{{ $categoriaId ? 'ri-pencil-line' : 'ri-price-tag-3-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">
                                {{ $categoriaId ? 'Editar categoría' : 'Nueva categoría' }}
                            </h5>
                            <small class="text-muted">
                                {{ $categoriaId ? 'Modifica los datos y guarda los cambios.' : 'Crea una categoría para organizar el catálogo.' }}
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-categoria-close" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-categoria-body p-4">

                        <h6 class="modal-section-title mb-3"><i class="ri-price-tag-3-line"></i> Datos de la categoría
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="nombre" class="form-label">
                                    Nombre <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-price-tag-3-line"></i></span>
                                    <input type="text" id="nombre" wire:model.live.debounce.400ms="nombre"
                                        class="form-control border-start-0 ps-0 @error('nombre') is-invalid @elseif ($nombre !== '') is-valid @enderror"
                                        placeholder="Ej. Televisores" maxlength="100">
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
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-link"></i></span>
                                    <input type="text" id="slug" wire:model.live.debounce.400ms="slug"
                                        class="form-control border-start-0 ps-0 @error('slug') is-invalid @elseif ($slug !== '') is-valid @enderror"
                                        placeholder="televisores" maxlength="120">
                                    @error('slug')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-text">Se genera solo desde el nombre. Escríbelo para personalizarlo.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="padre_id" class="form-label">
                                    Categoría padre <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-node-tree"></i></span>
                                    <select id="padre_id" wire:model.live="padreId"
                                        class="form-select border-start-0 @error('padreId') is-invalid @enderror"
                                        {{ $padreForzado ? 'disabled' : '' }}>
                                        <option value="">— Sin padre (categoría raíz) —</option>
                                        @foreach ($opcionesPadre as $opcion)
                                            <option value="{{ $opcion['id'] }}">
                                                {{ str_repeat('— ', $opcion['nivel']) }}{{ $opcion['nombre'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('padreId')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                @if ($padreForzado)
                                    <small class="text-muted"><i class="ri-information-line align-bottom me-1"></i> Creando subcategoría de la categoría seleccionada.</small>
                                @endif
                            </div>

                            <div class="col-md-3">
                                <label for="posicion" class="form-label">
                                    Posición <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-sort-number-asc"></i></span>
                                    <input type="number" id="posicion" wire:model.live.debounce.400ms="posicion"
                                        class="form-control border-start-0 ps-0 @error('posicion') is-invalid @enderror"
                                        placeholder="0" min="0" max="999">
                                    @error('posicion')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <div class="form-check form-switch form-switch-lg mb-0">
                                    <input type="checkbox" id="activo" class="form-check-input"
                                        wire:model.live="isActive">
                                    <label for="activo" class="form-check-label">Categoría activa</label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="descripcion" class="form-label">
                                    Descripción <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 align-items-start"><i
                                            class="ri-align-left"></i></span>
                                    <textarea id="descripcion" rows="2" wire:model.live.debounce.400ms="descripcion"
                                        class="form-control border-start-0 @error('descripcion') is-invalid @enderror"
                                        placeholder="¿Qué productos agrupa esta categoría?" maxlength="500"></textarea>
                                    @error('descripcion')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer modal-categoria-footer p-4">
                        <div class="d-flex align-items-center justify-content-between w-100 gap-3 flex-wrap">
                            {{-- Pista de por qué el botón sigue bloqueado --}}
                            <span class="modal-pista-guardar {{ $this->formularioValido ? 'modal-pista-ok' : '' }}">
                                @if ($this->formularioValido)
                                    <i class="ri-checkbox-circle-fill"></i> Listo para guardar
                                @else
                                    <i class="ri-information-line"></i>
                                    Completa los campos con <span class="text-danger">*</span>
                                @endif
                            </span>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light modal-cancelar"
                                    data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-success modal-guardar"
                                    @disabled(!$this->formularioValido) wire:loading.attr="disabled" wire:target="guardar">
                                    <span wire:loading.remove wire:target="guardar">
                                        <i class="ri-save-line align-bottom me-1"></i>
                                        {{ $categoriaId ? 'Guardar cambios' : 'Crear categoría' }}
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
    <div class="modal fade zoomIn" id="modalEliminarCategoria" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1">
                            <i class="ri-delete-bin-line"></i>
                        </span>
                    </div>

                    <span class="modal-eliminar-etiqueta">Confirmación</span>
                    <h5 class="mb-2">¿Eliminar esta categoría?</h5>
                    <p class="text-muted mb-4">
                        Se quitará <strong class="modal-eliminar-nombre">{{ $eliminarNombre }}</strong> del catálogo.
                        Sus datos quedan archivados y no se pierden.
                    </p>

                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100"
                            data-bs-dismiss="modal">Cancelar</button>
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
