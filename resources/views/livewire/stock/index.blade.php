<div class="stock-modulo">

    {{-- ===================== Hero ===================== --}}
    <header class="stock-hero mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="min-w-0">
                <h1 class="stock-hero-titulo">Stock actual</h1>
                <p class="stock-hero-sub">Inventario disponible en tiempo real, agrupado por categoría o por marca.</p>
            </div>
            <span class="stock-hero-badge">
                <i class="ri-stack-line"></i> {{ $resumen['productos'] }} productos activos
            </span>
        </div>
    </header>

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <button type="button" class="stock-kpi {{ $filtroEstado === 'con_stock' ? 'is-activo' : '' }}"
                wire:click="setEstado('con_stock')"
                aria-pressed="{{ $filtroEstado === 'con_stock' ? 'true' : 'false' }}"
                title="Ver los productos con existencias">
                <span class="stock-kpi-icono stock-kpi-icono--stock"><i class="ri-archive-2-line"></i></span>
                <span class="stock-kpi-cuerpo">
                    <span class="stock-kpi-label">Unidades en stock</span>
                    <span class="stock-kpi-valor">{{ number_format($resumen['unidades'], 0, ',', '.') }}</span>
                    <span class="stock-kpi-nota">Listas para vender</span>
                </span>
                <i class="ri-arrow-right-up-line stock-kpi-flecha" aria-hidden="true"></i>
            </button>
        </div>

        <div class="col-6 col-xl-3">
            <div class="stock-kpi stock-kpi--estatico">
                <span class="stock-kpi-icono stock-kpi-icono--valor"><i class="ri-wallet-2-line"></i></span>
                <span class="stock-kpi-cuerpo">
                    <span class="stock-kpi-label">Valor de inventario</span>
                    <span class="stock-kpi-valor">Bs {{ number_format($resumen['valor'], 2, ',', '.') }}</span>
                    <span class="stock-kpi-nota">Unidades × precio de venta</span>
                </span>
            </div>
        </div>

        <div class="col-6 col-xl-3">
            <button type="button" class="stock-kpi {{ $filtroEstado === 'agotados' ? 'is-activo' : '' }}"
                wire:click="setEstado('agotados')"
                aria-pressed="{{ $filtroEstado === 'agotados' ? 'true' : 'false' }}"
                title="Ver los productos agotados">
                <span class="stock-kpi-icono stock-kpi-icono--agotados"><i class="ri-close-circle-line"></i></span>
                <span class="stock-kpi-cuerpo">
                    <span class="stock-kpi-label">Productos agotados</span>
                    <span class="stock-kpi-valor {{ $resumen['agotados'] > 0 ? 'stock-kpi-valor--peligro' : '' }}">{{ $resumen['agotados'] }}</span>
                    <span class="stock-kpi-nota">Sin existencias</span>
                </span>
                <i class="ri-arrow-right-up-line stock-kpi-flecha" aria-hidden="true"></i>
            </button>
        </div>

        <div class="col-6 col-xl-3">
            <button type="button" class="stock-kpi {{ $filtroEstado === 'bajo_minimo' ? 'is-activo' : '' }}"
                wire:click="setEstado('bajo_minimo')"
                aria-pressed="{{ $filtroEstado === 'bajo_minimo' ? 'true' : 'false' }}"
                title="Ver los productos bajo el mínimo">
                <span class="stock-kpi-icono stock-kpi-icono--alerta"><i class="ri-alert-line"></i></span>
                <span class="stock-kpi-cuerpo">
                    <span class="stock-kpi-label">Bajo stock mínimo</span>
                    <span class="stock-kpi-valor {{ $resumen['bajoMinimo'] > 0 ? 'stock-kpi-valor--alerta' : '' }}">{{ $resumen['bajoMinimo'] }}</span>
                    <span class="stock-kpi-nota">Requieren reposición</span>
                </span>
                <i class="ri-arrow-right-up-line stock-kpi-flecha" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 g-xl-4">

        {{-- ===================== Filtros: escritorio ===================== --}}
        <div class="col-xl-3 d-none d-xl-block">
            <aside class="stock-filtros card">
                <div class="card-header stock-filtros-cabecera">
                    <h5 class="stock-filtros-titulo">
                        <i class="ri-equalizer-line"></i> Filtros
                        @if ($filtrosActivos > 0)
                            <span class="stock-filtros-contador">{{ $filtrosActivos }}</span>
                        @endif
                    </h5>
                    <button type="button" class="stock-limpiar" wire:click="limpiarFiltros"
                        @disabled($filtrosActivos === 0)>
                        <i class="ri-close-circle-line"></i> Limpiar
                    </button>
                </div>
                <div class="card-body stock-filtros-cuerpo">
                    @include('livewire.stock.partials.filtros', ['contexto' => 'escritorio'])
                </div>
            </aside>
        </div>

        {{-- ===================== Contenido ===================== --}}
        <div class="col-xl-9">
            <div class="card stock-contenido">

                {{-- Filtros: móvil y tablet --}}
                <div class="stock-filtros-movil d-xl-none">
                    <button type="button" class="stock-filtros-toggle {{ $filtrosAbiertos ? 'is-abierto' : '' }}"
                        wire:click="toggleFiltros"
                        aria-expanded="{{ $filtrosAbiertos ? 'true' : 'false' }}"
                        aria-controls="stockFiltrosPanel">
                        <i class="ri-equalizer-line"></i>
                        <span class="stock-filtros-toggle-texto">Filtros</span>
                        @if ($filtrosActivos > 0)
                            <span class="stock-filtros-contador">{{ $filtrosActivos }}</span>
                        @endif
                        <i class="ri-arrow-down-s-line stock-filtros-toggle-chevron ms-auto" aria-hidden="true"></i>
                    </button>

                    @if ($filtrosAbiertos)
                        <div id="stockFiltrosPanel" class="stock-filtros-panel">
                            @include('livewire.stock.partials.filtros', ['contexto' => 'movil'])
                            <button type="button" class="stock-limpiar stock-limpiar--bloque mt-3"
                                wire:click="limpiarFiltros" @disabled($filtrosActivos === 0)>
                                <i class="ri-close-circle-line"></i> Limpiar filtros
                            </button>
                        </div>
                    @endif
                </div>

                {{-- Encabezado y buscador --}}
                <div class="stock-contenido-header">
                    <div class="stock-contenido-titulo">
                        <h5 class="stock-contenido-titulo-texto">
                            Stock por {{ $vista === 'categorias' ? 'categoría' : 'marca' }}
                            <span class="spinner-border spinner-border-sm stock-spinner" role="status" wire:loading.delay>
                                <span class="visually-hidden">Cargando...</span>
                            </span>
                        </h5>
                        <p class="stock-contenido-subtitulo">
                            {{ $resumen['conStock'] }} {{ $resumen['conStock'] === 1 ? 'producto con existencias' : 'productos con existencias' }}
                            de {{ $resumen['productos'] }} {{ $resumen['productos'] === 1 ? 'activo' : 'activos' }}
                            @if ($filtrosActivos > 0)
                                · con filtros aplicados
                            @endif
                        </p>
                    </div>

                    <div class="search-box stock-buscador">
                        <input type="text" class="form-control" placeholder="Buscar producto o marca..."
                            wire:model.live.debounce.400ms="buscar">
                        <i class="ri-search-line search-icon"></i>
                        @if ($buscar !== '')
                            <button type="button" class="stock-buscador-limpiar"
                                wire:click="$set('buscar', '')" title="Limpiar búsqueda"
                                aria-label="Limpiar búsqueda">
                                <i class="ri-close-circle-fill"></i>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Vistas y estado --}}
                <div class="stock-toolbar">
                    <ul class="nav stock-tabs" role="tablist">
                        <li class="nav-item">
                            <button type="button"
                                class="nav-link {{ $vista === 'categorias' ? 'active' : '' }}"
                                wire:click="cambiarVista('categorias')" role="tab"
                                aria-selected="{{ $vista === 'categorias' ? 'true' : 'false' }}">
                                <i class="ri-folder-2-line"></i> Por categorías
                                <span class="stock-tabs-contador">{{ count($categorias) }}</span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button"
                                class="nav-link {{ $vista === 'marcas' ? 'active' : '' }}"
                                wire:click="cambiarVista('marcas')" role="tab"
                                aria-selected="{{ $vista === 'marcas' ? 'true' : 'false' }}">
                                <i class="ri-trademark-line"></i> Por marcas
                                <span class="stock-tabs-contador">{{ count($marcas) }}</span>
                            </button>
                        </li>
                    </ul>

                    <div class="stock-segmento" role="group" aria-label="Filtrar por estado del stock">
                        @foreach ([
                            'todos' => ['Todos', 'ri-layout-grid-line', ''],
                            'con_stock' => ['Con stock', 'ri-checkbox-circle-line', 'is-ok'],
                            'bajo_minimo' => ['Bajo mínimo', 'ri-alert-line', 'is-alerta'],
                            'agotados' => ['Agotados', 'ri-close-circle-line', 'is-peligro'],
                        ] as $valor => [$etiqueta, $icono, $tono])
                            <button type="button"
                                class="stock-segmento-btn {{ $tono }} {{ $filtroEstado === $valor ? 'is-activo' : '' }}"
                                wire:click="setEstado('{{ $valor }}')"
                                aria-pressed="{{ $filtroEstado === $valor ? 'true' : 'false' }}">
                                <i class="{{ $icono }}"></i>
                                <span>{{ $etiqueta }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Chips de filtros activos --}}
                @php
                    $categoriaActiva = $categoriaFiltro !== null
                        ? collect($categoriasFiltro)->firstWhere('id', $categoriaFiltro)
                        : null;
                    $marcasActivas = collect($marcasFiltroLista)->whereIn('id', $marcasFiltro);
                    $estadoEtiquetas = [
                        'con_stock' => 'Con stock',
                        'bajo_minimo' => 'Bajo mínimo',
                        'agotados' => 'Agotados',
                    ];
                @endphp

                @if ($filtrosActivos > 0)
                    <div class="stock-chips">
                        <span class="stock-chips-label">Filtros activos</span>

                        @if ($buscar !== '')
                            <button type="button" class="stock-chip-activo" wire:click="$set('buscar', '')">
                                <i class="ri-search-line"></i>
                                <span>{{ $buscar }}</span>
                                <i class="ri-close-line stock-chip-activo-x" aria-hidden="true"></i>
                            </button>
                        @endif

                        @if ($categoriaActiva)
                            <button type="button" class="stock-chip-activo"
                                wire:click="cambiarCategoria({{ $categoriaActiva['id'] }})">
                                <i class="ri-folder-2-line"></i>
                                <span>{{ $categoriaActiva['nombre'] }}</span>
                                <i class="ri-close-line stock-chip-activo-x" aria-hidden="true"></i>
                            </button>
                        @endif

                        @foreach ($marcasActivas as $marca)
                            <button type="button" class="stock-chip-activo"
                                wire:click="toggleMarca({{ $marca['id'] }})">
                                <i class="ri-trademark-line"></i>
                                <span>{{ $marca['nombre'] }}</span>
                                <i class="ri-close-line stock-chip-activo-x" aria-hidden="true"></i>
                            </button>
                        @endforeach

                        @if ($filtroEstado !== 'todos')
                            <button type="button" class="stock-chip-activo"
                                wire:click="setEstado('{{ $filtroEstado }}')">
                                <i class="ri-pulse-line"></i>
                                <span>{{ $estadoEtiquetas[$filtroEstado] }}</span>
                                <i class="ri-close-line stock-chip-activo-x" aria-hidden="true"></i>
                            </button>
                        @endif

                        <button type="button" class="stock-chips-limpiar" wire:click="limpiarFiltros">
                            Limpiar todo
                        </button>
                    </div>
                @endif

                {{-- Listado --}}
                <div class="stock-contenido-cuerpo" wire:loading.class="is-cargando"
                    wire:target="buscar, buscarMarca, filtroEstado, categoriaFiltro, marcasFiltro, cambiarVista, setEstado, cambiarCategoria, toggleMarca">

                    @if ($resumen['productos'] === 0)
                        <div class="stock-vacio">
                            <span class="stock-vacio-icono">
                                <i class="{{ $filtrosActivos > 0 ? 'ri-search-eye-line' : 'ri-stack-line' }}"></i>
                            </span>
                            @if ($filtrosActivos > 0)
                                <h5 class="stock-vacio-titulo">Sin resultados con los filtros actuales</h5>
                                <p class="stock-vacio-texto">Prueba con otros términos o quita los filtros para ver todo el inventario.</p>
                                <button type="button" class="btn btn-primary btn-sm" wire:click="limpiarFiltros">
                                    <i class="ri-refresh-line align-bottom me-1"></i> Quitar filtros
                                </button>
                            @else
                                <h5 class="stock-vacio-titulo">Todavía no hay productos activos</h5>
                                <p class="stock-vacio-texto">
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
                        @if ($resumen['productos'] > 0)
                            <div class="stock-pista">
                                <i class="ri-box-3-line"></i>
                                Haz clic en el nombre de un producto para ver sus unidades en el inventario.
                            </div>
                        @endif
                    @endcan

                </div>
            </div>
        </div>
    </div>
</div>
