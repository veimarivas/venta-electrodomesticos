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
                    <h3 class="ventas-show-kpi-value">{{ $vendidos }}</h3>
                    <small class="ventas-show-kpi-caption">
                        @if ($devueltos > 0)
                            {{ $devueltos }} {{ $devueltos === 1 ? 'devuelto' : 'devueltos' }}
                        @else
                            Unidades vendidas
                        @endif
                    </small>
                </div>
            </div>
        </div>
        @if ($puedeVerCostos)
            <div class="col-xl-3 col-md-6">
                <div class="ventas-show-kpi-card">
                    <div class="ventas-show-kpi-icon ventas-show-kpi-icon-success">
                        <i class="ri-line-chart-line"></i>
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
                                {{-- `carnet`, no `numero_documento`: esa columna no
                                     existe en `personas` y con `shouldBeStrict()`
                                     tumbaba la ficha de toda venta con cliente. --}}
                                @if ($venta->cliente->persona->carnet)
                                    <small class="text-muted font-monospace">{{ $venta->cliente->persona->carnet }}</small>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="d-flex align-items-center gap-3">
                            <div class="ventas-show-cliente-avatar flex-shrink-0" style="background: linear-gradient(135deg, var(--marca-apagado), var(--marca-apagado));">
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

                    {{-- Va DEBAJO del total y no como una resta más arriba: el
                         subtotal ya está neto, así que una fila «− devuelto»
                         entre subtotal y total se lee como si se restara dos
                         veces. Aquí explica por qué el total es menor de lo que
                         se cobró, que es la pregunta real. --}}
                    @if ($venta->tiene_devoluciones)
                        <div class="alert alert-warning alert-borderless mt-3 mb-0 py-2 fs-13">
                            <i class="ri-arrow-go-back-line align-bottom me-1"></i>
                            Se cobraron <strong>Bs {{ number_format((float) $venta->total_original, 2, ',', '.') }}</strong>
                            y se devolvieron <strong>Bs {{ number_format((float) $venta->total_devuelto, 2, ',', '.') }}</strong>.
                        </div>
                    @endif
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
                <span class="ventas-show-seccion-badge">{{ $vendidos }}</span>
                @if ($devueltos > 0)
                    <span class="badge bg-warning-subtle text-warning">
                        <i class="ri-arrow-go-back-line align-bottom me-1"></i>
                        {{ $devueltos }} {{ $devueltos === 1 ? 'devuelto' : 'devueltos' }}
                    </span>
                @endif
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
                            <th class="text-end">Importe</th>
                            <th class="text-end pe-4" style="width: 1%;">
                                <span class="visually-hidden">Acciones</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($venta->detalles as $detalle)
                            {{-- Una linea devuelta se atenua y se tacha: sigue
                                 en la venta como histórico, pero ya no cuenta
                                 en el importe. --}}
                            <tr wire:key="linea-{{ $detalle->id }}"
                                class="{{ $detalle->estaDevuelto() ? 'ventas-show-linea-devuelta' : '' }}">
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
                                            @if ($detalle->estaDevuelto())
                                                <div class="mt-1">
                                                    <span class="badge bg-warning-subtle text-warning">
                                                        <i class="ri-arrow-go-back-line align-bottom me-1"></i>
                                                        Devuelto {{ $detalle->devuelto_en?->translatedFormat('d M') }}
                                                    </span>
                                                    <small class="d-block text-muted fs-11 mt-1">
                                                        {{ $detalle->motivo_devolucion }}
                                                    </small>
                                                </div>
                                            @endif
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
                                <td class="text-end ventas-show-col-num fw-semibold">
                                    Bs {{ number_format((float) $detalle->precio_unitario - (float) $detalle->descuento, 2, ',', '.') }}
                                </td>
                                <td class="text-end pe-4">
                                    @if ($puedeDevolver && ! $detalle->estaDevuelto())
                                        <button type="button" class="btn btn-sm btn-soft-warning"
                                            wire:click="confirmarDevolucion({{ $detalle->id }})"
                                            wire:loading.attr="disabled"
                                            title="Devolver este aparato al stock">
                                            <i class="ri-arrow-go-back-line align-bottom"></i>
                                        </button>
                                    @endif
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
                        <i class="ri-line-chart-line"></i>
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
                                <i class="ri-money-dollar-box-line"></i>
                                Efectivo Bs {{ number_format((float) $venta->monto_efectivo, 2, ',', '.') }}
                            </span>
                            <span class="ventas-show-pago-mixto-item">
                                <i class="ri-qr-code-line"></i>
                                QR Bs {{ number_format((float) $venta->monto_qr, 2, ',', '.') }}
                            </span>
                        </div>
                    @endif

                    @if ($venta->credito)
                        {{-- A plazos el «método de pago» solo cuenta la mitad: lo
                             que importa es cuánto entró hoy y cuánto queda
                             debiendo. --}}
                        <div class="ventas-show-pago-mixto">
                            <span class="ventas-show-pago-mixto-item">
                                <i class="ri-money-dollar-box-line"></i>
                                Inicial Bs
                                {{ number_format((float) $venta->credito->cuota_inicial, 2, ',', '.') }}
                            </span>
                            <span class="ventas-show-pago-mixto-item">
                                <i class="ri-calendar-schedule-line"></i>
                                {{ $venta->credito->numero_cuotas }}
                                {{ $venta->credito->numero_cuotas === 1 ? 'cuota' : 'cuotas' }} ·
                                saldo Bs
                                {{ number_format($venta->credito->saldoEnCentavos() / 100, 2, ',', '.') }}
                            </span>
                        </div>
                    @endif

                    @if ($venta->qrCobro)
                        <span class="ventas-show-qr-badge">
                            <i class="ri-qr-code-line align-bottom"></i> {{ $venta->qrCobro->nombre }}
                        </span>
                    @endif
                </div>

                @if ($venta->credito && $puedeVerCredito)
                    <div class="mt-3">
                        <a href="{{ route('creditos.show', $venta->credito) }}" class="ventas-show-comprobante">
                            <i class="ri-hand-coin-line"></i>
                            <span>Ver el plan de cuotas y sus pagos</span>
                            <i class="ri-arrow-right-line" style="font-size: .8rem; opacity: .6;"></i>
                        </a>
                    </div>
                @endif

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

    {{-- ===================== Entregas ===================== --}}
    @if ($puedeVerEntregas && ($venta->entregas->isNotEmpty() || $puedeProgramarEntrega))
        <div class="ventas-show-seccion mb-4">
            <div class="ventas-show-seccion-header">
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <div class="ventas-show-seccion-icon">
                            <i class="ri-truck-line"></i>
                        </div>
                        <h5 class="mb-0">Entregas</h5>
                        @if ($venta->entregas->isNotEmpty())
                            <span class="ventas-show-seccion-badge">{{ $venta->entregas->count() }}</span>
                        @endif
                    </div>

                    @if ($puedeProgramarEntrega)
                        <button type="button" class="btn btn-sm btn-primary" wire:click="abrirEntrega">
                            <i class="ri-map-pin-line align-bottom me-1"></i> Programar entrega
                        </button>
                    @endif
                </div>
            </div>

            <div class="ventas-show-seccion-body">
                @forelse ($venta->entregas as $entrega)
                    <div class="p-3 border-bottom" wire:key="entrega-{{ $entrega->id }}">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
                            <div class="min-w-0">
                                <span class="fw-semibold d-block">{{ $entrega->direccion }}</span>
                                <small class="text-muted d-block">
                                    @if ($entrega->referencia)
                                        {{ $entrega->referencia }} ·
                                    @endif
                                    @if ($entrega->programada_para)
                                        Para el {{ $entrega->programada_para->format('d/m/Y') }}
                                    @else
                                        Sin fecha acordada
                                    @endif
                                    @if ($entrega->repartidor)
                                        · Lleva {{ $entrega->repartidor->name }}
                                    @endif
                                    @if ($entrega->con_instalacion)
                                        · Con instalación
                                    @endif
                                </small>
                                <small class="text-muted d-block">
                                    {{ $entrega->detalles->count() }}
                                    {{ $entrega->detalles->count() === 1 ? 'aparato' : 'aparatos' }}:
                                    {{ $entrega->detalles->map(fn ($d) => $d->ventaDetalle?->producto?->nombre)->filter()->join(', ') }}
                                </small>
                                @if ($entrega->esta_entregada)
                                    <small class="text-success d-block">
                                        Recibió {{ $entrega->recibida_por }} ·
                                        {{ $entrega->entregada_en?->format('d/m/Y H:i') }}
                                    </small>
                                @elseif ($entrega->motivo_fallo)
                                    <small class="text-danger d-block">{{ $entrega->motivo_fallo }}</small>
                                @endif
                            </div>

                            @if ($entrega->esta_entregada)
                                <span class="badge bg-success-subtle text-success">Entregada</span>
                            @elseif ($entrega->estado === 'cancelada')
                                <span class="badge bg-secondary-subtle text-secondary">Cancelada</span>
                            @elseif ($entrega->esta_atrasada)
                                {{-- Lo atrasado se dice aquí y no solo en el tablero: es
                                     donde mira quien atiende la llamada del cliente. --}}
                                <span class="badge bg-danger-subtle text-danger">Atrasada</span>
                            @else
                                <span
                                    class="badge bg-primary-subtle text-primary">{{ $estadosEntrega[$entrega->estado] ?? $entrega->estado }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="text-muted p-3 mb-0">
                        Esta venta no tiene ninguna entrega programada. Si el cliente se llevó los
                        aparatos, no hace falta ninguna.
                    </p>
                @endforelse
            </div>
        </div>
    @endif

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

    {{-- ===================== Devolver un aparato ===================== --}}
    <div class="modal fade" id="modalDevolucion" tabindex="-1" aria-hidden="true"
         wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title rounded-circle fs-4 bg-warning-subtle text-warning">
                                <i class="ri-arrow-go-back-line"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">Devolver el aparato</h5>
                            <small class="text-muted">Vuelve al stock y deja de contar en esta venta</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="mb-3">
                        <label for="motivo-devolucion" class="form-label">¿Por qué se devuelve?</label>
                        <textarea id="motivo-devolucion" rows="3"
                                  class="form-control @error('motivoDevolucion') is-invalid @enderror"
                                  wire:model="motivoDevolucion"
                                  placeholder="Vino fallado, el cliente se arrepintió, no era el modelo pedido..."></textarea>
                        @error('motivoDevolucion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        {{-- El motivo no es burocracia: es lo que el proveedor
                             pide cuando hay que reclamarle una falla, y lo que
                             distingue un aparato roto de un cliente indeciso. --}}
                        <small class="text-muted d-block mt-2">
                            Queda guardado en la venta y en el historial del aparato.
                        </small>
                    </div>

                    <div class="alert alert-warning alert-borderless mb-0 fs-13">
                        <i class="ri-information-line align-bottom me-1"></i>
                        La venta <strong>no se anula</strong>: sigue siendo la misma, con un aparato
                        menos. Si devuelves todos, entonces sí queda anulada.
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" wire:click="devolver"
                            wire:loading.attr="disabled" wire:target="devolver">
                        <span wire:loading.remove wire:target="devolver">
                            <i class="ri-arrow-go-back-line align-bottom me-1"></i> Devolver al stock
                        </span>
                        <span wire:loading wire:target="devolver">Devolviendo...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Programar entrega ===================== --}}
    <div class="modal fade" id="modalProgramarEntrega" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Programar entrega</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">¿Qué aparatos se llevan?</label>
                        <div class="list-group list-group-flush">
                            @foreach ($entregables as $linea)
                                <label class="list-group-item d-flex align-items-center gap-2 px-0"
                                    wire:key="entregable-{{ $linea->id }}">
                                    <input type="checkbox" class="form-check-input m-0"
                                        value="{{ $linea->id }}" wire:model="lineasAEntregar">
                                    <span class="min-w-0">
                                        <span class="d-block">{{ $linea->producto?->nombre }}</span>
                                        <small class="text-muted">
                                            {{ $linea->unidad?->serial ?: $linea->unidad?->codigo_interno }}
                                        </small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('lineasAEntregar')
                            <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                        @enderror
                        {{-- Los ya programados no salen: el índice único los
                             rechazaría igualmente, y ofrecerlos invita a un error
                             que después hay que explicar. --}}
                        <small class="text-muted d-block mt-1">
                            Solo se listan los aparatos que no están ya en otra entrega.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="entrega-direccion" class="form-label">Dirección</label>
                        <input type="text" id="entrega-direccion"
                            class="form-control @error('direccion') is-invalid @enderror" wire:model="direccion"
                            placeholder="Av. Siempre Viva 742, zona Sur">
                        @error('direccion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="entrega-referencia" class="form-label">
                                Referencia <span class="text-muted">(opcional)</span>
                            </label>
                            <input type="text" id="entrega-referencia" class="form-control"
                                wire:model="referencia" placeholder="Portón verde, frente a la cancha">
                        </div>
                        <div class="col-md-6">
                            <label for="entrega-telefono" class="form-label">
                                Teléfono de contacto <span class="text-muted">(opcional)</span>
                            </label>
                            <input type="text" id="entrega-telefono" class="form-control"
                                wire:model="telefonoContacto" placeholder="A quién llamar al llegar">
                        </div>
                        <div class="col-md-6">
                            <label for="entrega-fecha" class="form-label">
                                ¿Qué día? <span class="text-muted">(opcional)</span>
                            </label>
                            <input type="date" id="entrega-fecha"
                                class="form-control @error('programadaPara') is-invalid @enderror"
                                wire:model="programadaPara">
                            @error('programadaPara')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Sin fecha queda como «cuando se pueda».</small>
                        </div>
                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check mt-4">
                                <input type="checkbox" class="form-check-input" id="entrega-instalacion"
                                    wire:model="conInstalacion">
                                <label class="form-check-label" for="entrega-instalacion">
                                    Hay que instalarlo
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label for="entrega-notas" class="form-label">
                            Notas <span class="text-muted">(opcional)</span>
                        </label>
                        <textarea id="entrega-notas" rows="2" class="form-control" wire:model="notasEntrega"
                            placeholder="Subir por el ascensor de servicio, avisar una hora antes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="programarEntrega"
                        wire:loading.attr="disabled" wire:target="programarEntrega">Programar</button>
                </div>
            </div>
        </div>
    </div>

</div>
