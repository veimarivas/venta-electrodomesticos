<div class="creditos-modulo">

    {{-- Los indicadores contestan la pregunta del módulo antes de que nadie
         lea la tabla: cuánto hay en la calle y cuánto está vencido. --}}
    <div class="row g-3 mb-4">
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

    <div class="card border-0 shadow-sm">
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
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
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
                                    <span class="d-block">{{ $credito->venta?->codigo }}</span>
                                    <small class="text-muted">
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
                                        <span class="badge bg-secondary-subtle text-secondary">Anulado</span>
                                    @elseif ($credito->estado === 'pagado')
                                        <span class="badge bg-success-subtle text-success">Pagado</span>
                                    @elseif ($credito->esta_en_mora)
                                        {{-- Lo vencido se dice con todas las letras: es el
                                             único estado que obliga a hacer algo hoy. --}}
                                        <span class="badge bg-danger-subtle text-danger">Vencido</span>
                                    @else
                                        <span class="badge bg-primary-subtle text-primary">Al día</span>
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
