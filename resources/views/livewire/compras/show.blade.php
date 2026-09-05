<div class="compras-show-modulo">

    {{-- Hero --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero compras-show-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="compras-show-hero-ring" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-shopping-bag-3-line me-1"></i>
                            Compras · Detalle
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="compras-show-hero-avatar flex-shrink-0">
                                <i class="{{ $compra->es_borrador ? 'ri-draft-line' : ($compra->esta_recepcionada ? 'ri-checkbox-circle-line' : 'ri-close-circle-line') }}"></i>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1 d-flex align-items-center gap-2 flex-wrap">
                                    <span class="font-monospace">{{ $compra->codigo }}</span>
                                    <span class="compra-estado {{ $compra->es_borrador ? 'compra-estado-borrador' : ($compra->esta_recepcionada ? 'compra-estado-recepcionada' : 'compra-estado-anulada') }}">
                                        <span class="compra-estado-dot"></span>
                                        {{ \App\Models\Compra::ESTADOS[$compra->estado] }}
                                    </span>
                                </h4>
                                <p class="text-white-50 mb-0">
                                    {{ $compra->proveedor->nombre }} · {{ $compra->fecha_compra->format('d/m/Y') }}
                                    @if ($compra->numero_factura) · Factura {{ $compra->numero_factura }} @endif
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                            @if ($compra->esta_recepcionada)
                                @can('unidades.ver')
                                    <a href="{{ route('etiquetas.compra', $compra) }}" target="_blank"
                                        class="btn btn-sm btn-light">
                                        <i class="ri-price-tag-3-line align-bottom me-1"></i> Imprimir etiquetas
                                    </a>
                                @endcan
                            @endif
                            <a href="{{ route('compras.index') }}{{ request('proveedor') ? '?proveedor='.$compra->proveedor_id : '' }}"
                                class="btn btn-sm btn-outline-light">
                                <i class="ri-arrow-left-line align-bottom me-1"></i> Volver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    {{-- KPIs --}}
    @php $r = $this->rentabilidad; @endphp
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="compras-show-kpi-card">
                <div class="compras-show-kpi-icon compras-show-kpi-icon-primary">
                    <i class="ri-wallet-3-line"></i>
                </div>
                <div class="compras-show-kpi-body">
                    <small class="compras-show-kpi-label">Total invertido</small>
                    <h3 class="compras-show-kpi-value">Bs {{ number_format((float) $compra->total, 2) }}</h3>
                    <small class="compras-show-kpi-caption">Monto de la compra</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="compras-show-kpi-card">
                <div class="compras-show-kpi-icon compras-show-kpi-icon-info">
                    <i class="ri-box-3-line"></i>
                </div>
                <div class="compras-show-kpi-body">
                    <small class="compras-show-kpi-label">Productos</small>
                    <h3 class="compras-show-kpi-value">{{ $this->lineas->count() }}</h3>
                    <small class="compras-show-kpi-caption">{{ $this->resumenUnidades['total'] }} unidades generadas</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="compras-show-kpi-card">
                <div class="compras-show-kpi-icon compras-show-kpi-icon-success">
                    <i class="ri-store-2-line"></i>
                </div>
                <div class="compras-show-kpi-body">
                    <small class="compras-show-kpi-label">En stock</small>
                    <h3 class="compras-show-kpi-value">{{ $this->resumenUnidades['en_stock'] }}</h3>
                    <small class="compras-show-kpi-caption">{{ $this->resumenUnidades['vendidas'] }} vendidas</small>
                </div>
            </div>
        </div>
        @if ($compra->esta_recepcionada && $r)
            <div class="col-xl-3 col-md-6">
                <div class="compras-show-kpi-card">
                    <div class="compras-show-kpi-icon compras-show-kpi-icon-accent">
                        <i class="ri-line-chart-line"></i>
                    </div>
                    <div class="compras-show-kpi-body">
                        <small class="compras-show-kpi-label">Ganancia realizada</small>
                        <h3 class="compras-show-kpi-value compras-show-kpi-ganancia">Bs {{ number_format((float) $r['ganancia'], 2) }}</h3>
                        <small class="compras-show-kpi-caption">Margen {{ $r['margen'] }} %</small>
                    </div>
                </div>
            </div>
        @else
            <div class="col-xl-3 col-md-6">
                <div class="compras-show-kpi-card">
                    <div class="compras-show-kpi-icon compras-show-kpi-icon-{{ $compra->es_borrador ? 'warning' : 'info' }}">
                        <i class="ri-information-line"></i>
                    </div>
                    <div class="compras-show-kpi-body">
                        <small class="compras-show-kpi-label">Estado</small>
                        <h3 class="compras-show-kpi-value">{{ \App\Models\Compra::ESTADOS[$compra->estado] }}</h3>
                        <small class="compras-show-kpi-caption">{{ $compra->es_borrador ? 'Pendiente de recepcionar' : 'Recepcionada el '.$compra->recepcionada_en?->format('d/m/Y') }}</small>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- Info cards --}}
    <div class="row g-3 mb-4">
        <div class="col-lg-6">
            <div class="compras-show-info-card">
                <div class="compras-show-info-header">
                    <div class="compras-show-info-icon">
                        <i class="ri-truck-line"></i>
                    </div>
                    <h6 class="mb-0">Proveedor</h6>
                </div>
                <div class="compras-show-info-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="compras-show-proveedor-avatar flex-shrink-0">
                            <span>{{ $compra->proveedor->iniciales }}</span>
                        </div>
                        <div class="min-w-0">
                            <h5 class="mb-0">{{ $compra->proveedor->nombre }}</h5>
                            @if ($compra->proveedor->nit)
                                <small class="text-muted font-monospace">NIT {{ $compra->proveedor->nit }}</small>
                            @endif
                        </div>
                    </div>
                    @if ($compra->proveedor->contacto || $compra->proveedor->telefono || $compra->proveedor->correo)
                        <div class="compras-show-info-datos mt-3">
                            @if ($compra->proveedor->contacto)
                                <div class="compras-show-info-dato">
                                    <i class="ri-user-3-line"></i>
                                    <span>{{ $compra->proveedor->contacto }}</span>
                                </div>
                            @endif
                            @if ($compra->proveedor->telefono)
                                <div class="compras-show-info-dato">
                                    <i class="ri-phone-line"></i>
                                    <span>{{ $compra->proveedor->telefono }}</span>
                                </div>
                            @endif
                            @if ($compra->proveedor->correo)
                                <div class="compras-show-info-dato">
                                    <i class="ri-mail-line"></i>
                                    <span>{{ $compra->proveedor->correo }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="compras-show-info-card">
                <div class="compras-show-info-header">
                    <div class="compras-show-info-icon">
                        <i class="ri-file-list-3-line"></i>
                    </div>
                    <h6 class="mb-0">Resumen financiero</h6>
                </div>
                <div class="compras-show-info-body">
                    <div class="compras-show-fila-resumen">
                        <span class="compras-show-fila-label">Subtotal</span>
                        <span class="compras-show-fila-valor">Bs {{ number_format((float) $compra->subtotal, 2) }}</span>
                    </div>
                    @if ((float) $compra->descuento > 0)
                        <div class="compras-show-fila-resumen">
                            <span class="compras-show-fila-label">Descuento</span>
                            <span class="compras-show-fila-valor text-danger">− Bs {{ number_format((float) $compra->descuento, 2) }}</span>
                        </div>
                    @endif
                    @if ((float) $compra->impuesto > 0)
                        <div class="compras-show-fila-resumen">
                            <span class="compras-show-fila-label">Impuesto</span>
                            <span class="compras-show-fila-valor">Bs {{ number_format((float) $compra->impuesto, 2) }}</span>
                        </div>
                    @endif
                    <div class="compras-show-fila-resumen">
                        <span class="compras-show-fila-label">Flete</span>
                        <span class="compras-show-fila-valor">Bs {{ number_format((float) $compra->flete, 2) }}</span>
                    </div>
                    <div class="compras-show-fila-resumen">
                        <span class="compras-show-fila-label">Otros gastos</span>
                        <span class="compras-show-fila-valor">Bs {{ number_format((float) $compra->otros_gastos, 2) }}</span>
                    </div>
                    <div class="compras-show-fila-total">
                        <span class="compras-show-fila-label fw-bold">Total invertido</span>
                        <span class="compras-show-fila-valor compras-show-total-grande">Bs {{ number_format((float) $compra->total, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Líneas de productos --}}
    <div class="compras-show-seccion mb-4">
        <div class="compras-show-seccion-header">
            <div class="d-flex align-items-center gap-2">
                <div class="compras-show-seccion-icon">
                    <i class="ri-box-3-line"></i>
                </div>
                <h5 class="mb-0">Productos comprados</h5>
                <span class="compras-show-seccion-badge">{{ $this->lineas->count() }}</span>
            </div>
        </div>
        <div class="compras-show-seccion-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-end">Costo unit.</th>
                            <th class="text-end">Subtotal</th>
                            <th class="text-end">Costo real</th>
                            <th class="text-end pe-4">P. venta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->lineas as $linea)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        @if ($linea->producto->imagen)
                                            <div class="compras-show-producto-img flex-shrink-0">
                                                <img src="{{ asset('storage/'.$linea->producto->imagen) }}" alt="{{ $linea->producto->nombre }}">
                                            </div>
                                        @else
                                            <div class="compras-show-producto-img compras-show-producto-placeholder flex-shrink-0">
                                                <i class="ri-image-line"></i>
                                            </div>
                                        @endif
                                        <div class="min-w-0">
                                            <h6 class="mb-0 text-truncate">{{ $linea->producto->nombre }}</h6>

                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="compras-show-cantidad-pill">{{ $linea->cantidad }}</span>
                                </td>
                                <td class="text-end compras-show-col-num">{{ number_format((float) $linea->costo_unitario, 2) }}</td>
                                <td class="text-end compras-show-col-num fw-semibold">Bs {{ number_format((float) $linea->subtotal, 2) }}</td>
                                <td class="text-end compras-show-col-num">
                                    @if ($compra->esta_recepcionada)
                                        <span class="compras-show-costo-real">Bs {{ number_format((float) $linea->costo_real_unitario, 2) }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end compras-show-col-num pe-4">Bs {{ number_format((float) $linea->precio_venta, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="compras-show-empty">
                                        <i class="ri-shopping-basket-line"></i>
                                        <p>Esta compra no tiene productos.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Unidades generadas --}}
    @if ($compra->esta_recepcionada && $this->resumenUnidades['total'] > 0)
        <div class="compras-show-seccion mb-4">
            <div class="compras-show-seccion-header">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 w-100">
                    <div class="d-flex align-items-center gap-2">
                        <div class="compras-show-seccion-icon">
                            <i class="ri-barcode-line"></i>
                        </div>
                        <h5 class="mb-0">Unidades generadas</h5>
                        <span class="compras-show-seccion-badge">{{ $this->resumenUnidades['total'] }}</span>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        @if ($this->resumenUnidades['en_stock'] > 0)
                            <span class="compras-show-status-badge compras-show-status-success">
                                <span class="compras-show-status-dot"></span>
                                {{ $this->resumenUnidades['en_stock'] }} en stock
                            </span>
                        @endif
                        @if ($this->resumenUnidades['vendidas'] > 0)
                            <span class="compras-show-status-badge compras-show-status-info">
                                <span class="compras-show-status-dot"></span>
                                {{ $this->resumenUnidades['vendidas'] }} vendidas
                            </span>
                        @endif
                        @if ($this->resumenUnidades['reservadas'] > 0)
                            <span class="compras-show-status-badge compras-show-status-warning">
                                <span class="compras-show-status-dot"></span>
                                {{ $this->resumenUnidades['reservadas'] }} reservadas
                            </span>
                        @endif
                        @if ($this->resumenUnidades['danadas'] > 0)
                            <span class="compras-show-status-badge compras-show-status-danger">
                                <span class="compras-show-status-dot"></span>
                                {{ $this->resumenUnidades['danadas'] }} dañadas
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="compras-show-seccion-body">
                @foreach ($this->unidadesPorProducto as $producto)
                    <div class="compras-show-unidad-grupo {{ !$loop->last ? 'compras-show-unidad-grupo-border' : '' }}">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="mb-0">{{ $producto['nombre'] }}</h6>

                            </div>
                            <span class="compras-show-unidad-count">{{ $producto['total'] }} unidades</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($producto['unidades'] as $unidad)
                                <div class="compras-show-unidad-chip compras-show-unidad-{{ $unidad->estado }}"
                                    title="{{ $unidad->codigo_interno }} · {{ \App\Models\Unidad::ESTADOS[$unidad->estado] }}">
                                    <span class="compras-show-unidad-codigo">{{ $unidad->codigo_interno }}</span>
                                    <span class="compras-show-unidad-estado-dot"></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Rentabilidad --}}
    @if ($compra->esta_recepcionada && $r)
        <div class="compras-show-seccion mb-4">
            <div class="compras-show-seccion-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="compras-show-seccion-icon">
                        <i class="ri-line-chart-line"></i>
                    </div>
                    <h5 class="mb-0">Rentabilidad</h5>
                </div>
            </div>
            <div class="compras-show-seccion-body">
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="compras-show-renta-card">
                            <div class="compras-show-renta-icon compras-show-renta-icon-primary">
                                <i class="ri-box-3-line"></i>
                            </div>
                            <div class="compras-show-renta-body">
                                <small class="compras-show-renta-label">Unidades generadas</small>
                                <h4 class="compras-show-renta-value">{{ $r['unidades'] }}</h4>
                                <small class="compras-show-renta-sub">{{ $r['vendidas'] }} vendidas · {{ $r['en_stock'] }} en stock</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="compras-show-renta-card">
                            <div class="compras-show-renta-icon compras-show-renta-icon-info">
                                <i class="ri-wallet-line"></i>
                            </div>
                            <div class="compras-show-renta-body">
                                <small class="compras-show-renta-label">Ingreso realizado</small>
                                <h4 class="compras-show-renta-value compras-show-renta-info">Bs {{ number_format((float) $r['ingreso'], 2) }}</h4>
                                <small class="compras-show-renta-sub">{{ $r['recuperado'] }} % de lo invertido</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="compras-show-renta-card">
                            <div class="compras-show-renta-icon compras-show-renta-icon-success">
                                <i class="ri-line-chart-line"></i>
                            </div>
                            <div class="compras-show-renta-body">
                                <small class="compras-show-renta-label">Ganancia realizada</small>
                                <h4 class="compras-show-renta-value compras-show-renta-success">Bs {{ number_format((float) $r['ganancia'], 2) }}</h4>
                                <small class="compras-show-renta-sub">Margen {{ $r['margen'] }} %</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6">
                        <div class="compras-show-renta-card">
                            <div class="compras-show-renta-icon compras-show-renta-icon-warning">
                                <i class="ri-stack-line"></i>
                            </div>
                            <div class="compras-show-renta-body">
                                <small class="compras-show-renta-label">Ganancia potencial</small>
                                <h4 class="compras-show-renta-value compras-show-renta-warning">Bs {{ number_format((float) $r['potencial'], 2) }}</h4>
                                <small class="compras-show-renta-sub">Si se vende lo que queda</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="compras-show-progreso-wrapper">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="compras-show-progreso-label">Recuperación de la inversión</span>
                        <span class="compras-show-progreso-porcentaje">{{ $r['recuperado'] }} %</span>
                    </div>
                    <div class="progress compras-show-progreso-bar" role="progressbar"
                        aria-valuenow="{{ $r['recuperado'] }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar compras-show-progreso-fill" style="width: {{ min($r['recuperado'], 100) }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Notas --}}
    @if ($compra->notas)
        <div class="compras-show-seccion mb-4">
            <div class="compras-show-seccion-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="compras-show-seccion-icon">
                        <i class="ri-sticky-note-line"></i>
                    </div>
                    <h5 class="mb-0">Notas</h5>
                </div>
            </div>
            <div class="compras-show-seccion-body">
                <p class="compras-show-notas-texto mb-0">{{ $compra->notas }}</p>
            </div>
        </div>
    @endif

</div>
