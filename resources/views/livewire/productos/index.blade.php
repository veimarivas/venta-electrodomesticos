<div class="productos-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-price-tag-3-line me-1"></i>
                            @if ($categoria)
                                Catálogo · Categoría
                            @else
                                Catálogo · Productos
                            @endif
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="{{ $categoria ? 'ri-folder-open-line' : 'ri-shopping-bag-3-line' }}"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                @if ($categoria)
                                    {{-- Dentro de una categoría el encabezado dice cuál es y
                                         dónde vive en el árbol, no solo "Productos". --}}
                                    @if ($categoriaRuta !== '')
                                        <p class="text-white-50 mb-1 fs-13 text-truncate productos-ruta">
                                            <a href="{{ route('productos.index') }}"
                                                class="text-white-50 text-decoration-none">Catálogo</a>
                                            <i class="ri-arrow-right-s-line align-middle"></i>
                                            {{ str_replace(' / ', ' › ', $categoriaRuta) }}
                                        </p>
                                    @endif
                                    <h4 class="text-white mb-1 text-truncate">{{ $categoria->nombre }}</h4>
                                    <p class="text-white-50 mb-0">
                                        {{ $totalProductos }}
                                        {{ $totalProductos === 1 ? 'producto' : 'productos' }} en esta categoría
                                        @if ($subcategorias->isNotEmpty())
                                            y sus {{ $subcategorias->count() }}
                                            {{ $subcategorias->count() === 1 ? 'subcategoría' : 'subcategorías' }}
                                        @endif
                                    </p>
                                @else
                                    <h4 class="text-white mb-1">Productos</h4>
                                    <p class="text-white-50 mb-0">
                                        El modelo del producto, su precio de lista y sus especificaciones.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('productos.crear')
                                <button type="button" class="btn btn-light crud-nueva-hero" wire:click="abrirCrear">
                                    <i class="ri-add-line align-bottom me-1"></i> Nuevo producto
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
            {{-- Los indicadores siguen al contexto: dentro de una categoría son
                 los de su rama, no los del catálogo entero. --}}
            <x-stat-card label="Productos" value="{{ $totalProductos }}" icon="bx-package" color="primary"
                caption="{{ $categoria ? 'En ' . $categoria->nombre . ' y sus subcategorías' : 'Total en el catálogo' }}" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Activos" value="{{ $activos }}" icon="bx-check-circle" color="success"
                caption="Disponibles a la venta" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Inactivos" value="{{ $inactivos }}" icon="bx-hide" color="danger"
                caption="Ocultos temporalmente" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Valor de catálogo" value="Bs {{ number_format($valorCatalogo, 2, ',', '.') }}"
                icon="bx-wallet2" color="info" caption="Suma de precios de lista" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm crud-listado">
        <div class="card-header bg-transparent py-3 crud-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Catálogo
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $productos->total() }}
                        {{ $productos->total() === 1 ? 'producto' : 'productos' }}
                        @if ($buscar !== '')
                            para «{{ $buscar }}»
                        @endif
                    </small>
                </div>

                <div class="col-md-9">
                    <div class="crud-filtros justify-content-md-end">
                        <div class="search-box flex-grow-1" style="max-width: 22rem">
                            <input type="text" class="form-control crud-busqueda"
                                placeholder="Buscar por nombre, SKU o modelo..."
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

                        <select class="form-select" style="max-width: 13rem" wire:model.live="categoriaFiltro">
                            <option value="">Todas las categorías</option>
                            @foreach ($opcionesCategorias as $opcion)
                                <option value="{{ $opcion['id'] }}">
                                    {{ str_repeat('— ', $opcion['nivel']) }}{{ $opcion['nombre'] }}
                                </option>
                            @endforeach
                        </select>

                        {{-- Dentro de una categoría el selector solo trae las marcas
                             con productos en esa rama; el resto no daría resultados. --}}
                        <select class="form-select" style="max-width: 11rem" wire:model.live="marcaFiltro"
                            @disabled($categoria && $marcas->isEmpty())>
                            <option value="">
                                @if ($categoria && $marcas->isEmpty())
                                    Sin marcas en esta categoría
                                @elseif ($categoria)
                                    Todas las marcas de {{ $categoria->nombre }}
                                @else
                                    Todas las marcas
                                @endif
                            </option>
                            @foreach ($marcas as $marca)
                                <option value="{{ $marca->id }}">{{ $marca->nombre }}</option>
                            @endforeach
                        </select>

                        @if ($categoriaFiltro || $marcaFiltro || $buscar !== '')
                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                wire:click="$set('categoriaFiltro', null); $set('marcaFiltro', null); $set('buscar', '')"
                                title="Quitar filtros">
                                <i class="ri-filter-off-line align-bottom me-1"></i> Limpiar
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        @if ($categoria)
            <div class="productos-contexto">
                <div class="productos-contexto-cabecera">
                    <div class="productos-contexto-titulo">
                        <span class="productos-contexto-icono">
                            <i class="ri-folder-open-line"></i>
                        </span>
                        <div class="min-w-0">
                            <small>Explorando la categoría</small>
                            <strong class="text-truncate d-block">{{ $categoria->nombre }}</strong>
                        </div>
                    </div>

                    <span class="productos-contexto-resumen">
                        <i class="ri-price-tag-3-line align-middle me-1"></i>
                        {{ $totalProductos }}
                        {{ $totalProductos === 1 ? 'producto' : 'productos' }}
                        @if ($subcategorias->isNotEmpty())
                            en {{ $subcategorias->count() }}
                            {{ $subcategorias->count() === 1 ? 'subcategoría' : 'subcategorías' }}
                        @endif
                    </span>

                    <a href="{{ route('productos.index') }}" class="productos-contexto-todo">
                        Ver todo el catálogo <i class="ri-arrow-right-line align-middle"></i>
                    </a>
                </div>

                @if ($subcategorias->isNotEmpty())
                    {{-- Directorio de la categoría abierta: cada rama hija como
                         tarjeta. Entrar en una acota el listado a esa subcategoría
                         (y a lo que cuelgue de ella). --}}
                    <div class="productos-subcategorias">
                        <div class="productos-subcategorias-titulo">
                            <i class="ri-node-tree"></i> Subcategorías
                        </div>
                        <div class="productos-subcategorias-filas">
                            @foreach ($subcategorias as $subcategoria)
                                <button type="button" class="productos-subcategoria-card"
                                    wire:click="$set('categoriaFiltro', {{ $subcategoria->id }})"
                                    title="Ver solo {{ $subcategoria->nombre }}"
                                    aria-label="Filtrar por la subcategoría {{ $subcategoria->nombre }}">
                                    <span class="productos-subcategoria-icono">
                                        <i class="ri-folder-line"></i>
                                    </span>
                                    <span class="productos-subcategoria-info min-w-0">
                                        <span class="productos-subcategoria-nombre text-truncate">
                                            {{ $subcategoria->nombre }}
                                        </span>
                                        <span class="productos-subcategoria-meta">
                                            {{ $subcategoria->productos_count }}
                                            {{ $subcategoria->productos_count === 1 ? 'producto' : 'productos' }}
                                        </span>
                                    </span>
                                    <i class="ri-arrow-right-s-line productos-subcategoria-flecha"></i>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-crud" wire:loading.class="opacity-50"
                    wire:target="buscar, categoriaFiltro, marcaFiltro">
                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4">Imagen</th>
                            <th scope="col" class="categorizable" style="cursor:pointer"
                                wire:click="ordenar('nombre')">
                                Producto
                                @if ($ordenarPor === 'nombre')
                                    <i
                                        class="ri-arrow-{{ $direccionOrden === 'asc' ? 'up' : 'down' }}-line align-middle"></i>
                                @endif
                            </th>
                            <th scope="col">Categoría</th>
                            <th scope="col">Marca</th>
                            <th scope="col" class="text-center">Disponibles</th>
                            <th scope="col" class="text-end">Precio</th>
                            <th scope="col" class="text-center">Estado</th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($productos as $producto)
                            <tr wire:key="producto-{{ $producto->id }}">
                                <td class="ps-4">
                                    <div class="crud-imagen">
                                        @if ($producto->imagen)
                                            <img src="{{ asset('storage/' . $producto->imagen) }}"
                                                alt="{{ $producto->nombre }}">
                                        @else
                                            <span class="avatar-title"><i class="ri-image-line"></i></span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    <h6 class="mb-0">{{ $producto->nombre }}</h6>
                                    <small class="text-muted">
                                        <code class="fs-12">{{ $producto->sku }}</code>
                                        @if ($producto->modelo)
                                            · {{ $producto->modelo }}
                                        @endif
                                    </small>
                                </td>

                                <td>
                                    <span class="text-muted">
                                        <i class="ri-folder-line align-middle me-1"></i>
                                        {{ $producto->categoria->nombre ?? '—' }}
                                    </span>
                                </td>

                                <td>
                                    @if ($producto->marca)
                                        <span class="text-muted">{{ $producto->marca->nombre }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    @php
                                        // Unidades en estado en_stock. El color avisa del nivel:
                                        // rojo sin existencias, ámbar en o por debajo del stock
                                        // mínimo configurado en el producto, verde con holgura.
                                        $disponibles = $producto->unidades_disponibles;
                                        $tono = match (true) {
                                            $disponibles === 0 => 'danger',
                                            $disponibles <= $producto->stock_minimo => 'warning',
                                            default => 'success',
                                        };
                                    @endphp
                                    <span
                                        class="badge bg-{{ $tono }}-subtle text-{{ $tono }} productos-disponibles"
                                        title="{{ $disponibles }} {{ $disponibles === 1 ? 'unidad en stock' : 'unidades en stock' }}@if ($producto->stock_minimo > 0) · mínimo {{ $producto->stock_minimo }} @endif">
                                        <i class="ri-archive-2-line align-middle me-1"></i>{{ $disponibles }}
                                    </span>
                                </td>

                                <td class="text-end">
                                    <span class="fw-semibold">Bs
                                        {{ number_format((float) $producto->precio_venta, 2, ',', '.') }}</span>
                                    @if ((float) $producto->descuento_maximo > 0)
                                        <span class="d-block text-muted fs-11"
                                            title="Rebaja máxima autorizada en el punto de venta">
                                            <i class="ri-price-tag-3-line align-bottom"></i>
                                            hasta −{{ number_format((float) $producto->descuento_maximo, 2, ',', '.') }}
                                        </span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    <span
                                        class="producto-estado {{ $producto->activo ? 'producto-estado-activo' : 'producto-estado-inactivo' }}">
                                        <span class="producto-estado-dot" aria-hidden="true"></span>
                                        {{ $producto->activo ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @can('unidades.ver')
                                            <button type="button"
                                                class="btn btn-sm btn-ghost-info btn-icon rounded-circle crud-accion-ver"
                                                wire:click="verUnidades({{ $producto->id }})" title="Ver unidades"
                                                aria-label="Ver unidades de {{ $producto->nombre }}">
                                                <i class="ri-box-3-line fs-16"></i>
                                            </button>
                                        @endcan
                                        @can('productos.editar')
                                            <button type="button"
                                                class="btn btn-sm btn-ghost-primary btn-icon rounded-circle crud-accion-editar"
                                                wire:click="abrirEditar({{ $producto->id }})" title="Editar"
                                                aria-label="Editar a {{ $producto->nombre }}">
                                                <i class="ri-pencil-line fs-16"></i>
                                            </button>
                                        @endcan
                                        @can('productos.eliminar')
                                            <button type="button"
                                                class="btn btn-sm btn-ghost-danger btn-icon rounded-circle crud-accion-eliminar"
                                                wire:click="confirmarEliminar({{ $producto->id }})" title="Eliminar"
                                                aria-label="Eliminar a {{ $producto->nombre }}">
                                                <i class="ri-delete-bin-line fs-16"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="text-center py-5">
                                        <div class="crud-empty-icon mx-auto mb-4">
                                            <span class="avatar-title rounded-circle fs-1">
                                                <i
                                                    class="{{ $buscar !== '' || $categoriaFiltro || $marcaFiltro ? 'ri-search-eye-line' : 'ri-shopping-bag-3-line' }}"></i>
                                            </span>
                                        </div>
                                        @if ($buscar !== '' || $categoriaFiltro || $marcaFiltro)
                                            <h5 class="mb-1">Sin resultados con los filtros actuales</h5>
                                            <p class="text-muted mb-3">Prueba con otros términos o quita los filtros.
                                            </p>
                                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                                wire:click="$set('categoriaFiltro', null); $set('marcaFiltro', null); $set('buscar', '')">
                                                <i class="ri-close-line align-bottom me-1"></i> Quitar filtros
                                            </button>
                                        @else
                                            <h5 class="mb-1">Todavía no hay productos</h5>
                                            <p class="text-muted mb-3">Crea el primero para empezar a poblar el
                                                catálogo.</p>
                                            @can('productos.crear')
                                                <button type="button" class="btn btn-success btn-sm"
                                                    wire:click="abrirCrear">
                                                    <i class="ri-add-line align-bottom me-1"></i> Crear producto
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

        @if ($productos->hasPages())
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando {{ $productos->firstItem() }}-{{ $productos->lastItem() }} de
                        {{ $productos->total() }}
                    </p>
                    <div class="crud-paginacion">
                        {{ $productos->onEachSide(1)->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal registro / edición ===================== --}}
    <div class="modal fade" id="modalProducto" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-crud-dialog">
            <div class="modal-content border-0 modal-crud-content {{ $productoId ? 'modal-editar-crud' : '' }}">
                <div class="modal-header modal-crud-header p-4">
                    <div class="modal-crud-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-crud-icon rounded-circle fs-4">
                                <i class="{{ $productoId ? 'ri-pencil-line' : 'ri-shopping-bag-3-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">{{ $productoId ? 'Editar producto' : 'Nuevo producto' }}</h5>
                            <small class="text-muted">
                                @if ($productoId)
                                    Modifica los datos y guarda los cambios.
                                @elseif ($categoria)
                                    Se registrará en la categoría <strong>{{ $categoria->nombre }}</strong>.
                                @else
                                    Registra el modelo, no las unidades físicas.
                                @endif
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-crud-body p-4">
                        <h6 class="crud-section-title mb-3"><i class="ri-price-tag-3-line"></i> Identificación</h6>
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label for="nombre" class="form-label">
                                    Nombre <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-shopping-bag-3-line"></i></span>
                                    <input type="text" id="nombre" wire:model.live.debounce.400ms="nombre"
                                        class="form-control border-start-0 ps-0 @error('nombre') is-invalid @elseif ($nombre !== '') is-valid @enderror"
                                        placeholder="Ej. Smart TV 55\" 4K" maxlength="150">
                                    @error('nombre')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label for="sku" class="form-label">
                                    SKU <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-barcode-line"></i></span>
                                    <input type="text" id="sku" wire:model.live.debounce.400ms="sku"
                                        class="form-control border-start-0 ps-0 @error('sku') is-invalid @elseif ($sku !== '') is-valid @enderror"
                                        placeholder="TVSAM55" maxlength="40">
                                    @error('sku')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-text">Código corto interno del modelo.</div>
                            </div>

                            <div class="col-md-7">
                                <label for="slug" class="form-label">
                                    Slug <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-link"></i></span>
                                    <input type="text" id="slug" wire:model.live.debounce.400ms="slug"
                                        class="form-control border-start-0 ps-0 @error('slug') is-invalid @elseif ($slug !== '') is-valid @enderror"
                                        placeholder="smart-tv-55-4k" maxlength="160">
                                    @error('slug')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-text">Se genera solo desde el nombre. Escríbelo para personalizarlo.
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label for="modelo" class="form-label">
                                    Modelo <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-cpu-line"></i></span>
                                    <input type="text" id="modelo" wire:model.live.debounce.400ms="modelo"
                                        class="form-control border-start-0 ps-0 @error('modelo') is-invalid @enderror"
                                        placeholder="UN55CU8000" maxlength="120">
                                    @error('modelo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <h6 class="crud-section-title mb-3 mt-4"><i class="ri-node-tree"></i> Clasificación</h6>
                        <div class="row g-3">
                            @php
                                // Al crear desde una categoría no hay nada que elegir: el
                                // producto nace en ella y el modal solo lo informa. Al
                                // editar el selector sigue estando, para poder reclasificar.
                                $categoriaFijada = $productoId === null && $categoria !== null;
                            @endphp

                            <div class="col-md-6">
                                @if ($categoriaFijada)
                                    <label class="form-label">Categoría</label>
                                    <div class="crud-categoria-fijada">
                                        <span class="crud-categoria-fijada-icono">
                                            <i class="ri-folder-open-line"></i>
                                        </span>
                                        <div class="min-w-0">
                                            @if ($categoriaRuta !== '')
                                                <small class="text-muted d-block text-truncate">
                                                    {{ str_replace(' / ', ' › ', $categoriaRuta) }}
                                                </small>
                                            @endif
                                            <span
                                                class="fw-semibold d-block text-truncate">{{ $categoria->nombre }}</span>
                                        </div>
                                    </div>
                                    <div class="form-text">
                                        Se registrará en esta categoría. Para usar otra, sal al catálogo
                                        completo o entra en la categoría que quieras.
                                    </div>
                                @else
                                    <label for="categoriaId" class="form-label">
                                        Categoría <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i
                                                class="ri-folder-line"></i></span>
                                        <select id="categoriaId" wire:model.live="categoriaId"
                                            class="form-select border-start-0 @error('categoriaId') is-invalid @enderror">
                                            <option value="">— Elige una categoría —</option>
                                            @foreach ($opcionesCategorias as $opcion)
                                                <option value="{{ $opcion['id'] }}">
                                                    {{ str_repeat('— ', $opcion['nivel']) }}{{ $opcion['nombre'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('categoriaId')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <label for="marcaId" class="form-label">
                                    Marca <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                @if (! $mostrarFormularioMarca)
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i
                                                class="ri-trademark-line"></i></span>
                                        <select id="marcaId" wire:model.live="marcaId"
                                            class="form-select border-start-0 @error('marcaId') is-invalid @enderror">
                                            <option value="">— Sin marca —</option>
                                            @foreach ($marcasFormulario as $marca)
                                                <option value="{{ $marca->id }}">{{ $marca->nombre }}</option>
                                            @endforeach
                                        </select>
                                        @can('marcas.crear')
                                            <button type="button" wire:click="abrirFormularioMarca"
                                                class="btn btn-outline-success d-flex align-items-center gap-1"
                                                title="Registrar nueva marca">
                                                <i class="ri-add-line"></i>
                                            </button>
                                        @endcan
                                        @error('marcaId')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                @else
                                    <div class="border rounded-3 p-3 bg-white shadow-sm">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <small class="text-muted fw-semibold">
                                                <i class="ri-add-circle-line me-1"></i> Nueva marca
                                            </small>
                                            <button type="button" wire:click="cerrarFormularioMarca"
                                                class="btn btn-sm btn-link text-muted p-0" title="Cancelar">
                                                <i class="ri-close-line"></i>
                                            </button>
                                        </div>
                                        <div class="mb-2">
                                            <input type="text" wire:model.live="nuevaMarcaNombre"
                                                class="form-control form-control-sm @error('nuevaMarcaNombre') is-invalid @enderror"
                                                placeholder="Nombre de la marca" maxlength="100" autofocus>
                                            @error('nuevaMarcaNombre')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="mb-2">
                                            <input type="text" wire:model.live="nuevaMarcaSlug"
                                                class="form-control form-control-sm @error('nuevaMarcaSlug') is-invalid @enderror"
                                                placeholder="slug-de-la-marca" maxlength="100">
                                            @error('nuevaMarcaSlug')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="d-flex justify-content-end">
                                            <button type="button" wire:click="guardarMarcaRapida"
                                                wire:loading.attr="disabled"
                                                class="btn btn-sm btn-success @unless($this->formularioMarcaValido) disabled @endunless">
                                                <i class="ri-check-line me-1"></i> Guardar y seleccionar
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <h6 class="crud-section-title mb-3 mt-4"><i class="ri-tools-line"></i> Ficha comercial</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="precio" class="form-label">
                                    Precio de venta (Bs) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Bs</span>
                                    <input type="number" id="precio" wire:model.live.debounce.400ms="precio"
                                        step="0.01" min="0"
                                        class="form-control border-start-0 ps-0 @error('precio') is-invalid @elseif ($precio !== '') is-valid @enderror"
                                        placeholder="0.00">
                                    @error('precio')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="descuentoMaximo" class="form-label">
                                    Descuento máximo (Bs)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-price-tag-3-line"></i></span>
                                    <input type="number" id="descuentoMaximo"
                                        wire:model.live.debounce.400ms="descuentoMaximo" step="0.01" min="0"
                                        class="form-control border-start-0 ps-0 @error('descuentoMaximo') is-invalid @enderror"
                                        placeholder="0.00">
                                    @error('descuentoMaximo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="form-text">
                                    Lo máximo que el mostrador puede rebajar. Con 0 se cobra el precio de lista.
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="minStock" class="form-label">
                                    Stock mínimo <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-archive-2-line"></i></span>
                                    <input type="number" id="minStock" wire:model.live.debounce.400ms="minStock"
                                        min="0"
                                        class="form-control border-start-0 ps-0 @error('minStock') is-invalid @enderror"
                                        placeholder="0">
                                    @error('minStock')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="mesesGarantia" class="form-label">
                                    Meses de garantía <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-shield-check-line"></i></span>
                                    <input type="number" id="mesesGarantia"
                                        wire:model.live.debounce.400ms="mesesGarantia" min="0" max="240"
                                        class="form-control border-start-0 ps-0 @error('mesesGarantia') is-invalid @enderror"
                                        placeholder="12">
                                    @error('mesesGarantia')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <div class="form-check form-switch form-switch-lg">
                                    <input type="checkbox" id="activo" class="form-check-input"
                                        wire:model.live="isActive">
                                    <label for="activo" class="form-check-label">Producto activo</label>
                                </div>
                            </div>
                        </div>

                        <h6 class="crud-section-title mb-3 mt-4"><i class="ri-file-list-3-line"></i> Detalles</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="descripcion" class="form-label">
                                    Descripción <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0 align-items-start"><i
                                            class="ri-align-left"></i></span>
                                    <textarea id="descripcion" rows="5" wire:model.live.debounce.400ms="descripcion"
                                        class="form-control border-start-0 @error('descripcion') is-invalid @enderror"
                                        placeholder="Qué hace el producto, para quién es, qué incluye la caja..." maxlength="1000"></textarea>
                                    @error('descripcion')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <label class="form-label mb-0">
                                        Especificaciones <span class="text-muted fw-normal fs-12">(opcional)</span>
                                    </label>
                                    <button type="button"
                                        class="btn btn-sm btn-soft-primary crud-especificacion-agregar"
                                        wire:click="agregarEspecificacion">
                                        <i class="ri-add-line align-bottom me-1"></i> Agregar
                                    </button>
                                </div>

                                {{-- Una fila por característica. Los botones son type="button":
                                     agregar o quitar filas no envía el formulario ni guarda nada,
                                     el producto se registra una sola vez al pulsar Guardar. --}}
                                <div class="crud-especificaciones">
                                    @forelse ($especificaciones as $indice => $especificacion)
                                        <div class="crud-especificacion"
                                            wire:key="especificacion-{{ $indice }}">
                                            <input type="text"
                                                wire:model.live.debounce.400ms="especificaciones.{{ $indice }}.clave"
                                                class="form-control @error('especificaciones.' . $indice . '.clave') is-invalid @enderror"
                                                placeholder="Característica" maxlength="60"
                                                aria-label="Característica {{ $indice + 1 }}">
                                            <input type="text"
                                                wire:model.live.debounce.400ms="especificaciones.{{ $indice }}.valor"
                                                class="form-control @error('especificaciones.' . $indice . '.valor') is-invalid @enderror"
                                                placeholder="Valor" maxlength="200"
                                                aria-label="Valor {{ $indice + 1 }}">
                                            <button type="button"
                                                class="btn btn-ghost-danger btn-icon crud-especificacion-quitar"
                                                wire:click="quitarEspecificacion({{ $indice }})"
                                                title="Quitar esta especificación"
                                                aria-label="Quitar especificación {{ $indice + 1 }}">
                                                <i class="ri-close-line"></i>
                                            </button>
                                        </div>
                                    @empty
                                        <p class="text-muted fs-13 mb-0 crud-especificaciones-vacio">
                                            Sin especificaciones. Pulsa <strong>Agregar</strong> para añadir la primera.
                                        </p>
                                    @endforelse
                                </div>

                                @error('especificaciones')
                                    <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                                @enderror
                                @error('especificaciones.*.clave')
                                    <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                                @enderror
                                @error('especificaciones.*.valor')
                                    <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                                @enderror

                                <div class="form-text">
                                    Ej. «Pantalla» → «55 pulgadas». Deja el valor vacío para una
                                    característica que solo se cumple o no (como «HDR»).
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Imagen <span
                                        class="text-muted fw-normal fs-12">(opcional)</span></label>
                                <div class="crud-upload">
                                    <div class="crud-upload-preview">
                                        @if ($imagen)
                                            <img src="{{ $imagen->temporaryUrl() }}" alt="Nueva imagen">
                                        @elseif ($imagenActual !== '')
                                            <img src="{{ asset('storage/' . $imagenActual) }}" alt="Imagen actual">
                                        @else
                                            <span class="avatar-title"><i class="ri-image-line"></i></span>
                                        @endif
                                    </div>

                                    <div class="flex-grow-1 min-w-0">
                                        <input type="file" wire:model="imagen"
                                            accept="image/jpeg,image/png,image/webp"
                                            class="form-control @error('imagen') is-invalid @enderror">
                                        @error('imagen')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div wire:loading wire:target="imagen" class="form-text text-primary">
                                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                            Subiendo imagen...
                                        </div>
                                        <div class="form-text">JPG, PNG o WebP de hasta 3 MB.</div>
                                    </div>

                                    @if ($imagenActual !== '' && !$imagen)
                                        <div class="form-check">
                                            <input type="checkbox" id="quitar_imagen" class="form-check-input"
                                                wire:model.live="quitarImagen">
                                            <label for="quitar_imagen"
                                                class="form-check-label text-danger">Quitar</label>
                                        </div>
                                    @endif
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
                                <button type="button" class="btn btn-light modal-cancelar"
                                    data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-success modal-guardar"
                                    @disabled(!$this->formularioValido) wire:loading.attr="disabled" wire:target="guardar">
                                    <span wire:loading.remove wire:target="guardar">
                                        <i class="ri-save-line align-bottom me-1"></i>
                                        {{ $productoId ? 'Guardar cambios' : 'Crear producto' }}
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
    <div class="modal fade zoomIn" id="modalEliminarProducto" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1"><i class="ri-delete-bin-line"></i></span>
                    </div>

                    <h5 class="mb-2">¿Eliminar este producto?</h5>
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
