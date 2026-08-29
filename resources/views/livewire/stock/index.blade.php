<div class="stock-modulo">

    {{-- ===================== Hero ===================== --}}
    <div class="stock-hero mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="min-w-0">
                <h1 class="stock-hero-titulo">Stock Actual</h1>
                <p class="stock-hero-sub">Inventario en tiempo real de todos los productos.</p>
            </div>
            <span class="stock-hero-badge">
                <i class="ri-stack-line"></i> {{ $resumen['productos'] }} productos activos
            </span>
        </div>
    </div>

    {{-- ===================== KPIs ===================== --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card stock-kpi h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="min-w-0">
                            <span class="stock-kpi-label">Unidades en stock</span>
                            <span class="stock-kpi-valor">{{ number_format($resumen['unidades'], 0, ',', '.') }}</span>
                            <span class="stock-kpi-nota">Listas para vender</span>
                        </div>
                        <span class="stock-kpi-icono stock-kpi-icono--stock"><i class="ri-archive-2-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stock-kpi h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="min-w-0">
                            <span class="stock-kpi-label">Valor de inventario</span>
                            <span class="stock-kpi-valor">Bs {{ number_format($resumen['valor'], 2, ',', '.') }}</span>
                            <span class="stock-kpi-nota">Unidades × precio de venta</span>
                        </div>
                        <span class="stock-kpi-icono stock-kpi-icono--valor"><i class="ri-wallet-2-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stock-kpi h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="min-w-0">
                            <span class="stock-kpi-label">Productos agotados</span>
                            <span class="stock-kpi-valor" style="color: #e34948;">{{ $resumen['agotados'] }}</span>
                            <span class="stock-kpi-nota">Sin existencias disponibles</span>
                        </div>
                        <span class="stock-kpi-icono stock-kpi-icono--agotados"><i class="ri-close-circle-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card stock-kpi h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="min-w-0">
                            <span class="stock-kpi-label">Bajo stock mínimo</span>
                            <span class="stock-kpi-valor" style="color: var(--marca-oro);">{{ $resumen['bajoMinimo'] }}</span>
                            <span class="stock-kpi-nota">Requieren reposición</span>
                        </div>
                        <span class="stock-kpi-icono stock-kpi-icono--alerta"><i class="ri-alert-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        {{-- ===================== Panel de filtros ===================== --}}
        <div class="col-xl-3 col-lg-4">
            <div class="card stock-filtros">
                <div class="card-header">
                    <div class="d-flex mb-3">
                        <div class="flex-grow-1">
                            <h5 class="fs-16 mb-0 stock-text-ink" style="font-weight: 650;">
                                <i class="ri-filter-3-line align-bottom me-1 stock-text-accent"></i> Filtros
                            </h5>
                        </div>
                        <div class="flex-shrink-0">
                            <button type="button"
                                class="stock-limpiar"
                                wire:click="limpiarFiltros">
                                <i class="ri-close-line"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="accordion accordion-flush filter-accordion">

                    {{-- Categorías --}}
                    <div class="card-body border-bottom">
                        <p class="text-uppercase fs-12 fw-medium mb-2 stock-text-muted">Categorías</p>
                        <ul class="list-unstyled mb-0 filter-list stock-filtro-lista">
                            @forelse ($categoriasFiltro as $opcion)
                                <li>
                                    <button type="button"
                                        class="d-flex py-1 align-items-center w-100 bg-transparent border-0 stock-filtro-item {{ $opcion['activa'] ? 'stock-text-accent' : 'stock-text-ink' }}"
                                        style="padding-left: {{ 8 + $opcion['nivel'] * 14 }}px;"
                                        wire:click="cambiarCategoria({{ $opcion['id'] }})"
                                        title="Ver stock de {{ $opcion['nombre'] }}">
                                        <i class="ri-folder-{{ $opcion['activa'] ? 'open-fill' : 'line' }} align-middle me-1 fs-14"></i>
                                        <span class="flex-grow-1 text-start text-truncate">{{ $opcion['nombre'] }}</span>
                                        <span class="badge rounded-pill ms-2 flex-shrink-0 {{ $opcion['activa'] ? 'stock-text-accent' : 'stock-bg-inactive' }}">{{ $opcion['total'] }}</span>
                                    </button>
                                </li>
                            @empty
                                <li class="text-muted fs-13 py-1">Sin categorías con productos.</li>
                            @endforelse
                        </ul>
                    </div>

                    {{-- Marcas --}}
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="flush-headingMarcas">
                            <button class="accordion-button bg-transparent shadow-none" type="button"
                                data-bs-toggle="collapse" data-bs-target="#flush-collapseMarcas"
                                aria-expanded="true" aria-controls="flush-collapseMarcas">
                                <span class="text-uppercase fs-12 fw-medium stock-text-muted">Marcas</span>
                                @if (count($marcasFiltro) > 0)
                                    <span class="badge rounded-pill align-middle ms-1 stock-text-accent">{{ count($marcasFiltro) }}</span>
                                @endif
                            </button>
                        </h2>
                        <div id="flush-collapseMarcas" class="accordion-collapse collapse show"
                            aria-labelledby="flush-headingMarcas">
                            <div class="accordion-body text-body pt-0">
                                <div class="search-box search-box-sm stock-buscador">
                                    <input type="text" class="form-control"
                                        placeholder="Buscar marcas..." wire:model.live.debounce.300ms="buscarMarca">
                                    <i class="ri-search-line search-icon"></i>
                                </div>
                                <div class="d-flex flex-column gap-2 mt-3 filter-check">
                                    @forelse ($marcasFiltroLista as $marca)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                value="{{ $marca['id'] }}" id="marca-filtro-{{ $marca['id'] }}"
                                                {{ $marca['activa'] ? 'checked' : '' }}
                                                wire:change="toggleMarca({{ $marca['id'] }})">
                                            <label class="form-check-label d-flex w-100 justify-content-between"
                                                for="marca-filtro-{{ $marca['id'] }}">
                                                <span class="text-truncate">{{ $marca['nombre'] }}</span>
                                                <span class="badge rounded-pill ms-2" style="background: var(--marca-suave); color: var(--marca-apagado);">{{ $marca['total'] }}</span>
                                            </label>
                                        </div>
                                    @empty
                                        <p class="text-muted fs-13 mb-0">Sin marcas con ese nombre.</p>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Estado del stock --}}
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="flush-headingEstado">
                            <button class="accordion-button bg-transparent shadow-none {{ $filtroEstado === 'todos' ? 'collapsed' : '' }}"
                                type="button" data-bs-toggle="collapse" data-bs-target="#flush-collapseEstado"
                                aria-expanded="{{ $filtroEstado === 'todos' ? 'false' : 'true' }}"
                                aria-controls="flush-collapseEstado">
                                <span class="text-uppercase fs-12 fw-medium" style="color: var(--marca-apagado);">Estado del stock</span>
                            </button>
                        </h2>
                        <div id="flush-collapseEstado" class="accordion-collapse collapse {{ $filtroEstado === 'todos' ? '' : 'show' }}"
                            aria-labelledby="flush-headingEstado">
                            <div class="accordion-body text-body">
                                <div class="d-flex flex-column gap-2 filter-check">
                                    @foreach (['con_stock' => 'Con stock', 'agotados' => 'Agotados', 'bajo_minimo' => 'Bajo mínimo'] as $valor => $etiqueta)
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="{{ $valor }}"
                                                id="estado-filtro-{{ $valor }}"
                                                {{ $filtroEstado === $valor ? 'checked' : '' }}
                                                wire:click="setEstado('{{ $valor }}')">
                                            <label class="form-check-label" for="estado-filtro-{{ $valor }}">{{ $etiqueta }}</label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <!-- end col -->

        {{-- ===================== Contenido ===================== --}}
        <div class="col-xl-9 col-lg-8">
            <div class="card stock-contenido">
                <div class="card-header stock-contenido-header">
                    <div class="row g-3 align-items-center">
                        <div class="col-sm-auto">
                            <div>
                                <h5 class="mb-0 stock-contenido-titulo">
                                    Stock por {{ $vista === 'categorias' ? 'categoría' : 'marca' }}
                                    <span class="spinner-border spinner-border-sm align-middle"
                                        style="color: var(--marca-azul-texto);"
                                        role="status" wire:loading.delay>
                                        <span class="visually-hidden">Cargando...</span>
                                    </span>
                                </h5>
                                <small class="fs-13" style="color: var(--marca-apagado);">
                                    {{ $resumen['conStock'] }} {{ $resumen['conStock'] === 1 ? 'producto con existencias' : 'productos con existencias' }}
                                    de {{ $resumen['productos'] }} {{ $resumen['productos'] === 1 ? 'activo' : 'activos' }}
                                    @if ($buscar !== '' || $filtroEstado !== 'todos' || $categoriaFiltro !== null || $marcasFiltro !== [])
                                        · con filtros
                                    @endif
                                </small>
                            </div>
                        </div>
                        <div class="col-sm">
                            <div class="d-flex justify-content-sm-end">
                                <div class="search-box stock-buscador ms-2" style="max-width: 20rem">
                                    <input type="text" class="form-control"
                                        placeholder="Buscar producto, SKU o marca..."
                                        wire:model.live.debounce.400ms="buscar">
                                    <i class="ri-search-line search-icon"></i>
                                    @if ($buscar !== '')
                                        <button type="button"
                                            class="btn btn-sm btn-link text-muted position-absolute end-0 top-50 translate-middle-y me-1 p-1"
                                            wire:click="$set('buscar', '')" title="Limpiar búsqueda">
                                            <i class="ri-close-circle-fill fs-14"></i>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-header" style="border-bottom: 1px solid var(--marca-suave);">
                    <div class="row align-items-center">
                        <div class="col">
                            <ul class="nav nav-tabs-custom card-header-tabs border-bottom-0 stock-tabs" role="tablist">
                                <li class="nav-item">
                                    <button type="button"
                                        class="nav-link {{ $vista === 'categorias' ? 'active' : '' }} fw-semibold"
                                        wire:click="cambiarVista('categorias')" role="tab">
                                        <i class="ri-folder-2-line align-middle me-1"></i>Por categorías
                                        <span class="badge rounded-pill align-middle ms-1" style="background: rgba(37, 73, 112, .12); color: var(--marca-azul-texto);">{{ count($categorias) }}</span>
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button type="button"
                                        class="nav-link {{ $vista === 'marcas' ? 'active' : '' }} fw-semibold"
                                        wire:click="cambiarVista('marcas')" role="tab">
                                        <i class="ri-trademark-line align-middle me-1"></i>Por marcas
                                        <span class="badge rounded-pill align-middle ms-1" style="background: rgba(37, 73, 112, .12); color: var(--marca-azul-texto);">{{ count($marcas) }}</span>
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="card-body" wire:loading.class="opacity-50"
                    wire:target="buscar, filtroEstado, categoriaFiltro, marcasFiltro, cambiarVista, setEstado, cambiarCategoria, toggleMarca">

                    @if ($resumen['productos'] === 0)
                        <div class="text-center py-5">
                            <div class="avatar-lg mx-auto mb-4">
                                <span class="avatar-title rounded-circle fs-1 shadow-sm" style="background: rgba(37, 73, 112, .12); color: var(--marca-azul-texto);">
                                    <i class="{{ $buscar !== '' || $filtroEstado !== 'todos' || $categoriaFiltro !== null || $marcasFiltro !== [] ? 'ri-search-eye-line' : 'ri-stack-line' }}"></i>
                                </span>
                            </div>
                            @if ($buscar !== '' || $filtroEstado !== 'todos' || $categoriaFiltro !== null || $marcasFiltro !== [])
                                <h5 class="mb-1" style="color: var(--marca-tinta);">Sin resultados con los filtros actuales</h5>
                                <p class="text-muted mb-3">Prueba con otros términos o limpia los filtros.</p>
                                <button type="button" class="btn btn-sm" style="background: var(--marca-suave); color: var(--marca-tinta); border: 1px solid var(--marca-linea);" wire:click="limpiarFiltros">
                                    <i class="ri-close-line align-bottom me-1"></i> Quitar filtros
                                </button>
                            @else
                                <h5 class="mb-1 fw-semibold" style="color: var(--marca-azul-texto);">Todavía no hay productos activos</h5>
                                <p class="text-muted mb-0">
                                    Registra productos y recepciona compras para ver aquí su stock disponible.
                                </p>
                            @endif
                        </div>
                    @elseif ($vista === 'categorias')
                        @foreach ($categorias as $grupo)
                            @include('livewire.stock.partials.categoria-grupo', [
                                'grupo' => $grupo,
                                'nivel' => 0,
                                'clave' => $grupo['categoria'] ? 'cat-'.$grupo['categoria']->id : 'cat-sin',
                                'colapsadas' => $colapsadas,
                            ])
                        @endforeach
                    @else
                        @foreach ($marcas as $grupo)
                            @include('livewire.stock.partials.marca-grupo', [
                                'grupo' => $grupo,
                                'clave' => $grupo['marca'] ? 'marca-'.$grupo['marca']->id : 'marca-sin',
                                'colapsadas' => $colapsadas,
                            ])
                        @endforeach
                    @endif

                    @can('unidades.ver')
                        <div class="text-center mt-4 stock-pista">
                            <i class="ri-box-3-line align-middle me-1"></i>
                            Haz clic en el nombre de un producto para ver sus unidades en el inventario.
                        </div>
                    @endcan

                </div>
            </div>
        </div>
    </div>
</div>
