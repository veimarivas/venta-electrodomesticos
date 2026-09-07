<div class="compras-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
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
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Resumen y filtros ===================== --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <span class="avatar-title rounded-3 bg-success-subtle text-success fs-4">
                                <i class="ri-save-3-line"></i>
                            </span>
                        </div>
                        <div class="min-w-0">
                            <small class="text-muted d-block">Pagado en el período</small>
                            <h4 class="mb-0 text-success">Bs {{ number_format((float) $total, 2, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <span class="avatar-title rounded-3 bg-primary-subtle text-primary fs-4">
                                <i class="ri-file-list-3-line"></i>
                            </span>
                        </div>
                        <div class="min-w-0">
                            <small class="text-muted d-block">Pagos registrados</small>
                            <h4 class="mb-0">{{ $cantidad }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        @foreach (['hoy' => 'Hoy', 'semana' => 'Semana', 'mes' => 'Mes', 'todas' => 'Todas'] as $valor => $etiqueta)
                            <button type="button"
                                class="btn btn-sm {{ $filtro === $valor ? 'btn-success' : 'btn-light' }} rounded-pill"
                                wire:click="$set('filtro', '{{ $valor }}')">
                                {{ $etiqueta }}
                            </button>
                        @endforeach
                    </div>
                    <div class="mt-2">
                        <input type="text" class="form-control form-control-sm" wire:model.live.debounce.400ms="buscar"
                            placeholder="Compra, factura o proveedor...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-crud"
                    wire:loading.class="opacity-50" wire:target="buscar, filtro">
                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4">Compra</th>
                            <th scope="col">Proveedor</th>
                            <th scope="col">Fecha</th>
                            <th scope="col">Registró</th>
                            <th scope="col">Respaldo</th>
                            <th scope="col" class="text-end pe-4">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pagos as $pago)
                            <tr>
                                <td class="ps-4">
                                    <a href="{{ route('compras.show', $pago->compra) }}" class="fw-semibold text-primary">
                                        {{ $pago->compra?->codigo ?? '—' }}
                                    </a>
                                    @if ($pago->compra?->numero_factura)
                                        <small class="text-muted d-block">Factura {{ $pago->compra->numero_factura }}</small>
                                    @endif
                                </td>
                                <td>{{ $pago->compra?->proveedor?->nombre ?? '—' }}</td>
                                <td>{{ $pago->fecha?->format('d/m/Y') }}</td>
                                <td>{{ $pago->user?->name ?? '—' }}</td>
                                <td>
                                    @if ($this->urlBoucher($pago))
                                        <a href="{{ $this->urlBoucher($pago) }}" target="_blank"
                                            class="btn btn-sm btn-ghost-info btn-icon rounded-circle"
                                            title="Ver boucher" aria-label="Ver boucher del pago">
                                            <i class="ri-file-image-line fs-16"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-end pe-4 fw-semibold">
                                    Bs {{ number_format((float) $pago->monto, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="text-center py-5">
                                        <div class="crud-empty-icon mx-auto mb-4">
                                            <span class="avatar-title rounded-circle fs-1">
                                                <i class="ri-bank-card-line"></i>
                                            </span>
                                        </div>
                                        <h6 class="mb-1">No hay pagos en este período</h6>
                                        <p class="text-muted mb-0">Cambia el filtro o registra un pago desde una orden de compra.</p>
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