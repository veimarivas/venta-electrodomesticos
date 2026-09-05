<div class="items-modulo">

    @php
        // Pill de estado con punto, mismo lenguaje que el resto del catálogo.
        $pillEstado = [
            'en_stock' => 'unidad-estado-stock',
            'reservado' => 'unidad-estado-reservado',
            'vendido' => 'unidad-estado-vendido',
            'devuelto' => 'unidad-estado-devuelto',
            'danado' => 'unidad-estado-danado',
            'garantia' => 'unidad-estado-garantia',
            'perdido' => 'unidad-estado-perdido',
        ];
    @endphp

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-barcode-box-line me-1"></i> Inventario · Unidades
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-box-3-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Unidades (unidades)</h4>
                                <p class="text-white-50 mb-0">
                                    Cada unidad física del producto con su código o serial. Es lo que se vende al cliente.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('unidades.crear')
                                <button type="button" class="btn btn-light crud-nueva-hero" wire:click="abrirCrear">
                                    <i class="ri-add-line align-bottom me-1"></i> Nueva unidad
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
            <x-stat-card label="Total unidades" value="{{ $totalUnidades }}" icon="bx-box"
                color="primary" caption="Registradas en el sistema" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="En stock" value="{{ $enStock }}" icon="bx-check-circle"
                color="success" caption="Disponibles para vender" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Vendidas" value="{{ $vendidos }}" icon="bx-cart"
                color="info" caption="Ya entregadas al cliente" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Valor en inventario" value="Bs {{ number_format($valorInventario, 2, ',', '.') }}"
                icon="bx-wallet2" color="warning" caption="Suma del precio de stock" />
        </div>
    </div>

    {{-- ===================== Ficha del producto ===================== --}}
    @if ($producto)
        <div class="card border-0 shadow-sm overflow-hidden mb-4 producto-ficha-card">
            <div class="card-body p-0">
                <div class="producto-ficha-header">
                    <div class="producto-ficha-header-glow" aria-hidden="true"></div>
                    <div class="row align-items-center g-4">
                        <div class="col-lg-7">
                            <div class="d-flex align-items-start gap-4">
                                <div class="producto-ficha-imagen flex-shrink-0">
                                    @if ($producto->imagen)
                                        <img src="{{ asset('storage/'.$producto->imagen) }}" alt="{{ $producto->nombre }}">
                                    @else
                                        <span class="producto-ficha-imagen-placeholder">
                                            <i class="ri-box-3-line"></i>
                                        </span>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    @if ($producto->categoria)
                                        <div class="producto-ficha-ruta">
                                            <i class="ri-folder-3-line"></i>
                                            {{ str_replace(' / ', ' › ', $producto->categoria->ruta) }}
                                        </div>
                                    @endif
                                    <h4 class="producto-ficha-nombre mb-2">{{ $producto->nombre }}</h4>
                                    <div class="d-flex flex-wrap align-items-center gap-2">

                                        @if ($producto->modelo)
                                            <span class="producto-ficha-modelo">{{ $producto->modelo }}</span>
                                        @endif
                                        @if ($producto->marca)
                                            <span class="producto-ficha-marca">
                                                <i class="ri-trademark-line"></i> {{ $producto->marca->nombre }}
                                            </span>
                                        @endif
                                        <span class="producto-ficha-estado {{ $producto->activo ? 'producto-ficha-estado-activo' : 'producto-ficha-estado-inactivo' }}">
                                            <span class="producto-ficha-estado-dot"></span>
                                            {{ $producto->activo ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="producto-ficha-stats">
                                <div class="producto-ficha-stat">
                                    <div class="producto-ficha-stat-icono producto-ficha-stat-precio">
                                        <i class="ri-money-dollar-circle-line"></i>
                                    </div>
                                    <div>
                                        <span class="producto-ficha-stat-label">Precio de lista</span>
                                        <span class="producto-ficha-stat-valor">Bs {{ number_format((float) $producto->precio_venta, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                                <div class="producto-ficha-stat">
                                    <div class="producto-ficha-stat-icono producto-ficha-stat-stock">
                                        <i class="ri-archive-line"></i>
                                    </div>
                                    <div>
                                        <span class="producto-ficha-stat-label">Stock mínimo</span>
                                        <span class="producto-ficha-stat-valor">{{ $producto->stock_minimo }}</span>
                                    </div>
                                </div>
                                <div class="producto-ficha-stat">
                                    <div class="producto-ficha-stat-icono producto-ficha-stat-garantia">
                                        <i class="ri-shield-check-line"></i>
                                    </div>
                                    <div>
                                        <span class="producto-ficha-stat-label">Garantía</span>
                                        <span class="producto-ficha-stat-valor">{{ $producto->meses_garantia }} {{ $producto->meses_garantia === 1 ? 'mes' : 'meses' }}</span>
                                    </div>
                                </div>
                                <div class="producto-ficha-stat">
                                    <div class="producto-ficha-stat-icono producto-ficha-stat-unidades">
                                        <i class="ri-box-3-line"></i>
                                    </div>
                                    <div>
                                        <span class="producto-ficha-stat-label">Unidades</span>
                                        <span class="producto-ficha-stat-valor">{{ $totalUnidades }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($producto->descripcion || ! empty($producto->especificaciones) || $unidadesPorEstado->isNotEmpty())
                    <div class="producto-ficha-body">
                        <div class="row g-4">
                            @if ($producto->descripcion)
                                <div class="col-lg-6">
                                    <div class="producto-ficha-seccion">
                                        <h6 class="producto-ficha-seccion-titulo">
                                            <i class="ri-file-text-line"></i> Descripción
                                        </h6>
                                        <p class="producto-ficha-descripcion mb-0">{{ $producto->descripcion }}</p>
                                    </div>
                                </div>
                            @endif

                            @if (! empty($producto->especificaciones))
                                <div class="col-lg-6">
                                    <div class="producto-ficha-seccion">
                                        <h6 class="producto-ficha-seccion-titulo">
                                            <i class="ri-list-check-2"></i> Especificaciones
                                        </h6>
                                        <div class="producto-ficha-specs">
                                            @foreach ($producto->especificaciones as $clave => $valor)
                                                <div class="producto-ficha-spec-item">
                                                    <span class="producto-ficha-spec-clave">{{ $clave }}</span>
                                                    <span class="producto-ficha-spec-valor">
                                                        @if ($valor === true)
                                                            <i class="ri-check-line text-success"></i>
                                                        @else
                                                            {{ $valor }}
                                                        @endif
                                                    </span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if ($unidadesPorEstado->isNotEmpty())
                                <div class="col-12">
                                    <div class="producto-ficha-seccion">
                                        <h6 class="producto-ficha-seccion-titulo">
                                            <i class="ri-pie-chart-line"></i> Distribución por estado
                                        </h6>
                                        <div class="producto-ficha-estados">
                                            @foreach ($estados as $valor => $etiqueta)
                                                @if ($unidadesPorEstado->has($valor))
                                                    @php
                                                        $porcentaje = $totalUnidades > 0 ? round(($unidadesPorEstado[$valor] / $totalUnidades) * 100) : 0;
                                                    @endphp
                                                    <div class="producto-ficha-estado-item">
                                                        <div class="producto-ficha-estado-header">
                                                            <span class="unidad-estado {{ $pillEstado[$valor] ?? 'unidad-estado-perdido' }}">
                                                                <span class="unidad-estado-dot"></span>
                                                                {{ $etiqueta }}
                                                            </span>
                                                            <span class="producto-ficha-estado-cantidad">
                                                                {{ $unidadesPorEstado[$valor] }}
                                                                <span class="producto-ficha-estado-pct">({{ $porcentaje }}%)</span>
                                                            </span>
                                                        </div>
                                                        <div class="producto-ficha-estado-barra">
                                                            <div class="producto-ficha-estado-barra-fill producto-ficha-barra-{{ $valor }}"
                                                                style="width: {{ $porcentaje }}%"></div>
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="producto-ficha-footer">
                    <a href="{{ route('inventario.unidades.index') }}" class="btn btn-sm btn-soft-secondary">
                        <i class="ri-arrow-left-line align-bottom me-1"></i> Todo el inventario
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm crud-listado">
        <div class="card-header bg-transparent py-3 crud-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Inventario
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $unidades->total() }}
                        {{ $unidades->total() === 1 ? 'unidad' : 'unidades' }}
                        @if ($buscar !== '')
                            para «{{ $buscar }}»
                        @endif
                    </small>
                </div>

                <div class="col-md-9">
                    <div class="crud-filtros justify-content-md-end">
                        <div class="search-box flex-grow-1" style="max-width: 20rem">
                            <input type="text" class="form-control crud-busqueda"
                                placeholder="Código, serial o producto..." wire:model.live.debounce.400ms="buscar">
                            <i class="ri-search-line search-icon"></i>
                            @if ($buscar !== '')
                                <button type="button"
                                    class="btn btn-sm btn-link text-muted position-absolute end-0 top-50 translate-middle-y me-1 p-1"
                                    wire:click="$set('buscar', '')" title="Limpiar búsqueda">
                                    <i class="ri-close-circle-fill fs-16"></i>
                                </button>
                            @endif
                        </div>

                        <select class="form-select" style="max-width: 13rem" wire:model.live="productoFiltro">
                            <option value="">Todos los productos</option>
                            @foreach ($productos as $opcionProducto)
                                <option value="{{ $opcionProducto->id }}">{{ $opcionProducto->nombre }}</option>
                            @endforeach
                        </select>

                        @if ($productoFiltro || $buscar !== '')
                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                wire:click="$set('productoFiltro', null); $set('buscar', '')"
                                title="Quitar filtros">
                                <i class="ri-filter-off-line align-bottom me-1"></i> Limpiar
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Tabs de estado ===================== --}}
        @if ($estadosConUnidades->isNotEmpty())
            <div class="px-4 pt-3">
                <ul class="nav nav-tabs nav-tabs-estado" role="tablist">
                    @foreach ($estados as $valor => $etiqueta)
                        @if ($estadosConUnidades->has($valor))
                            <li class="nav-item" role="presentation">
                                <button type="button"
                                    class="nav-link {{ $estadoFiltro === $valor ? 'active' : '' }}"
                                    wire:click="$set('estadoFiltro', '{{ $valor }}')"
                                    role="tab"
                                    aria-selected="{{ $estadoFiltro === $valor ? 'true' : 'false' }}">
                                    <span class="unidad-estado-dot tab-estado-dot tab-estado-{{ $valor }}"></span>
                                    {{ $etiqueta }}
                                    <span class="tab-estado-badge">{{ $estadosConUnidades[$valor] }}</span>
                                </button>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Barra de selección: solo aparece cuando hay unidades marcadas --}}
        @if (count($seleccionadas) > 0)
            <div class="barra-seleccion px-4 py-2">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <span class="fw-semibold">
                        <i class="ri-checkbox-multiple-line align-bottom me-1"></i>
                        {{ count($seleccionadas) }}
                        {{ count($seleccionadas) === 1 ? 'unidad seleccionada' : 'unidades seleccionadas' }}
                    </span>

                    <div class="d-flex flex-wrap gap-2">
                        @if ($this->urlEtiquetas)
                            <a href="{{ $this->urlEtiquetas }}" target="_blank" class="btn btn-sm btn-success">
                                <i class="ri-price-tag-3-line align-bottom me-1"></i> Imprimir etiquetas
                            </a>
                        @endif
                        <button type="button" class="btn btn-sm btn-light" wire:click="limpiarSeleccion">
                            Quitar selección
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-crud"
                    wire:loading.class="opacity-50" wire:target="buscar, productoFiltro, estadoFiltro">
                    @php
                        // Ids de la página actual, para el "marcar todo" de la cabecera.
                        $idsPagina = $unidades->pluck('id')->map(fn ($id) => (string) $id)->all();
                        $paginaMarcada = $idsPagina !== [] && empty(array_diff($idsPagina, $seleccionadas));
                    @endphp

                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            @can('unidades.ver')
                                <th scope="col" class="ps-4" style="width:1%">
                                    <input type="checkbox" class="form-check-input" @checked($paginaMarcada)
                                        wire:click="alternarPagina({{ Js::from($idsPagina) }})"
                                        title="Marcar todas las de esta página"
                                        aria-label="Marcar todas las unidades de esta página">
                                </th>
                            @endcan
                            <th scope="col" class="categorizable" style="cursor:pointer" wire:click="ordenar('codigo_interno')">
                                Código interno
                                @if ($ordenarPor === 'codigo_interno')
                                    <i class="ri-arrow-{{ $direccionOrden === 'asc' ? 'up' : 'down' }}-line align-middle"></i>
                                @endif
                            </th>
                            <th scope="col">Serial</th>
                            <th scope="col">Producto</th>
                            <th scope="col">Origen</th>
                            <th scope="col" class="text-end">Costo</th>
                            <th scope="col" class="text-end">Precio</th>
                            <th scope="col" class="text-center">Estado</th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($unidades as $unidad)
                            <tr wire:key="item-{{ $unidad->id }}">
                                @can('unidades.ver')
                                    <td class="ps-4">
                                        <input type="checkbox" class="form-check-input" value="{{ $unidad->id }}"
                                            wire:model.live="seleccionadas"
                                            aria-label="Seleccionar la unidad {{ $unidad->codigo_interno }}">
                                    </td>
                                @endcan
                                <td>
                                    <span class="unidad-codigo">{{ $unidad->codigo_interno }}</span>
                                </td>

                                <td>
                                    @if ($unidad->serial)
                                        <span class="text-muted font-monospace fs-13">{{ $unidad->serial }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td>
                                    <h6 class="mb-0">{{ $unidad->producto->nombre ?? '—' }}</h6>
                                    @if ($unidad->producto)
                                        <small class="text-muted">

                                        </small>
                                    @endif
                                </td>

                                <td>
                                    @if ($unidad->compra_id && $unidad->compra?->proveedor)
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="ri-truck-line text-success fs-14"></i>
                                            <div class="min-w-0">
                                                <small class="text-success fw-semibold d-block">Compra</small>
                                                <small class="text-muted text-truncate d-block" style="max-width:120px">{{ $unidad->compra->proveedor->nombre }}</small>
                                            </div>
                                        </div>
                                    @else
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="ri-archive-line text-secondary fs-14"></i>
                                            <small class="text-muted">Stock anterior</small>
                                        </div>
                                    @endif
                                </td>

                                <td class="text-end">
                                    <span class="text-muted">Bs {{ number_format((float) $unidad->costo_unitario, 2, ',', '.') }}</span>
                                </td>

                                <td class="text-end">
                                    <span class="fw-semibold">Bs {{ number_format((float) $unidad->precio_venta, 2, ',', '.') }}</span>
                                </td>

                                <td class="text-center">
                                    <span class="unidad-estado {{ $pillEstado[$unidad->estado] ?? 'unidad-estado-perdido' }}">
                                        <span class="unidad-estado-dot"></span>
                                        {{ $estados[$unidad->estado] ?? $unidad->estado }}
                                    </span>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @if ($unidad->estado === 'vendido')
                                            @if ($unidad->ventaDetalle?->venta)
                                                <a href="{{ route('ventas.index') }}#venta-{{ $unidad->ventaDetalle->venta_id }}"
                                                    class="btn btn-sm btn-ghost-info btn-icon rounded-circle"
                                                    title="Ver venta #{{ $unidad->ventaDetalle->venta_id }}"
                                                    aria-label="Ver venta de {{ $unidad->codigo_interno }}">
                                                    <i class="ri-shopping-bag-line fs-16"></i>
                                                </a>
                                            @endif
                                        @else
                                            @can('unidades.ver')
                                                <a href="{{ route('etiquetas.unidades', ['ids' => $unidad->id]) }}" target="_blank"
                                                    class="btn btn-sm btn-ghost-info btn-icon rounded-circle"
                                                    title="Imprimir etiqueta" aria-label="Imprimir la etiqueta de {{ $unidad->codigo_interno }}">
                                                    <i class="ri-price-tag-3-line fs-16"></i>
                                                </a>
                                            @endcan
                                            @can('unidades.editar')
                                                <button type="button" class="btn btn-sm btn-ghost-primary btn-icon rounded-circle crud-accion-editar"
                                                    wire:click="abrirEditar({{ $unidad->id }})" title="Editar" aria-label="Editar a {{ $unidad->codigo_interno }}">
                                                    <i class="ri-pencil-line fs-16"></i>
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9">
                                    <div class="text-center py-5">
                                        <div class="crud-empty-icon mx-auto mb-4">
                                            <span class="avatar-title rounded-circle fs-1">
                                                <i class="{{ $buscar !== '' || $productoFiltro ? 'ri-search-eye-line' : 'ri-box-3-line' }}"></i>
                                            </span>
                                        </div>
                                        @if ($buscar !== '' || $productoFiltro)
                                            <h5 class="mb-1">Sin resultados con los filtros actuales</h5>
                                            <p class="text-muted mb-3">Prueba con otros términos o quita los filtros.</p>
                                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                                wire:click="$set('productoFiltro', null); $set('buscar', '')">
                                                <i class="ri-close-line align-bottom me-1"></i> Quitar filtros
                                            </button>
                                        @else
                                            <h5 class="mb-1">No hay unidades en este estado</h5>
                                            <p class="text-muted mb-3">Registra la primera unidad física de un producto para empezar el inventario.</p>
                                            @can('unidades.crear')
                                                <button type="button" class="btn btn-success btn-sm" wire:click="abrirCrear">
                                                    <i class="ri-add-line align-bottom me-1"></i> Crear unidad
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

        @if ($unidades->hasPages())
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando {{ $unidades->firstItem() }}-{{ $unidades->lastItem() }} de {{ $unidades->total() }}
                    </p>
                    <div class="crud-paginacion">
                        {{ $unidades->onEachSide(1)->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal registro / edición ===================== --}}
    <div class="modal fade" id="modalItem" tabindex="-1" aria-hidden="true" wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-crud-dialog">
            <div class="modal-content border-0 modal-crud-content {{ $itemId ? 'modal-editar-crud' : '' }}">
                <div class="modal-header modal-crud-header p-4">
                    <div class="modal-crud-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-crud-icon rounded-circle fs-4">
                                <i class="{{ $itemId ? 'ri-pencil-line' : 'ri-box-3-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">{{ $itemId ? 'Editar unidad' : 'Nueva unidad' }}</h5>
                            <small class="text-muted">
                                {{ $itemId ? 'Modifica los datos y guarda los cambios.' : 'La unidad física con su código o serial, no el modelo.' }}
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-crud-body p-4">
                        @if ($itemId)
                            {{-- ===== MODO EDICIÓN: solo serial, ubicación y notas ===== --}}
                            <h6 class="crud-section-title mb-3"><i class="ri-barcode-box-line"></i> Identificación</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Producto</label>
                                    <div class="crud-categoria-fijada">
                                        <span class="crud-categoria-fijada-icono">
                                            <i class="ri-box-3-line"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <span class="fw-semibold d-block text-truncate">{{ $producto->nombre ?? $productoId }}</span>
                                            <small class="text-muted d-block text-truncate">
                                                {{ $codigoActual }}
                                            </small>
                                        </div>
                                    </div>
                                    <div class="form-text">El producto y el código no se pueden cambiar.</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="serial" class="form-label">
                                        Serial del fabricante <span class="text-muted fw-normal fs-12">(opcional)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="ri-fingerprint-line"></i></span>
                                        <input type="text" id="serial" wire:model.live.debounce.400ms="serial"
                                            @if ($producto?->tiene_serial === false) disabled @endif
                                            class="form-control border-start-0 ps-0 @error('serial') is-invalid @enderror"
                                            placeholder="{{ ($producto?->tiene_serial ?? true) ? 'Ej. S3X9A2K1' : 'Este producto no usa serial' }}" maxlength="100">
                                        @error('serial')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    @if ($producto?->tiene_serial === false)
                                        <div class="form-text text-muted">Este producto no maneja serial de fabricante.</div>
                                    @endif
                                </div>
                            </div>

                            <h6 class="crud-section-title mb-3 mt-4"><i class="ri-archive-line"></i> Inventario</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="ubicacion" class="form-label">
                                        Ubicación <span class="text-muted fw-normal fs-12">(opcional)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="ri-map-pin-line"></i></span>
                                        <input type="text" id="ubicacion" wire:model.live.debounce.400ms="ubicacion"
                                            class="form-control border-start-0 ps-0 @error('ubicacion') is-invalid @enderror"
                                            placeholder="Bodega A / Estante 3" maxlength="120">
                                        @error('ubicacion')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="notas" class="form-label">
                                        Notas <span class="text-muted fw-normal fs-12">(opcional)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="ri-align-left"></i></span>
                                        <textarea id="notas" rows="2" wire:model.live.debounce.400ms="notas"
                                            class="form-control border-start-0 ps-0 @error('notas') is-invalid @enderror"
                                            placeholder="Observaciones de esta unidad..." maxlength="1000"></textarea>
                                        @error('notas')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- ===== MODO CREACIÓN: formulario completo ===== --}}
                            <h6 class="crud-section-title mb-3"><i class="ri-barcode-box-line"></i> Identificación</h6>
                            <div class="row g-3">
                                @php
                                    $productoFijado = $producto !== null;
                                @endphp

                                <div class="col-md-6">
                                    @if ($productoFijado)
                                        <label class="form-label">Producto</label>
                                        <div class="crud-categoria-fijada">
                                            <span class="crud-categoria-fijada-icono">
                                                <i class="ri-box-3-line"></i>
                                            </span>
                                            <div class="min-w-0">
                                                <span class="fw-semibold d-block text-truncate">{{ $producto->nombre }}</span>
                                                <small class="text-muted d-block text-truncate">
                                                    @if ($producto->modelo) {{ $producto->modelo }} @endif
                                                </small>
                                            </div>
                                        </div>
                                        <div class="form-text">
                                            La unidad se registrará en este producto. Para otro, sal al
                                            inventario completo o entra en el producto que quieras.
                                        </div>
                                    @else
                                        <label for="productoId" class="form-label">
                                            Producto <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light border-end-0"><i class="ri-box-3-line"></i></span>
                                            <select id="productoId" wire:model.live="productoId"
                                                class="form-select border-start-0 @error('productoId') is-invalid @enderror">
                                                <option value="">— Elige un producto —</option>
                                                @foreach ($productos as $opcionProducto)
                                                    <option value="{{ $opcionProducto->id }}">{{ $opcionProducto->nombre }}</option>
                                                @endforeach
                                            </select>
                                            @error('productoId')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    @endif
                                </div>

                                <div class="col-md-6">
                                    <label for="serial" class="form-label">
                                        Serial del fabricante <span class="text-muted fw-normal fs-12">(opcional)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="ri-fingerprint-line"></i></span>
                                        <input type="text" id="serial" wire:model.live.debounce.400ms="serial"
                                            @if ($productoId && $productoSeleccionado?->tiene_serial === false) disabled @endif
                                            class="form-control border-start-0 ps-0 @error('serial') is-invalid @enderror"
                                            placeholder="{{ $productoId && $productoSeleccionado?->tiene_serial === false ? 'Este producto no usa serial' : 'Ej. S3X9A2K1' }}" maxlength="100">
                                        @error('serial')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    @if ($productoId && $productoSeleccionado?->tiene_serial === false)
                                        <div class="form-text text-muted">Este producto no maneja serial de fabricante.</div>
                                    @else
                                        <div class="form-text">Si el fabricante lo trae. Se puede dejar vacío.</div>
                                    @endif
                                </div>

                                <div class="col-12">
                                    <label class="form-label">
                                        Código interno <span class="text-danger">*</span>
                                    </label>
                                    <div class="crud-codigo-preview">
                                        <i class="ri-qr-code-line"></i>
                                        <div class="min-w-0">
                                            <code class="fs-14">{{ $this->codigoPreview !== '' ? $this->codigoPreview : 'Elige un producto para generarlo' }}</code>
                                            <small class="d-block text-muted">Se genera solo al guardar.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h6 class="crud-section-title mb-3 mt-4"><i class="ri-money-dollar-circle-line"></i> Precios</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="costo" class="form-label">
                                        Costo (Bs) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">Bs</span>
                                        <input type="number" id="costo" wire:model.live.debounce.400ms="costo" step="0.01" min="0"
                                            class="form-control border-start-0 ps-0 @error('costo') is-invalid @elseif ($costo !== '') is-valid @enderror"
                                            placeholder="0.00">
                                        @error('costo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-text">Costo real de esta unidad.</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="precio" class="form-label">
                                        Precio de venta (Bs) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0">Bs</span>
                                        <input type="number" id="precio" wire:model.live.debounce.400ms="precio" step="0.01" min="0"
                                            class="form-control border-start-0 ps-0 @error('precio') is-invalid @elseif ($precio !== '') is-valid @enderror"
                                            placeholder="0.00">
                                        @error('precio')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-text">Se sugiere el precio de lista del producto.</div>
                                </div>
                            </div>

                            <h6 class="crud-section-title mb-3 mt-4"><i class="ri-archive-line"></i> Inventario</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Estado <span class="text-danger">*</span></label>
                                    <div class="crud-categoria-fijada">
                                        <span class="crud-categoria-fijada-icono">
                                            <i class="ri-radio-button-line"></i>
                                        </span>
                                        <span class="fw-semibold">{{ $estados['en_stock'] }}</span>
                                    </div>
                                    <div class="form-text">Toda unidad nueva entra disponible.</div>
                                </div>

                                <div class="col-md-4">
                                    <label for="ubicacion" class="form-label">
                                        Ubicación <span class="text-muted fw-normal fs-12">(opcional)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="ri-map-pin-line"></i></span>
                                        <input type="text" id="ubicacion" wire:model.live.debounce.400ms="ubicacion"
                                            class="form-control border-start-0 ps-0 @error('ubicacion') is-invalid @enderror"
                                            placeholder="Bodega A / Estante 3" maxlength="120">
                                        @error('ubicacion')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label for="fechaIngreso" class="form-label">
                                        Fecha de ingreso <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="ri-calendar-line"></i></span>
                                        <input type="date" id="fechaIngreso" wire:model.live.debounce.400ms="fechaIngreso"
                                            class="form-control border-start-0 ps-0 @error('fechaIngreso') is-invalid @enderror">
                                        @error('fechaIngreso')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-text">La garantía se calcula desde los meses del producto.</div>
                                </div>

                                <div class="col-12">
                                    <label for="notas" class="form-label">
                                        Notas <span class="text-muted fw-normal fs-12">(opcional)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="ri-align-left"></i></span>
                                        <textarea id="notas" rows="2" wire:model.live.debounce.400ms="notas"
                                            class="form-control border-start-0 ps-0 @error('notas') is-invalid @enderror"
                                            placeholder="Observaciones de esta unidad..." maxlength="1000"></textarea>
                                        @error('notas')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer modal-crud-footer p-4">
                        <div class="d-flex align-items-center justify-content-between w-100 gap-3 flex-wrap">
                            <small class="{{ $this->formularioValido ? 'modal-pista-ok' : 'modal-pista-guardar' }}">
                                @if ($this->formularioValido)
                                    <i class="ri-checkbox-circle-fill align-bottom me-1"></i> Listo para guardar
                                @else
                                    <i class="ri-information-line align-bottom me-1"></i>
                                    Completa los campos marcados con <span class="text-danger">*</span>
                                @endif
                            </small>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-success modal-guardar" @disabled(! $this->formularioValido)
                                    wire:loading.attr="disabled" wire:target="guardar">
                                    <span wire:loading.remove wire:target="guardar">
                                        <i class="ri-save-line align-bottom me-1"></i>
                                        Guardar cambios
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
</div>
