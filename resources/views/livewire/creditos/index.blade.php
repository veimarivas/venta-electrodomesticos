<div class="creditos-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-hand-coin-line me-1"></i> Finanzas · Créditos
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-wallet-3-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Cartera de créditos</h4>
                                <p class="text-white-50 mb-0">
                                    Créditos otorgados, cuotas por cobrar y seguimiento de mora.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4 crud-kpis">
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="En la calle" icon="bx-wallet" color="primary"
                value="Bs {{ number_format($this->carteraEnCentavos / 100, 2, ',', '.') }}"
                caption="Saldo de los créditos vigentes" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Vencido" icon="bx-error-circle" color="danger"
                value="Bs {{ number_format($this->moraEnCentavos / 100, 2, ',', '.') }}"
                caption="Cuotas pasadas de fecha" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Vence esta semana" icon="bx-calendar-event" color="warning"
                value="Bs {{ number_format($this->porVencerEnCentavos / 100, 2, ',', '.') }}"
                caption="Próximos {{ \App\Livewire\Creditos\Index::DIAS_PROXIMOS }} días" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Clientes con deuda" icon="bx-user-voice" color="info"
                value="{{ $this->clientesConDeuda }}" caption="Con algún crédito vigente" />
        </div>
    </div>

    {{-- ===================== Tabla de créditos ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="card-header bg-transparent py-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Cartera
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando..</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">{{ $creditos->total() }}
                        {{ $creditos->total() === 1 ? 'crédito' : 'créditos' }}</small>
                </div>

                <div class="col-md-8">
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                        <div class="search-box flex-grow-1" style="max-width: 20rem">
                            <input type="text" class="form-control" placeholder="Cliente o número de venta.."
                                wire:model.live.debounce.400ms="buscar">
                            <i class="ri-search-line search-icon"></i>
                        </div>

                        <select class="form-select" style="max-width: 13rem" wire:model.live="filtro">
                            <option value="vigentes">Vigentes</option>
                            <option value="mora">Con cuotas vencidas</option>
                            <option value="proximos">Vencen esta semana</option>
                            <option value="pagados">Pagados</option>
                            <option value="anulados">Anulados</option>
                            <option value="todos">Todos</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-crud"
                    wire:loading.class="opacity-50" wire:target="buscar, filtro">
                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            <th class="ps-4">Cliente</th>
                            <th>Venta</th>
                            <th class="text-end">Financiado</th>
                            <th class="text-end">Saldo</th>
                            <th>Próxima cuota</th>
                            <th class="pe-4">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($creditos as $credito)
                            @php
                                $saldo = (float) $credito->comprometido - (float) $credito->cobrado;
                                $proxima = $credito->proximaCuota();
                            @endphp

                            <tr wire:key="credito-{{ $credito->id }}">
                                <td class="ps-4">
                                    <a href="{{ route('creditos.show', $credito) }}" class="fw-semibold">
                                        {{ $credito->cliente?->persona?->nombre_completo ?? 'Sin nombre' }}
                                    </a>
                                    <small class="text-muted d-block">{{ $credito->cliente?->codigo }}</small>
                                </td>
                                <td>
                                    <span class="unidad-codigo">{{ $credito->venta?->codigo }}</span>
                                    <small class="text-muted d-block mt-1">
                                        {{ $credito->numero_cuotas }}
                                        {{ $credito->numero_cuotas === 1 ? 'cuota' : 'cuotas' }}
                                    </small>
                                </td>
                                <td class="text-end">
                                    Bs {{ number_format((float) $credito->total_financiado, 2, ',', '.') }}
                                </td>
                                <td class="text-end fw-semibold">
                                    Bs {{ number_format($saldo, 2, ',', '.') }}
                                </td>
                                <td>
                                    @if ($proxima)
                                        <span class="d-block">{{ $proxima->vence_en->format('d/m/Y') }}</span>
                                        <small class="text-muted">
                                            Bs {{ number_format($proxima->faltaEnCentavos() / 100, 2, ',', '.') }}
                                            · cuota {{ $proxima->numero }}
                                        </small>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="pe-4">
                                    @if ($credito->estado === 'anulado')
                                        <span class="unidad-estado unidad-estado-danado">
                                            <span class="unidad-estado-dot"></span> Anulado
                                        </span>
                                    @elseif ($credito->estado === 'pagado')
                                        <span class="unidad-estado unidad-estado-stock">
                                            <span class="unidad-estado-dot"></span> Pagado
                                        </span>
                                    @elseif ($credito->esta_en_mora)
                                        <span class="unidad-estado unidad-estado-reservado">
                                            <span class="unidad-estado-dot"></span> Vencido
                                        </span>
                                    @else
                                        <span class="unidad-estado unidad-estado-garantia">
                                            <span class="unidad-estado-dot"></span> Al día
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="ri-hand-coin-line display-6 d-block mb-2"></i>
                                    No hay créditos que mostrar con este filtro.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($creditos->hasPages())
            <div class="card-footer paginacion-compacta">
                {{ $creditos->links() }}
            </div>
        @endif
    </div>
</div>
