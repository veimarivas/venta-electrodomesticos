<div class="items-modulo ventas-modulo">

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-file-list-3-line me-1"></i> Ventas · Historial
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title bg-white bg-opacity-25 text-white rounded-3 fs-3">
                                    <i class="ri-file-list-3-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Historial de ventas</h4>
                                <p class="text-white-50 mb-0">
                                    Qué se vendió, a quién y cuánto se ganó. Las ventas no se borran: se anulan.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('ventas.crear')
                                <a href="{{ route('ventas.create') }}" class="btn btn-light crud-nueva-hero">
                                    <i class="ri-add-line align-bottom me-1"></i> Nueva venta
                                </a>
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
            <x-stat-card label="Ventas completadas" value="{{ $totalVentas }}" icon="bx-receipt"
                color="primary" caption="Sin contar anuladas" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Ingresos" value="Bs {{ number_format($ingresoTotal, 2, ',', '.') }}"
                icon="bx-wallet2" color="success" caption="Total cobrado" />
        </div>
        <div class="col-xl-3 col-md-6">
            @if ($puedeVerCostos)
                <x-stat-card label="Ganancia" value="Bs {{ number_format($gananciaTotal, 2, ',', '.') }}"
                    icon="bx-trending-up" color="info" caption="Ingresos menos costos" />
            @else
                <x-stat-card label="Ventas de hoy" value="{{ $ventasHoy }}" icon="bx-calendar-event"
                    color="info" caption="Bs {{ number_format($ingresoHoy, 2, ',', '.') }}" />
            @endif
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Anuladas" value="{{ $anuladas }}" icon="bx-x-circle"
                color="danger" caption="Fuera de los totales" />
        </div>
    </div>

    {{-- ===================== Gráficos de ingresos por método ===================== --}}
    @php
        $graf = $this->ingresosPorPago;
        $serieGraf = $this->serieIngresos;
        $tieneIngresos = ($graf['total'] ?? 0) > 0;
    @endphp
    <div class="row g-3 mb-4">
        <div class="col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent py-3">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        <i class="ri-wallet-3-line"></i> Ingresos por tipo de cobro
                    </h5>
                    <small class="text-muted fs-13">
                        {{ \Carbon\Carbon::parse($desde)->format('d/m/Y') }} — {{ \Carbon\Carbon::parse($hasta)->format('d/m/Y') }}
                        · Mixto desagregado en Efectivo y QR
                    </small>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2 mb-3 flex-wrap">
                        <span class="badge bg-success-subtle text-success border fs-12">
                            <i class="ri-money-dollar-circle-line me-1"></i>Efectivo Bs {{ number_format($graf['efectivo'], 2, ',', '.') }}
                        </span>
                        <span class="badge bg-primary-subtle text-primary border fs-12">
                            <i class="ri-qr-code-line me-1"></i>QR Bs {{ number_format($graf['qr'], 2, ',', '.') }}
                        </span>
                        <span class="badge bg-light text-body border fs-12">
                            Total Bs {{ number_format($graf['total'], 2, ',', '.') }}
                        </span>
                    </div>
                    <div class="reportes-chart-container chart-doughnut {{ $tieneIngresos ? '' : 'vacio' }}" style="height: 240px">
                        <canvas id="chart-ventas-pago" data-colors='["--vz-success", "--vz-primary"]'></canvas>
                        @if (! $tieneIngresos)
                            <div class="reportes-chart-vacio">
                                <i class="ri-wallet-3-line d-block"></i>
                                Sin ingresos en este período.
                            </div>
                        @endif
                    </div>
                    <small class="text-muted fs-12 d-block mt-2">
                        Efectivo y QR puros suman directo; en pago mixto se suma cada parte en su canal.
                    </small>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent py-3">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        <i class="ri-bar-chart-line"></i> Evolución diaria — Efectivo vs QR
                    </h5>
                    <small class="text-muted fs-13">Ingresos diarios desagregados</small>
                </div>
                <div class="card-body">
                    <div class="reportes-chart-container chart-barras {{ $serieGraf->sum('total') > 0 ? '' : 'vacio' }}" style="height: 240px">
                        <canvas id="chart-ventas-evolucion" data-colors='["--vz-success", "--vz-primary"]'></canvas>
                        @if ($serieGraf->sum('total') == 0)
                            <div class="reportes-chart-vacio">
                                <i class="ri-line-chart-line d-block"></i>
                                Sin datos en este período.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm crud-listado">
        <div class="card-header bg-transparent py-3 crud-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Ventas
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $ventas->total() }} {{ $ventas->total() === 1 ? 'venta' : 'ventas' }}
                        @if ($buscar !== '')
                            para «{{ $buscar }}»
                        @endif
                    </small>
                </div>

                <div class="col-md-9">
                    <div class="crud-filtros justify-content-md-end">
                        <div class="search-box flex-grow-1" style="max-width: 18rem">
                            <input type="text" class="form-control crud-busqueda"
                                placeholder="Código, cliente o serial..." wire:model.live.debounce.400ms="buscar">
                            <i class="ri-search-line search-icon"></i>
                        </div>

                        <input type="date" class="form-control" style="max-width: 9.5rem"
                            wire:model.live="desde" aria-label="Desde">
                        <input type="date" class="form-control" style="max-width: 9.5rem"
                            wire:model.live="hasta" aria-label="Hasta">

                        <select class="form-select" style="max-width: 10rem" wire:model.live="filtroEstado">
                            <option value="todas">Todo estado</option>
                            @foreach ($estados as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-crud"
                    wire:loading.class="opacity-50" wire:target="buscar, filtroEstado, desde, hasta">
                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4" role="button" wire:click="ordenar('codigo')">
                                Venta
                                @if ($ordenarPor === 'codigo')
                                    <i class="ri-arrow-{{ $direccionOrden === 'asc' ? 'up' : 'down' }}-line align-middle"></i>
                                @endif
                            </th>
                            <th scope="col" role="button" wire:click="ordenar('vendida_en')">
                                Fecha
                                @if ($ordenarPor === 'vendida_en')
                                    <i class="ri-arrow-{{ $direccionOrden === 'asc' ? 'up' : 'down' }}-line align-middle"></i>
                                @endif
                            </th>
                            <th scope="col">Cliente</th>
                            <th scope="col" class="text-center">Aparatos</th>
                            <th scope="col" class="text-end" role="button" wire:click="ordenar('total')">
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
                        @forelse ($ventas as $venta)
                            <tr wire:key="venta-{{ $venta->id }}"
                                class="{{ $venta->esta_anulada ? 'fila-dado-de-baja' : '' }}">
                                <td class="ps-4">
                                    <span class="badge bg-primary-subtle text-primary fs-12 font-monospace">
                                        {{ $venta->codigo }}
                                    </span>
                                    <small class="text-muted d-block mt-1">
                                        {{ $metodosPago[$venta->metodo_pago] ?? $venta->metodo_pago }}
                                    </small>
                                </td>

                                <td>
                                    <div>{{ $venta->vendida_en->format('d/m/Y') }}</div>
                                    <small class="text-muted">{{ $venta->vendida_en->format('H:i') }}</small>
                                </td>

                                <td>
                                    <div class="text-truncate">
                                        {{ $venta->cliente?->persona?->nombre_completo ?? 'Público general' }}
                                    </div>
                                    <small class="text-muted">Vendió {{ $venta->user?->name ?? '—' }}</small>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-light text-body border">{{ $venta->detalles_count }}</span>
                                </td>

                                <td class="text-end col-importe fw-semibold">
                                    Bs {{ number_format((float) $venta->total, 2, ',', '.') }}
                                </td>

                                <td class="text-center">
                                    <span class="badge rounded-pill {{ $venta->esta_anulada ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' }}">
                                        {{ $estados[$venta->estado] ?? $venta->estado }}
                                    </span>
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('ventas.show', $venta) }}" class="btn btn-sm btn-ghost-info btn-icon rounded-circle"
                                            title="Ver detalle" aria-label="Ver el detalle de {{ $venta->codigo }}">
                                            <i class="ri-eye-line fs-16"></i>
                                        </a>

                                        <button type="button" class="btn btn-sm btn-ghost-success btn-icon rounded-circle"
                                            wire:click="verRecibo({{ $venta->id }})" title="Ver recibo"
                                            aria-label="Ver recibo de {{ $venta->codigo }}">
                                            <i class="ri-file-text-line fs-16"></i>
                                        </button>

                                        @if (! $venta->esta_anulada)
                                            @can('ventas.anular')
                                                <button type="button" class="btn btn-sm btn-ghost-danger btn-icon rounded-circle"
                                                    wire:click="confirmarAnular({{ $venta->id }})" title="Anular"
                                                    aria-label="Anular {{ $venta->codigo }}">
                                                    <i class="ri-close-circle-line fs-16"></i>
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <div class="text-center py-5">
                                        <div class="crud-empty-icon mx-auto mb-4">
                                            <span class="avatar-title rounded-circle fs-1">
                                                <i class="{{ $buscar !== '' || $desde !== '' || $hasta !== '' ? 'ri-search-eye-line' : 'ri-receipt-line' }}"></i>
                                            </span>
                                        </div>
                                        @if ($buscar !== '' || $desde !== '' || $hasta !== '' || $filtroEstado !== 'todas')
                                            <h5 class="mb-1">Sin ventas con estos filtros</h5>
                                            <p class="text-muted mb-3">Prueba con otro rango de fechas o quita los filtros.</p>
                                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                                wire:click="$set('buscar', ''); $set('desde', ''); $set('hasta', ''); $set('filtroEstado', 'todas')">
                                                <i class="ri-close-line align-bottom me-1"></i> Quitar filtros
                                            </button>
                                        @else
                                            <h5 class="mb-1">Todavía no hay ventas</h5>
                                            <p class="text-muted mb-3">Registra la primera desde el punto de venta.</p>
                                            @can('ventas.crear')
                                                <a href="{{ route('ventas.create') }}" class="btn btn-success btn-sm">
                                                    <i class="ri-shopping-cart-2-line align-bottom me-1"></i> Ir al punto de venta
                                                </a>
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

        @if ($ventas->hasPages())
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando {{ $ventas->firstItem() }}-{{ $ventas->lastItem() }} de {{ $ventas->total() }}
                    </p>
                    <div class="crud-paginacion">
                        {{ $ventas->onEachSide(1)->links() }}
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal de anulación ===================== --}}
    <div class="modal fade zoomIn" id="modalAnularVenta" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1"><i class="ri-close-circle-line"></i></span>
                    </div>

                    <h5 class="mb-2">¿Anular la venta {{ $anularCodigo }}?</h5>
                    <p class="text-muted mb-3">
                        Los aparatos vuelven al stock y la venta sale de los totales.
                        <strong>No se borra</strong>: queda en el histórico con su motivo.
                    </p>

                    <div class="mb-4 text-start">
                        <label for="v-motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                        <input type="text" id="v-motivo" wire:model.live.debounce.400ms="motivoAnulacion"
                            class="form-control @error('motivoAnulacion') is-invalid @enderror"
                            placeholder="Ej. El cliente devolvió el equipo" maxlength="500">
                        @error('motivoAnulacion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger modal-eliminar-btn w-100" wire:click="anular"
                            wire:loading.attr="disabled" wire:target="anular">
                            <span wire:loading.remove wire:target="anular">Sí, anular</span>
                            <span wire:loading wire:target="anular">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Anulando...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Modal de recibo ===================== --}}
    <div class="modal fade zoomIn" id="modalRecibo" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-recibo-dialog">
            <div class="modal-content border-0 modal-recibo-content">
                <div class="modal-body p-0">
                    @if ($reciboVenta)
                        @php $rv = $reciboVenta; @endphp

                        {{-- Header --}}
                        <div class="modal-recibo-header">
                            <div class="d-flex align-items-center justify-content-between w-100">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="modal-recibo-icon">
                                        <i class="ri-file-text-line"></i>
                                    </div>
                                    <div>
                                        <h5 class="modal-recibo-title mb-0">Recibo de venta</h5>
                                        <small class="modal-recibo-subtitle">{{ $rv->codigo }}</small>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm modal-recibo-close" wire:click="cerrarRecibo">
                                    <i class="ri-close-line"></i>
                                </button>
                            </div>
                        </div>

                        {{-- Preview del recibo --}}
                        <div class="modal-recibo-body">
                            <div class="modal-recibo-paper">
                                {{-- Estado anulada --}}
                                @if ($rv->esta_anulada)
                                    <div class="recibo-anulada-banner">
                                        <i class="ri-error-warning-line"></i>
                                        <span>ANULADA · {{ $rv->anulada_en?->format('d/m/Y H:i') }}</span>
                                        @if ($rv->motivo_anulacion)
                                            <small>{{ $rv->motivo_anulacion }}</small>
                                        @endif
                                    </div>
                                @endif

                                {{-- Tienda --}}
                                <div class="recibo-center">
                                    <div class="recibo-tienda">{{ config('app.name') }}</div>
                                    <div class="recibo-titulo">RECIBO DE VENTA</div>
                                    <div class="recibo-codigo">{{ $rv->codigo }}</div>
                                </div>

                                <div class="recibo-separador"></div>

                                {{-- Datos generales --}}
                                <table class="recibo-datos">
                                    <tr>
                                        <td class="recibo-etiqueta">Fecha</td>
                                        <td class="recibo-valor">{{ $rv->vendida_en->format('d/m/Y H:i') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="recibo-etiqueta">Cliente</td>
                                        <td class="recibo-valor">{{ $rv->cliente?->persona?->nombre_completo ?? 'Público general' }}</td>
                                    </tr>
                                    @if ($rv->cliente?->persona?->carnet)
                                        <tr>
                                            <td class="recibo-etiqueta">Carnet</td>
                                            <td class="recibo-valor">{{ $rv->cliente->persona->carnet }}</td>
                                        </tr>
                                    @endif
                                    <tr>
                                        <td class="recibo-etiqueta">Atendió</td>
                                        <td class="recibo-valor">{{ $rv->user?->name ?? '—' }}</td>
                                    </tr>
                                </table>

                                <div class="recibo-separador"></div>

                                {{-- Líneas --}}
                                <table class="recibo-lineas">
                                    @foreach ($rv->detalles as $detalle)
                                        @php
                                            $importe = (float) $detalle->precio_unitario - (float) $detalle->descuento;
                                        @endphp
                                        <tr>
                                            <td>{{ $detalle->producto?->nombre ?? 'Producto' }}</td>
                                            <td class="recibo-importe">{{ number_format($importe, 2, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <td colspan="2" class="recibo-detalle-linea">
                                                {{ $detalle->unidad?->codigo_interno }}
                                                @if ($detalle->unidad?->serial) · S/N {{ $detalle->unidad->serial }} @endif
                                                @if ((float) $detalle->descuento > 0)
                                                    <br>Precio {{ number_format((float) $detalle->precio_unitario, 2, ',', '.') }} · Descuento −{{ number_format((float) $detalle->descuento, 2, ',', '.') }}
                                                @endif
                                                @if ($detalle->unidad?->garantia_hasta)
                                                    <br>Garantía hasta {{ $detalle->unidad->garantia_hasta->format('d/m/Y') }}
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>

                                <div class="recibo-separador"></div>

                                {{-- Totales --}}
                                <table class="recibo-totales">
                                    <tr>
                                        <td class="recibo-tenue">Subtotal</td>
                                        <td class="recibo-valor">Bs {{ number_format((float) $rv->subtotal, 2, ',', '.') }}</td>
                                    </tr>
                                    @if ((float) $rv->descuento > 0)
                                        <tr>
                                            <td class="recibo-tenue">Descuentos</td>
                                            <td class="recibo-valor text-danger">− Bs {{ number_format((float) $rv->descuento, 2, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                    <tr class="recibo-total-final">
                                        <td>TOTAL</td>
                                        <td class="recibo-valor">Bs {{ number_format((float) $rv->total, 2, ',', '.') }}</td>
                                    </tr>
                                </table>

                                <div class="recibo-separador"></div>

                                {{-- Pago --}}
                                <table class="recibo-datos">
                                    <tr>
                                        <td class="recibo-etiqueta">Pago</td>
                                        <td class="recibo-valor">{{ $metodosPago[$rv->metodo_pago] ?? $rv->metodo_pago }}</td>
                                    </tr>
                                    @if ($rv->metodo_pago === 'mixto')
                                        <tr>
                                            <td class="recibo-etiqueta">En efectivo</td>
                                            <td class="recibo-valor">Bs {{ number_format((float) $rv->monto_efectivo, 2, ',', '.') }}</td>
                                        </tr>
                                        <tr>
                                            <td class="recibo-etiqueta">Por QR</td>
                                            <td class="recibo-valor">Bs {{ number_format((float) $rv->monto_qr, 2, ',', '.') }}</td>
                                        </tr>
                                    @endif
                                    @if ($rv->qrCobro)
                                        <tr>
                                            <td class="recibo-etiqueta">QR</td>
                                            <td class="recibo-valor">{{ $rv->qrCobro->nombre }}</td>
                                        </tr>
                                    @endif
                                </table>

                                @if ($rv->notas)
                                    <div class="recibo-separador"></div>
                                    <div class="recibo-notas">{{ $rv->notas }}</div>
                                @endif

                                <div class="recibo-separador"></div>

                                {{-- Pie --}}
                                <div class="recibo-pie">
                                    {{ $rv->detalles->count() }} {{ $rv->detalles->count() === 1 ? 'aparato' : 'aparatos' }}
                                    · Emitido el {{ now()->format('d/m/Y H:i') }}
                                    <br>Conserva este recibo para cualquier reclamo de garantía.
                                    <br>¡Gracias por su compra!
                                </div>
                            </div>
                        </div>

                        {{-- Footer con acciones --}}
                        <div class="modal-recibo-footer">
                            <button type="button" class="btn btn-light modal-recibo-cancelar" wire:click="cerrarRecibo">
                                <i class="ri-close-line align-bottom me-1"></i> Cerrar
                            </button>
                            <a href="{{ route('ventas.recibo', $rv) }}" target="_blank" rel="noopener"
                                class="btn modal-recibo-descargar">
                                <i class="ri-file-download-line align-bottom me-1"></i> Descargar PDF
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @script
    <script>
        const Rc = window.ReportesCharts;
        Rc.initVentasPago('chart-ventas-pago', @js([$this->ingresosPorPago['efectivo'], $this->ingresosPorPago['qr']]));
    </script>
    @endscript

    @script
    <script>
        const Rc = window.ReportesCharts;
        Rc.initVentasEvolucion('chart-ventas-evolucion', @js($this->serieIngresos->all()));
    </script>
    @endscript
</div>
