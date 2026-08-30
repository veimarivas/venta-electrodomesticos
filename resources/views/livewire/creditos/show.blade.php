<div class="credito-ficha">

    @php
        $saldo = $credito->saldoEnCentavos();
        $pagado = \App\Support\ProrrateoDeGastos::aCentavos($credito->total_financiado) - $saldo;
    @endphp

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div class="min-w-0">
                    <h4 class="mb-1">
                        {{ $credito->cliente?->persona?->nombre_completo ?? 'Sin nombre' }}
                    </h4>
                    <p class="text-muted mb-0 fs-13">
                        {{ $credito->cliente?->codigo }} ·
                        <a href="{{ route('ventas.show', $credito->venta) }}">{{ $credito->venta->codigo }}</a>
                        · {{ $credito->venta->vendida_en?->format('d/m/Y') }}
                        @if ($credito->cliente?->persona?->celular)
                            · {{ $credito->cliente->persona->celular }}
                        @endif
                    </p>
                </div>

                <div class="d-flex align-items-center gap-2">
                    @if ($credito->estado === 'anulado')
                        <span class="badge bg-secondary-subtle text-secondary fs-13">Anulado</span>
                    @elseif ($credito->estado === 'pagado')
                        <span class="badge bg-success-subtle text-success fs-13">Pagado</span>
                    @elseif ($credito->esta_en_mora)
                        <span class="badge bg-danger-subtle text-danger fs-13">Con cuotas vencidas</span>
                    @else
                        <span class="badge bg-primary-subtle text-primary fs-13">Al día</span>
                    @endif

                    @if ($puedeCobrar && $credito->esta_vigente)
                        <button type="button" class="btn btn-primary" wire:click="abrirCobro">
                            <i class="ri-hand-coin-line align-bottom me-1"></i> Registrar pago
                        </button>
                    @endif
                </div>
            </div>

            <div class="row g-3 mt-1">
                <div class="col-sm-6 col-lg-3">
                    <div class="caja-dato">
                        <span class="caja-dato-label">Cuota inicial</span>
                        <span class="caja-dato-valor">Bs
                            {{ number_format((float) $credito->cuota_inicial, 2, ',', '.') }}</span>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="caja-dato">
                        <span class="caja-dato-label">Financiado</span>
                        <span class="caja-dato-valor">Bs
                            {{ number_format((float) $credito->total_financiado, 2, ',', '.') }}</span>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="caja-dato">
                        <span class="caja-dato-label">Pagado</span>
                        <span class="caja-dato-valor">Bs {{ number_format($pagado / 100, 2, ',', '.') }}</span>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="caja-dato">
                        <span class="caja-dato-label">Saldo</span>
                        <span class="caja-dato-valor">Bs {{ number_format($saldo / 100, 2, ',', '.') }}</span>
                    </div>
                </div>
            </div>

            @if ($credito->notas)
                <div class="alert alert-warning alert-borderless mt-3 mb-0 fs-13">
                    <i class="ri-sticky-note-line align-bottom me-1"></i>
                    {!! nl2br(e($credito->notas)) !!}
                </div>
            @endif
        </div>
    </div>

    <div class="row g-4">
        {{-- ===================== Plan de cuotas ===================== --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">Plan de cuotas</h5>
                    <small class="text-muted fs-13">
                        {{ $credito->numero_cuotas }}
                        {{ $credito->numero_cuotas === 1 ? 'cuota mensual' : 'cuotas mensuales' }}
                        desde el {{ $credito->primer_vencimiento->format('d/m/Y') }}
                    </small>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">#</th>
                                    <th>Vence</th>
                                    <th class="text-end">Monto</th>
                                    <th class="text-end">Pagado</th>
                                    <th class="pe-4">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($credito->cuotas as $cuota)
                                    <tr wire:key="cuota-{{ $cuota->id }}"
                                        @class(['table-active' => $proxima && $cuota->id === $proxima->id])>
                                        <td class="ps-4">{{ $cuota->numero }}</td>
                                        <td>{{ $cuota->vence_en->format('d/m/Y') }}</td>
                                        <td class="text-end">
                                            Bs {{ number_format((float) $cuota->monto, 2, ',', '.') }}</td>
                                        <td class="text-end">
                                            Bs {{ number_format((float) $cuota->monto_pagado, 2, ',', '.') }}</td>
                                        <td class="pe-4">
                                            @if ($cuota->esta_pagada)
                                                <span
                                                    class="badge bg-success-subtle text-success">{{ $cuota->etiqueta_estado }}</span>
                                            @elseif ($cuota->esta_vencida)
                                                <span
                                                    class="badge bg-danger-subtle text-danger">{{ $cuota->etiqueta_estado }}</span>
                                            @else
                                                <span
                                                    class="badge bg-light text-body">{{ $cuota->etiqueta_estado }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Pagos recibidos ===================== --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent">
                    <h5 class="card-title mb-0">Pagos recibidos</h5>
                    <small class="text-muted fs-13">Del más reciente al más antiguo</small>
                </div>

                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @forelse ($credito->pagos as $pago)
                            <div class="list-group-item px-0 d-flex align-items-center gap-3"
                                wire:key="pago-{{ $pago->id }}">
                                <div class="flex-grow-1 min-w-0">
                                    <span class="fw-semibold d-block">
                                        Bs {{ number_format((float) $pago->monto, 2, ',', '.') }}
                                        <small class="text-muted fw-normal">a la cuota
                                            {{ $pago->cuota?->numero }}</small>
                                    </span>
                                    <small class="text-muted d-block text-truncate">
                                        {{ $pago->recibo }} ·
                                        {{ $pago->pagado_en->format('d/m/Y H:i') }} ·
                                        {{ \App\Models\PagoCredito::METODOS_PAGO[$pago->metodo_pago] ?? $pago->metodo_pago }}
                                        @if ($pago->user) · {{ $pago->user->name }} @endif
                                    </small>
                                </div>
                            </div>
                        @empty
                            <p class="text-muted text-center py-4 mb-0">
                                Todavía no se ha cobrado ninguna cuota.
                            </p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Cobrar ===================== --}}
    <div class="modal fade" id="modalCobrarCuota" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar pago</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="monto-pago" class="form-label">¿Cuánto entrega el cliente?</label>
                        <div class="input-group">
                            <span class="input-group-text">Bs</span>
                            <input type="number" step="0.01" min="0.01" id="monto-pago"
                                class="form-control @error('monto') is-invalid @enderror" wire:model="monto">
                            @error('monto')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        {{-- El dinero se imputa solo, de la cuota más antigua a la
                             más nueva: elegir cuál se paga permitiría saldar la de
                             diciembre dejando viva la de agosto. --}}
                        <small class="text-muted d-block mt-2">
                            Se aplica a la cuota más antigua que quede debiendo. Saldo actual:
                            <strong>Bs {{ number_format($saldo / 100, 2, ',', '.') }}</strong>.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="metodo-pago-cuota" class="form-label">¿Cómo paga?</label>
                        <select id="metodo-pago-cuota" class="form-select" wire:model.live="metodoPago">
                            @foreach ($metodos as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if (in_array($metodoPago, \App\Models\PagoCredito::METODOS_CON_RESPALDO, true))
                        <div class="mb-3">
                            <label for="comprobante-cuota" class="form-label">Número de comprobante</label>
                            <input type="text" id="comprobante-cuota"
                                class="form-control @error('comprobante') is-invalid @enderror"
                                wire:model="comprobante" placeholder="El que da el banco">
                            @error('comprobante')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    @endif

                    <div>
                        <label for="notas-pago" class="form-label">Notas <span
                                class="text-muted">(opcional)</span></label>
                        <textarea id="notas-pago" rows="2" class="form-control" wire:model="notas"
                            placeholder="Pagó su hijo, trajo dos cuotas juntas..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="cobrar" wire:loading.attr="disabled"
                        wire:target="cobrar">Registrar pago</button>
                </div>
            </div>
        </div>
    </div>
</div>
