<div class="compras-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-shopping-bag-3-line me-1"></i>
                            Compras · Órdenes
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-shopping-bag-3-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Órdenes de compra</h4>
                                <p class="text-white-50 mb-0">
                                    Al recepcionar una compra se generan las unidades físicas con su costo real.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('compras.crear')
                                <button type="button" class="btn btn-light crud-nueva-hero"
                                    wire:click="abrirCrear">
                                    <i class="ri-add-line align-bottom me-1"></i> Nueva compra
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Banner de contexto del proveedor ===================== --}}
    @if ($proveedorContexto)
        <div class="compras-proveedor-banner mb-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <div class="compras-proveedor-avatar flex-shrink-0">
                        <span class="compras-proveedor-iniciales">{{ $proveedorContexto->iniciales }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <h5 class="mb-0">{{ $proveedorContexto->nombre }}</h5>
                            <span class="compras-proveedor-pill">
                                <i class="ri-truck-line me-1"></i>Proveedor
                            </span>
                            @if ($proveedorContexto->nit)
                                <span class="compras-proveedor-pill compras-proveedor-pill-nit">
                                    NIT {{ $proveedorContexto->nit }}
                                </span>
                            @endif
                        </div>
                        @if ($proveedorContexto->contacto || $proveedorContexto->telefono)
                            <small class="text-muted d-block mt-1">
                                @if ($proveedorContexto->contacto) {{ $proveedorContexto->contacto }} @endif
                                @if ($proveedorContexto->contacto && $proveedorContexto->telefono) · @endif
                                @if ($proveedorContexto->telefono) {{ $proveedorContexto->telefono }} @endif
                            </small>
                        @endif
                    </div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    @can('compras.crear')
                        <button type="button" class="btn btn-success btn-sm rounded-pill shadow-sm"
                            wire:click="abrirCrear">
                            <i class="ri-add-line align-bottom me-1"></i> Nueva compra con este proveedor
                        </button>
                    @endcan
                    @can('proveedores.ver')
                        <a href="{{ route('proveedores.show', $proveedorContexto) }}"
                            class="btn btn-outline-secondary btn-sm rounded-pill">
                            <i class="ri-eye-line align-bottom me-1"></i> Ver ficha completa
                        </a>
                    @endcan
                    <a href="{{ route('compras.index') }}" wire:click.prevent="quitarFiltroProveedor"
                        class="btn btn-light btn-sm rounded-pill border">
                        <i class="ri-close-line align-bottom me-1"></i> Ver todas
                    </a>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4 crud-kpis">
        <div class="col-xl-4 col-md-6">
            <x-stat-card
                label="{{ $proveedorContexto ? 'Compras de este proveedor' : 'Compras registradas' }}"
                value="{{ $totalCompras }}"
                icon="bx-receipt"
                color="primary"
                caption="{{ $proveedorContexto ? 'Historial con ' . $proveedorContexto->nombre : 'Total histórico' }}" />
        </div>
        <div class="col-xl-4 col-md-6">
            <x-stat-card label="En borrador" value="{{ $enBorrador }}" icon="bx-edit"
                color="warning" caption="Pendientes de recepcionar" />
        </div>
        <div class="col-xl-4 col-md-6">
            <x-stat-card label="Invertido este mes" value="Bs {{ number_format((float) $invertidoMes, 2) }}"
                icon="bx-wallet" color="info" caption="{{ ucfirst(now()->translatedFormat('F Y')) }}" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm crud-listado">
        <div class="card-header bg-transparent py-3 crud-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Compras registradas
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $compras->total() }} {{ $compras->total() === 1 ? 'compra' : 'compras' }}
                        @if ($buscar !== '')
                            para «{{ $buscar }}»
                        @endif
                    </small>
                </div>

                <div class="col-md-5">
                    <div class="search-box">
                        <input type="text" class="form-control crud-busqueda"
                            placeholder="Buscar por código, factura o proveedor..."
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
                        @foreach (\App\Models\Compra::ESTADOS as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
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
                            <th scope="col" class="ps-4">Compra</th>
                            <th scope="col">Proveedor</th>
                            <th scope="col" style="cursor:pointer" wire:click="ordenar('fecha_compra')">
                                Fecha
                                @if ($ordenarPor === 'fecha_compra')
                                    <i class="ri-arrow-{{ $direccionOrden === 'asc' ? 'up' : 'down' }}-line align-middle"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-center">Líneas</th>
                            <th scope="col" class="text-center">Unidades</th>
                            <th scope="col" class="text-end" style="cursor:pointer" wire:click="ordenar('total')">
                                Total
                                @if ($ordenarPor === 'total')
                                    <i class="ri-arrow-{{ $direccionOrden === 'asc' ? 'up' : 'down' }}-line align-middle"></i>
                                @endif
                            </th>
                            <th scope="col" class="text-center">Estado</th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($compras as $unidad)
                            <tr wire:key="compra-{{ $unidad->id }}"
                                class="{{ $detalleCompraId === $unidad->id ? 'fila-seleccionada' : '' }}">
                                <td class="ps-4">
                                    <span class="compra-codigo">{{ $unidad->codigo }}</span>
                                    @if ($unidad->numero_factura)
                                        <small class="text-muted d-block mt-1">Fact. {{ $unidad->numero_factura }}</small>
                                    @endif
                                </td>

                                <td>
                                    <div class="text-truncate">{{ $unidad->proveedor->nombre }}</div>
                                    <small class="text-muted">Registró {{ $unidad->user->name }}</small>
                                </td>

                                <td>{{ $unidad->fecha_compra->format('d/m/Y') }}</td>

                                <td class="text-center">
                                    <span class="badge bg-light text-body border">{{ $unidad->detalles_count }}</span>
                                </td>

                                <td class="text-center">
                                    @if ($unidad->unidades_count > 0)
                                        <span class="compra-unidades">
                                            <i class="ri-barcode-line align-middle me-1"></i>{{ $unidad->unidades_count }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td class="text-end col-importe fw-semibold">
                                    Bs {{ number_format((float) $unidad->total, 2) }}
                                </td>

                                <td class="text-center">
                                    <span class="compra-estado {{ $unidad->estado === 'borrador' ? 'compra-estado-borrador' : ($unidad->estado === 'recepcionada' ? 'compra-estado-recepcionada' : 'compra-estado-anulada') }}">
                                        <span class="compra-estado-dot"></span>
                                        {{ \App\Models\Compra::ESTADOS[$unidad->estado] }}
                                    </span>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('compras.show', $unidad) }}"
                                            class="btn btn-sm btn-ghost-info btn-icon rounded-circle crud-accion-ver"
                                            title="Ver detalle"
                                            aria-label="Ver el detalle de {{ $unidad->codigo }}">
                                            <i class="ri-eye-line fs-16"></i>
                                        </a>

                                        @if ($unidad->es_borrador)
                                            @can('compras.eliminar')
                                                <button type="button" class="btn btn-sm btn-ghost-danger btn-icon rounded-circle crud-accion-eliminar"
                                                    wire:click="confirmarEliminar({{ $unidad->id }})" title="Eliminar"
                                                    aria-label="Eliminar {{ $unidad->codigo }}">
                                                    <i class="ri-delete-bin-line fs-16"></i>
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">
                                    <div class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-4">
                                            <div class="avatar-title bg-light text-primary rounded-circle fs-1 shadow-sm">
                                                <i class="{{ $buscar !== '' ? 'ri-search-eye-line' : 'ri-shopping-bag-3-line' }}"></i>
                                            </div>
                                        </div>
                                        @if ($buscar !== '' || $filtroEstado !== 'todos')
                                            <h5 class="mb-1">Sin resultados</h5>
                                            <p class="text-muted mb-3">Prueba a quitar los filtros o cambiar la búsqueda.</p>
                                        @else
                                            <h5 class="mb-1 fw-semibold text-primary">Todavía no hay compras</h5>
                                            <p class="text-muted mb-3">
                                                Registra la primera para empezar a llenar el inventario.
                                            </p>
                                            @can('compras.crear')
                                                <button type="button" class="btn btn-success btn-sm rounded-pill shadow-sm"
                                                    wire:click="abrirCrear">
                                                    <i class="ri-add-line align-bottom me-1"></i> Nueva compra
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

        @if ($compras->hasPages())
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando {{ $compras->firstItem() }}-{{ $compras->lastItem() }} de {{ $compras->total() }}
                    </p>
                    <div class="crud-paginacion">
                        {{ $compras->onEachSide(1)->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>

    @include('livewire.compras.partials.modal-compra')
    @include('livewire.compras.partials.modal-seriales')
    @include('livewire.compras.partials.modal-eliminar')
</div>
