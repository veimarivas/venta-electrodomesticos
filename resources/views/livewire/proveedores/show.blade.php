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
                            Ficha del proveedor
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-user-star-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1 text-truncate">{{ $proveedor->nombre }}</h4>
                                <p class="text-white-50 mb-0">
                                    @if ($proveedor->nit)
                                        NIT {{ $proveedor->nit }}
                                    @else
                                        Sin NIT registrado
                                    @endif
                                    · {{ $this->totalCompras }} {{ $this->totalCompras === 1 ? 'compra' : 'compras' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                            @can('compras.crear')
                                @if ($proveedor->activo)
                                    <a href="{{ route('compras.index') }}?proveedor={{ $proveedor->id }}"
                                        class="btn btn-light crud-nueva-hero">
                                        <i class="ri-add-line align-bottom me-1"></i> Nueva compra
                                    </a>
                                @else
                                    <button type="button" class="btn btn-light crud-nueva-hero opacity-50" disabled
                                        title="Proveedor inactivo: no se pueden registrar compras">
                                        <i class="ri-add-line align-bottom me-1"></i> Nueva compra
                                    </button>
                                @endif
                            @endcan
                            <a href="{{ route('proveedores.index') }}" class="btn btn-outline-light crud-nueva-hero">
                                <i class="ri-arrow-left-line align-bottom me-1"></i> Volver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4 crud-kpis">
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Compras" value="{{ $this->totalCompras }}" icon="bx-receipt"
                color="primary" caption="Total registradas" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Recepcionadas" value="{{ $this->comprasRecepcionadas }}" icon="bx-check-double"
                color="success" caption="Generaron inventario" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Unidades" value="{{ $this->totalUnidades }}" icon="bx-barcode"
                color="info" caption="Creadas por sus compras" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Invertido" value="Bs {{ $this->totalInvertido }}" icon="bx-wallet"
                color="warning" caption="Suma de todas las compras" />
        </div>
    </div>

    {{-- ===================== Datos del proveedor ===================== --}}
    <div class="card border-0 shadow-sm mb-4 crud-listado">
        <div class="card-header bg-transparent py-3 crud-toolbar">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                <i class="ri-building-line"></i> Datos de la empresa
            </h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="d-flex align-items-start gap-2 proveedor-dato">
                        <span class="avatar-xs flex-shrink-0">
                            <span class="avatar-title rounded-circle proveedor-dato-icon">
                                <i class="ri-building-line"></i>
                            </span>
                        </span>
                        <div class="min-w-0">
                            <small class="text-muted d-block">Nombre o razón social</small>
                            <strong class="d-block text-truncate">{{ $proveedor->nombre }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start gap-2 proveedor-dato">
                        <span class="avatar-xs flex-shrink-0">
                            <span class="avatar-title rounded-circle proveedor-dato-icon">
                                <i class="ri-file-list-3-line"></i>
                            </span>
                        </span>
                        <div class="min-w-0">
                            <small class="text-muted d-block">NIT</small>
                            <strong class="d-block">{{ $proveedor->nit ?: '—' }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start gap-2 proveedor-dato">
                        <span class="avatar-xs flex-shrink-0">
                            <span class="avatar-title rounded-circle proveedor-dato-icon">
                                <i class="ri-map-pin-line"></i>
                            </span>
                        </span>
                        <div class="min-w-0">
                            <small class="text-muted d-block">Dirección</small>
                            <strong class="d-block">{{ $proveedor->direccion ?: 'Sin dirección' }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start gap-2 proveedor-dato">
                        <span class="avatar-xs flex-shrink-0">
                            <span class="avatar-title rounded-circle proveedor-dato-icon">
                                <i class="ri-user-line"></i>
                            </span>
                        </span>
                        <div class="min-w-0">
                            <small class="text-muted d-block">Contacto</small>
                            <strong class="d-block">{{ $proveedor->contacto ?: '—' }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start gap-2 proveedor-dato">
                        <span class="avatar-xs flex-shrink-0">
                            <span class="avatar-title rounded-circle proveedor-dato-icon">
                                <i class="ri-phone-line"></i>
                            </span>
                        </span>
                        <div class="min-w-0">
                            <small class="text-muted d-block">Teléfono</small>
                            <strong class="d-block">{{ $proveedor->telefono ?: '—' }}</strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-start gap-2 proveedor-dato">
                        <span class="avatar-xs flex-shrink-0">
                            <span class="avatar-title rounded-circle proveedor-dato-icon">
                                <i class="ri-mail-line"></i>
                            </span>
                        </span>
                        <div class="min-w-0">
                            <small class="text-muted d-block">Correo</small>
                            <strong class="d-block text-truncate">{{ $proveedor->correo ?: '—' }}</strong>
                        </div>
                    </div>
                </div>
                @if ($proveedor->notas)
                    <div class="col-12">
                        <div class="d-flex align-items-start gap-2 proveedor-dato">
                            <span class="avatar-xs flex-shrink-0">
                                <span class="avatar-title rounded-circle proveedor-dato-icon">
                                    <i class="ri-sticky-note-line"></i>
                                </span>
                            </span>
                            <div class="min-w-0">
                                <small class="text-muted d-block">Notas</small>
                                <p class="mb-0">{{ $proveedor->notas }}</p>
                            </div>
                        </div>
                    </div>
                @endif
                <div class="col-12">
                    <span class="proveedor-estado {{ $proveedor->activo ? 'proveedor-estado-activo' : 'proveedor-estado-inactivo' }}">
                        <span class="proveedor-estado-dot"></span>
                        {{ $proveedor->activo ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Historial de compras ===================== --}}
    <div class="card border-0 shadow-sm crud-listado">
        <div class="card-header bg-transparent py-3 crud-toolbar">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                    <i class="ri-shopping-bag-3-line"></i> Historial de compras
                </h5>
                @can('compras.crear')
                    @if ($proveedor->activo)
                        <a href="{{ route('compras.index') }}?proveedor={{ $proveedor->id }}"
                            class="btn btn-success btn-sm rounded-pill shadow-sm">
                            <i class="ri-add-line align-bottom me-1"></i> Nueva compra con este proveedor
                        </a>
                    @endif
                @endcan
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-crud border-top">
                    <thead class="table-light">
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4">Compra</th>
                            <th scope="col">Fecha</th>
                            <th scope="col" class="text-center">Líneas</th>
                            <th scope="col" class="text-center">Unidades</th>
                            <th scope="col" class="text-end">Total</th>
                            <th scope="col" class="text-center">Estado</th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->compras as $compra)
                            <tr wire:key="compra-{{ $compra->id }}">
                                <td class="ps-4">
                                    <span class="badge bg-primary-subtle text-primary fs-12 font-monospace">
                                        {{ $compra->codigo }}
                                    </span>
                                    @if ($compra->numero_factura)
                                        <small class="text-muted d-block mt-1">Fact. {{ $compra->numero_factura }}</small>
                                    @endif
                                </td>
                                <td>{{ $compra->fecha_compra->format('d/m/Y') }}</td>
                                <td class="text-center">
                                    <span class="badge bg-light text-body border">{{ $compra->detalles_count }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($compra->unidades_count > 0)
                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                            <i class="ri-barcode-line align-middle me-1"></i>{{ $compra->unidades_count }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end col-importe fw-semibold">
                                    Bs {{ number_format((float) $compra->total, 2) }}
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill
                                        @if ($compra->estado === 'borrador') bg-warning-subtle text-warning border border-warning-subtle
                                        @elseif ($compra->estado === 'recepcionada') bg-success-subtle text-success border border-success-subtle
                                        @else bg-secondary-subtle text-secondary border @endif">
                                        {{ \App\Models\Compra::ESTADOS[$compra->estado] }}
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('compras.index') }}?abrir={{ $compra->id }}"
                                        class="btn btn-sm btn-ghost-info btn-icon rounded-circle crud-accion-ver"
                                        title="Ver compra" aria-label="Ver la compra {{ $compra->codigo }}">
                                        <i class="ri-eye-line fs-16"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-4">
                                            <div class="avatar-title bg-light text-primary rounded-circle fs-1 shadow-sm">
                                                <i class="ri-shopping-bag-3-line"></i>
                                            </div>
                                        </div>
                                        <h5 class="mb-1 fw-semibold text-primary">Todavía no hay compras</h5>
                                        <p class="text-muted mb-3">Este proveedor aún no ha registrado ninguna compra.</p>
                                        @can('compras.crear')
                                            <a href="{{ route('compras.index') }}?proveedor={{ $proveedor->id }}" class="btn btn-success btn-sm rounded-pill shadow-sm">
                                                <i class="ri-add-line align-bottom me-1"></i> Registrar compra
                                            </a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>