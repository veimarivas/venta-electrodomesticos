<div class="ventas-show-modulo">

    {{-- ===================== Hero ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero ventas-show-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="ventas-show-hero-ring" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-file-list-3-line me-1"></i>
                            Ventas · Detalle
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="ventas-show-hero-avatar flex-shrink-0">
                                <i class="{{ $venta->esta_anulada ? 'ri-close-circle-line' : 'ri-checkbox-circle-line' }}"></i>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1 d-flex align-items-center gap-2 flex-wrap">
                                    <span class="font-monospace">{{ $venta->codigo }}</span>
                                    <span class="ventas-show-estado {{ $venta->esta_anulada ? 'ventas-show-estado-anulada' : 'ventas-show-estado-completada' }}">
                                        <span class="ventas-show-estado-dot"></span>
                                        {{ $estados[$venta->estado] }}
                                    </span>
                                </h4>
                                <p class="text-white-50 mb-0">
                                    {{ $venta->vendida_en->format('d/m/Y H:i') }}
                                    · {{ $venta->cliente?->persona?->nombre_completo ?? 'Público general' }}
                                    · Vendió {{ $venta->user?->name ?? '—' }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                            <a href="{{ route('ventas.recibo', $venta) }}" target="_blank" rel="noopener"
                                class="btn btn-sm btn-light ventas-show-accion">
                                <i class="ri-file-download-line align-bottom me-1"></i> Recibo
                            </a>
                            <a href="{{ route('ventas.index') }}"
                                class="btn btn-sm btn-outline-light ventas-show-accion">
                                <i class="ri-arrow-left-line align-bottom me-1"></i> Volver
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Alerta de anulación ===================== --}}
    @if ($venta->esta_anulada)
        <div class="ventas-show-anulada-alert mb-4">
            <i class="ri-error-warning-line"></i>
            <div>
                <strong>Venta anulada</strong>
                <span class="d-block mt-1">
                    {{ $venta->anulada_en?->format('d/m/Y H:i') }} · {{ $venta->motivo_anulacion }}
                </span>
            </div>
        </div>
    @endif

    {{-- ===================== KPIs ===================== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="ventas-show-kpi-card">
                <div class="ventas-show-kpi-icon ventas-show-kpi-icon-primary">
                    <i class="ri-wallet-3-line"></i>
                </div>
                <div class="ventas-show-kpi-body">
                    <small class="ventas-show-kpi-label">Total de la venta</small>
                    <h3 class="ventas-show-kpi-value">Bs {{ number_format((float) $venta->total, 2, ',', '.') }}</h3>
                    <small class="ventas-show-kpi-caption">Monto cobrado</small>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="ventas-show-kpi-card">
                <div class="ventas-show-kpi-icon ventas-show-kpi-icon-info">
                    <i class="ri-box-3-line"></i>
                </div>
                <div class="ventas-show-kpi-body">
                    <small class="ventas-show-kpi-label">Aparatos</small>
                    <h3 class="ventas-show-kpi-value">{{ $venta->detalles->count() }}</h3>
                    <small class="ventas-show-kpi-caption">Unidades vendidas</small>
                </div>
            </div>
        </div>
        @if ($puedeVerCostos)
            <div class="col-xl-3 col-md-6">
                <div class="ventas-show-kpi-card">
                    <div class="ventas-show-kpi-icon ventas-show-kpi-icon-success">
                        <i class="ri-trending-up-line"></i>
                    </div>
                    <div class="ventas-show-kpi-body">
                        <small class="ventas-show-kpi-label">Ganancia</small>
                        <h3 class="ventas-show-kpi-value ventas-show-kpi-ganancia">Bs {{ number_format((float) $venta->ganancia, 2, ',', '.') }}</h3>
                        <small class="ventas-show-kpi-caption">Ingreso menos costo</small>
                    </div>
                </div>
            </div>
        @endif
        <div class="col-xl-3 col-md-6">
            <div class="ventas-show-kpi-card">
                <div class="ventas-show-kpi-icon ventas-show-kpi-icon-{{ $venta->esta_anulada ? 'danger' : 'warning' }}">
                    <i class="ri-information-line"></i>
                </div>
                <div class="ventas-show-kpi-body">
                    <small class="ventas-show-kpi-label">Estado</small>
                    <h3 class="ventas-show-kpi-value">{{ $estados[$venta->estado] }}</h3>
                    <small class="ventas-show-kpi-caption">{{ $venta->esta_anulada ? 'Anulada el '.$venta->anulada_en?->format('d/m/Y') : 'Venta completada' }}</small>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Info cards ===================== --}}
    <div class="row g-3 mb-4">
        {{-- Cliente --}}
        <div class="col-lg-6">
            <div class="ventas-show-info-card">
                <div class="ventas-show-info-header">
                    <div class="ventas-show-info-icon">
                        <i class="ri-user-3-line"></i>
                    </div>
                    <h6 class="mb-0">Cliente</h6>
                </div>
                <div class="ventas-show-info-body">
                    @if ($venta->cliente?->persona)
                        <div class="d-flex align-items-center gap-3">
                            <div class="ventas-show-cliente-avatar flex-shrink-0">
                                {{ strtoupper(mb_substr($venta->cliente->persona->nombre_completo, 0, 2)) }}
                            </div>
                            <div class="min-w-0">
                                <h5 class="mb-0">{{ $venta->cliente->persona->nombre_completo }}</h5>
                                @if ($venta->cliente->persona->numero_documento)
                                    <small class="text-muted font-monospace">{{ $venta->cliente->persona->numero_documento }}</small>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="d-flex align-items-center gap-3">
                            <div class="ventas-show-cliente-avatar flex-shrink-0" style="background: linear-gradient(135deg, #6b778a, #4b5563);">
                                <i class="ri-user-3-line"></i>
                            </div>
                            <div class="min-w-0">
                                <h5 class="mb-0">Público general</h5>
                                <small class="text-muted">Sin datos de cliente</small>
                            </div>
                        </div>
                    @endif
                    <div class="ventas-show-info-datos mt-3">
                        <div class="ventas-show-info-dato">
                            <i class="ri-user-star-line"></i>
                            <span>Vendió: {{ $venta->user?->name ?? '—' }}</span>
                        </div>
                        <div class="ventas-show-info-dato">
                            <i class="ri-calendar-line"></i>
                            <span>{{ $venta->vendida_en->format('d/m/Y H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Resumen financiero --}}
        <div class="col-lg-6">
            <div class="ventas-show-info-card">
                <div class="ventas-show-info-header">
                    <div class="ventas-show-info-icon">
                        <i class="ri-file-list-3-line"></i>
                    </div>
                    <h6 class="mb-0">Resumen financiero</h6>
                </div>
                <div class="ventas-show-info-body">
                    <div class="ventas-show-fila-resumen">
                        <span class="ventas-show-fila-label">Subtotal</span>
                        <span class="ventas-show-fila-valor">Bs {{ number_format((float) $venta->subtotal, 2, ',', '.') }}</span>
                    </div>
                    @if ((float) $venta->descuento > 0)
                        <div class="ventas-show-fila-resumen">
                            <span class="ventas-show-fila-label">Descuento</span>
                            <span class="ventas-show-fila-valor text-danger">− Bs {{ number_format((float) $venta->descuento, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    @if ($puedeVerCostos)
                        <div class="ventas-show-fila-resumen">
                            <span class="ventas-show-fila-label">Costo total</span>
                            <span class="ventas-show-fila-valor">Bs {{ number_format((float) $venta->costo_total, 2, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="ventas-show-fila-total">
                        <span class="ventas-show-fila-label fw-bold">Total</span>
                        <span class="ventas-show-fila-valor ventas-show-total-grande">Bs {{ number_format((float) $venta->total, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Productos vendidos ===================== --}}
    <div class="ventas-show-seccion mb-4">
        <div class="ventas-show-seccion-header">
            <div class="d-flex align-items-center gap-2">
                <div class="ventas-show-seccion-icon">
                    <i class="ri-shopping-bag-3-line"></i>
                </div>
                <h5 class="mb-0">Aparatos vendidos</h5>
                <span class="ventas-show-seccion-badge">{{ $venta->detalles->count() }}</span>
            </div>
        </div>
        <div class="ventas-show-seccion-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-ventas-detalle">
                    <thead>
                        <tr>
                            <th class="ps-4">Aparato</th>
                            <th class="text-end">Precio</th>
                            <th class="text-end">Descuento</th>
                            @if ($puedeVerCostos)
                                <th class="text-end">Costo</th>
                                <th class="text-end">Ganancia</th>
                            @endif
                            <th class="text-end pe-4">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($venta->detalles as $detalle)
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="ventas-show-producto-img flex-shrink-0">
                                            @if ($detalle->producto?->imagen)
                                                <img src="{{ asset('storage/'.$detalle->producto->imagen) }}" alt="{{ $detalle->producto->nombre }}">
                                            @else
                                                <div class="ventas-show-producto-placeholder">
                                                    <i class="ri-smartphone-line"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <h6 class="mb-0 text-truncate">{{ $detalle->producto?->nombre ?? 'Producto' }}</h6>
                                            <small class="ventas-show-sku">
                                                <code class="fs-11">{{ $detalle->unidad?->codigo_interno }}</code>
                                                @if ($detalle->unidad?->serial) · {{ $detalle->unidad->serial }} @endif
                                                @if ($detalle->producto?->marca) · {{ $detalle->producto->marca->nombre }} @endif
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end ventas-show-col-num">{{ number_format((float) $detalle->precio_unitario, 2, ',', '.') }}</td>
                                <td class="text-end ventas-show-col-num">
                                    @if ((float) $detalle->descuento > 0)
                                        <span class="text-danger">− {{ number_format((float) $detalle->descuento, 2, ',', '.') }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                @if ($puedeVerCostos)
                                    <td class="text-end ventas-show-col-num text-muted">{{ number_format((float) $detalle->costo_unitario, 2, ',', '.') }}</td>
                                    <td class="text-end ventas-show-col-num text-success fw-semibold">{{ number_format((float) $detalle->ganancia, 2, ',', '.') }}</td>
                                @endif
                                <td class="text-end ventas-show-col-num fw-semibold pe-4">
                                    Bs {{ number_format((float) $detalle->precio_unitario - (float) $detalle->descuento, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $puedeVerCostos ? 6 : 4 }}" class="text-center py-5">
                                    <div class="ventas-show-empty">
                                        <i class="ri-shopping-basket-line"></i>
                                        <p>Esta venta no tiene aparatos.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ===================== Beneficio (solo si puede ver costos) ===================== --}}
    @if ($puedeVerCostos && ! $venta->esta_anulada)
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="ventas-show-beneficio">
                    <div class="ventas-show-beneficio-icon ventas-show-beneficio-icon-primary">
                        <i class="ri-money-dollar-circle-line"></i>
                    </div>
                    <div class="ventas-show-beneficio-body">
                        <small class="ventas-show-beneficio-label">Costo total</small>
                        <h4 class="ventas-show-beneficio-value">Bs {{ number_format((float) $venta->costo_total, 2, ',', '.') }}</h4>
                        <small class="ventas-show-beneficio-sub">Inversión en los aparatos</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="ventas-show-beneficio">
                    <div class="ventas-show-beneficio-icon ventas-show-beneficio-icon-success">
                        <i class="ri-trending-up-line"></i>
                    </div>
                    <div class="ventas-show-beneficio-body">
                        <small class="ventas-show-beneficio-label">Ganancia neta</small>
                        <h4 class="ventas-show-beneficio-value ventas-show-beneficio-success">Bs {{ number_format((float) $venta->ganancia, 2, ',', '.') }}</h4>
                        <small class="ventas-show-beneficio-sub">Margen de esta venta</small>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== Método de pago ===================== --}}
    <div class="ventas-show-seccion mb-4">
        <div class="ventas-show-seccion-header">
            <div class="d-flex align-items-center gap-2">
                <div class="ventas-show-seccion-icon">
                    <i class="ri-wallet-3-line"></i>
                </div>
                <h5 class="mb-0">Método de pago</h5>
            </div>
        </div>
        <div class="ventas-show-seccion-body">
            <div class="p-3">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="ventas-show-metodo-badge">
                        <i class="ri-bank-card-line align-bottom"></i>
                        {{ $metodosPago[$venta->metodo_pago] ?? $venta->metodo_pago }}
                    </span>

                    @if ($venta->metodo_pago === 'mixto')
                        <div class="ventas-show-pago-mixto">
                            <span class="ventas-show-pago-mixto-item">
                                <i class="ri-banknote-line"></i>
                                Efectivo Bs {{ number_format((float) $venta->monto_efectivo, 2, ',', '.') }}
                            </span>
                            <span class="ventas-show-pago-mixto-item">
                                <i class="ri-qr-code-line"></i>
                                QR Bs {{ number_format((float) $venta->monto_qr, 2, ',', '.') }}
                            </span>
                        </div>
                    @endif

                    @if ($venta->qrCobro)
                        <span class="ventas-show-qr-badge">
                            <i class="ri-qr-code-line align-bottom"></i> {{ $venta->qrCobro->nombre }}
                        </span>
                    @endif
                </div>

                @if ($venta->comprobante_url)
                    <div class="mt-3">
                        <a href="{{ $venta->comprobante_url }}" target="_blank" rel="noopener"
                            class="ventas-show-comprobante">
                            <i class="ri-image-line"></i>
                            <span>Respaldo del pago</span>
                            <i class="ri-external-link-line" style="font-size: .8rem; opacity: .6;"></i>
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== Notas ===================== --}}
    @if ($venta->notas)
        <div class="ventas-show-seccion mb-4">
            <div class="ventas-show-seccion-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="ventas-show-seccion-icon">
                        <i class="ri-sticky-note-line"></i>
                    </div>
                    <h5 class="mb-0">Notas</h5>
                </div>
            </div>
            <div class="ventas-show-seccion-body">
                <p class="ventas-show-notas-texto">{{ $venta->notas }}</p>
            </div>
        </div>
    @endif

</div>
