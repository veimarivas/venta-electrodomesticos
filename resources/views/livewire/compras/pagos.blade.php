<div class="compras-modulo pagos-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-bank-card-line me-1"></i>
                            Compras · Pagos
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-bank-card-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Pagos a proveedores</h4>
                                <p class="text-white-50 mb-0">
                                    Cuánto salió de la tienda hacia los proveedores, por día, semana o mes.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                            <div class="pagos-hero-stat">
                                <span class="pagos-hero-stat-label">Período</span>
                                <span class="pagos-hero-stat-value">{{ $cantidad }}</span>
                                <span class="pagos-hero-stat-sub">pagos</span>
                            </div>
                            <div class="pagos-hero-stat pagos-hero-stat--total">
                                <span class="pagos-hero-stat-label">Total</span>
                                <span class="pagos-hero-stat-value">Bs {{ $total }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Resumen y filtros ===================== --}}
    <div class="row g-3 mb-4">
        {{-- KPI: Total pagado --}}
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 pagos-kpi-card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="pagos-kpi-icon pagos-kpi-icon--success">
                            <i class="ri-save-3-line"></i>
                        </div>
                        <div class="min-w-0">
                            <small class="pagos-kpi-label">Pagado en el período</small>
                            <h4 class="mb-0 pagos-kpi-valor text-success">Bs {{ $total }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- KPI: Cantidad --}}
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 pagos-kpi-card">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="pagos-kpi-icon pagos-kpi-icon--primary">
                            <i class="ri-file-list-3-line"></i>
                        </div>
                        <div class="min-w-0">
                            <small class="pagos-kpi-label">Pagos registrados</small>
                            <h4 class="mb-0 pagos-kpi-valor">{{ $cantidad }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100 pagos-filtros-card">
                <div class="card-body">
                    <small class="pagos-kpi-label d-block mb-2">Filtrar por período</small>
                    <div class="d-flex flex-wrap gap-1-5">
                        @foreach (['hoy' => 'Hoy', 'semana' => 'Semana', 'mes' => 'Mes', 'todas' => 'Todas'] as $valor => $etiqueta)
                            <button type="button"
                                class="btn btn-sm pagos-filtro-btn {{ $filtro === $valor ? 'pagos-filtro-btn--active' : '' }}"
                                wire:click="$set('filtro', '{{ $valor }}')">
                                @if ($filtro === $valor)
                                    <i class="ri-check-line me-1"></i>
                                @endif
                                {{ $etiqueta }}
                            </button>
                        @endforeach
                    </div>
                    <div class="mt-2">
                        <div class="pagos-search-box">
                            <input type="text" class="form-control form-control-sm"
                                wire:model.live.debounce.400ms="buscar"
                                placeholder="Buscar compra, factura o proveedor...">
                            <i class="ri-search-line"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm pagos-listado-card">
        <div class="pagos-section-header">
            <div class="d-flex align-items-center gap-2">
                <div class="pagos-section-icon">
                    <i class="ri-bank-card-line"></i>
                </div>
                <div>
                    <h5 class="card-title mb-0">Historial de pagos</h5>
                    <small class="text-muted">
                        {{ $pagos->count() }} {{ $pagos->count() === 1 ? 'pago' : 'pagos' }} registrado{{ $pagos->count() === 1 ? '' : 's' }}
                        @if ($buscar !== '') para «{{ $buscar }}» @endif
                    </small>
                </div>
            </div>
            <span class="pagos-total-badge">
                Total: Bs {{ $total }}
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-pagos"
                    wire:loading.class="opacity-50" wire:target="buscar, filtro">
                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4">Compra</th>
                            <th scope="col">Proveedor</th>
                            <th scope="col">Fecha</th>
                            <th scope="col">Registró</th>
                            <th scope="col" class="text-center">Respaldo</th>
                            <th scope="col" class="text-end pe-4">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pagos as $pago)
                            <tr wire:key="pago-{{ $pago->id }}">
                                <td class="ps-4">
                                    <a href="{{ route('compras.show', $pago->compra) }}" class="pagos-compra-link">
                                        <i class="ri-file-list-3-line me-1"></i>
                                        {{ $pago->compra?->codigo ?? '—' }}
                                    </a>
                                    @if ($pago->compra?->numero_factura)
                                        <small class="d-block mt-1">
                                            <span class="pagos-factura-badge">Factura {{ $pago->compra->numero_factura }}</span>
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    <span class="pagos-proveedor">{{ $pago->compra?->proveedor?->nombre ?? '—' }}</span>
                                </td>
                                <td>
                                    <span class="pagos-fecha">{{ $pago->fecha?->format('d/m/Y') }}</span>
                                </td>
                                <td>
                                    <span class="pagos-user">{{ $pago->user?->name ?? '—' }}</span>
                                </td>
                                <td class="text-center">
                                    @if ($this->urlBoucher($pago))
                                        <a href="{{ $this->urlBoucher($pago) }}" target="_blank"
                                            class="pagos-boucher-btn"
                                            title="Ver boucher" aria-label="Ver boucher del pago">
                                            <i class="ri-file-image-line"></i>
                                        </a>
                                    @else
                                        <span class="pagos-sin-boucher">
                                            <i class="ri-file-damage-line"></i>
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <span class="pagos-monto">
                                        Bs {{ number_format((float) $pago->monto, 2, ',', '.') }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="pagos-empty text-center py-5">
                                        <div class="pagos-empty-icon mx-auto mb-3">
                                            <i class="ri-bank-card-line"></i>
                                        </div>
                                        <h6 class="mb-1">No hay pagos en este período</h6>
                                        <p class="text-muted mb-0 fs-13">
                                            @if ($buscar !== '')
                                                Sin resultados para «{{ $buscar }}». Prueba con otro término.
                                            @elseif ($filtro !== 'todas')
                                                Cambia el filtro a "Todas" o amplía el rango de fechas.
                                            @else
                                                Registra un pago desde una orden de compra para que aparezca aquí.
                                            @endif
                                        </p>
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
