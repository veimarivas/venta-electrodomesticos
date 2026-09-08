<div class="caja-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 caja-encabezado">
        <div class="card-body p-0">
            <div class="p-4 caja-hero">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 caja-chip">
                            <i class="ri-safe-2-line me-1"></i> Finanzas · Caja
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title bg-white bg-opacity-25 text-white rounded-3 fs-3">
                                    <i class="ri-money-dollar-circle-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Control de caja</h4>
                                <p class="text-white-50 mb-0">
                                    Apertura, cuadre y histórico de turnos de caja.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Estado del turno ===================== --}}
    @if ($abierta)
        <div class="card border-0 shadow-sm caja-turno-card mb-4">
            <div class="card-body p-0">
                <div class="caja-turno-hero caja-turno-hero--abierta">
                    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 position-relative" style="z-index: 1">
                        <div class="min-w-0">
                            <span class="caja-turno-estado">
                                <span class="caja-latido"></span> Caja abierta
                            </span>
                            <h4 class="caja-turno-titulo mt-3 mb-1">
                                Bs {{ number_format((float) $abierta->monto_inicial, 2, ',', '.') }}
                                <small class="fs-14 fw-normal" style="color: rgba(255,255,255,.7)">de fondo</small>
                            </h4>
                            <p class="mb-0 fs-13" style="color: rgba(255,255,255,.6)">
                                Abierta por {{ $abierta->abiertaPor?->name ?? '—' }} ·
                                {{ $abierta->abierta_en->translatedFormat('d \d\e F, H:i') }}
                            </p>
                        </div>

                        @if ($puedeGestionar)
                            <button type="button" class="btn caja-nueva-hero" wire:click="confirmarCierre">
                                <i class="ri-safe-2-line align-bottom me-1"></i> Cerrar y cuadrar
                            </button>
                        @endif
                    </div>

                    <div class="row g-3 mt-3 position-relative" style="z-index: 1">
                        <div class="col-sm-6 col-lg-3">
                            <div class="caja-dato">
                                <span class="caja-dato-label">Ventas del turno</span>
                                <span class="caja-dato-valor">{{ $ventasDelTurno }}</span>
                            </div>
                        </div>

                        @if ($esperado !== null)
                            <div class="col-sm-6 col-lg-3">
                                <div class="caja-dato">
                                    <span class="caja-dato-label">Debería haber</span>
                                    <span class="caja-dato-valor">Bs {{ number_format((float) $esperado, 2, ',', '.') }}</span>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if ($sueltas > 0)
                        <div class="alert alert-warning alert-borderless mt-3 mb-0 fs-13 position-relative" style="z-index: 1">
                            <i class="ri-alert-line align-bottom me-1"></i>
                            Hay <strong>{{ $sueltas }}</strong>
                            {{ $sueltas === 1 ? 'venta en efectivo' : 'ventas en efectivo' }}
                            de este horario que no quedaron atadas a la caja. No cuentan en el
                            cuadre; revísalas antes de cerrar.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm caja-turno-card mb-4">
            <div class="card-body p-0">
                <div class="caja-turno-hero caja-turno-hero--cerrada">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative" style="z-index: 1">
                        <div>
                            <h5 class="mb-1 text-white">No hay ninguna caja abierta</h5>
                            <p class="mb-0 fs-13" style="color: rgba(255,255,255,.6)">
                                Las ventas se registran igual, pero no entran en ningún cuadre.
                            </p>
                        </div>

                        @if ($puedeGestionar)
                            <button type="button" class="btn caja-nueva-hero"
                                data-bs-toggle="modal" data-bs-target="#modalAbrirCaja">
                                <i class="ri-inbox-unarchive-line align-bottom me-1"></i> Abrir caja
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== Histórico de cierres ===================== --}}
    @if ($puedeVer)
        <div class="card border-0 shadow-sm caja-listado">
            <div class="card-header bg-transparent py-3 caja-toolbar">
                <h5 class="card-title mb-0">Cierres anteriores</h5>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 tabla-caja">
                        <thead>
                            <tr class="text-uppercase fs-11 text-muted">
                                <th class="ps-4">Turno</th>
                                <th>Cerró</th>
                                <th class="text-end">Ventas</th>
                                <th class="text-end">Esperado</th>
                                <th class="text-end">Contado</th>
                                <th class="text-end pe-4">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cierres as $caja)
                                <tr wire:key="caja-{{ $caja->id }}">
                                    <td class="ps-4">
                                        <div class="fw-semibold">
                                            {{ $caja->abierta_en->translatedFormat('d \d\e F') }}
                                        </div>
                                        <small class="text-muted">
                                            {{ $caja->abierta_en->format('H:i') }} –
                                            {{ $caja->cerrada_en?->format('H:i') }} ·
                                            {{ $caja->abiertaPor?->name }}
                                        </small>
                                    </td>
                                    <td>{{ $caja->cerradaPor?->name ?? '—' }}</td>
                                    <td class="text-end">{{ $caja->ventas_count }}</td>
                                    <td class="text-end caja-num">
                                        {{ number_format((float) $caja->monto_esperado, 2, ',', '.') }}
                                    </td>
                                    <td class="text-end caja-num">
                                        {{ number_format((float) $caja->monto_declarado, 2, ',', '.') }}
                                    </td>
                                    <td class="text-end pe-4">
                                        @if ($caja->cuadra)
                                            <span class="caja-estado caja-estado-cuadra">
                                                <span class="caja-estado-dot"></span> Cuadra
                                            </span>
                                        @elseif ($caja->falta)
                                            <span class="caja-estado caja-estado-falta">
                                                <span class="caja-estado-dot"></span>
                                                Faltan {{ number_format(abs((float) $caja->diferencia), 2, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="caja-estado caja-estado-sobra">
                                                <span class="caja-estado-dot"></span>
                                                Sobran {{ number_format((float) $caja->diferencia, 2, ',', '.') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="ri-safe-2-line display-6 d-block mb-2"></i>
                                        Todavía no se ha cerrado ninguna caja.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($cierres->hasPages())
                <div class="card-footer paginacion-compacta">
                    {{ $cierres->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- ===================== Abrir ===================== --}}
    <div class="modal fade" id="modalAbrirCaja" tabindex="-1" aria-hidden="true"
         wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Abrir caja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="monto-inicial" class="form-label">¿Con cuánto empieza el cajón?</label>
                        <div class="input-group">
                            <span class="input-group-text">Bs</span>
                            <input type="number" step="0.01" min="0" id="monto-inicial"
                                   class="form-control @error('montoInicial') is-invalid @enderror"
                                   wire:model="montoInicial" placeholder="0.00">
                            @error('montoInicial')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <small class="text-muted d-block mt-2">
                            El cambio que se deja para empezar a atender. Si no dejas nada, pon 0.
                        </small>
                    </div>

                    <div>
                        <label for="notas-apertura" class="form-label">Notas <span class="text-muted">(opcional)</span></label>
                        <textarea id="notas-apertura" rows="2" class="form-control"
                                  wire:model="notas" placeholder="Turno de la mañana, caja 1..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="abrir"
                            wire:loading.attr="disabled" wire:target="abrir">Abrir caja</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Cerrar ===================== --}}
    <div class="modal fade" id="modalCerrarCaja" tabindex="-1" aria-hidden="true"
         wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Cerrar y cuadrar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="monto-declarado" class="form-label">¿Cuánto contaste en el cajón?</label>
                        <div class="input-group">
                            <span class="input-group-text">Bs</span>
                            <input type="number" step="0.01" min="0" id="monto-declarado"
                                   class="form-control @error('montoDeclarado') is-invalid @enderror"
                                   wire:model="montoDeclarado" placeholder="0.00" autofocus>
                            @error('montoDeclarado')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="alert alert-info alert-borderless mb-3 fs-13">
                        <i class="ri-information-line align-bottom me-1"></i>
                        Cuenta los billetes y monedas <strong>antes</strong> de mirar el sistema.
                        Al confirmar te dirá si cuadra.
                    </div>

                    <div>
                        <label for="notas-cierre" class="form-label">Notas <span class="text-muted">(opcional)</span></label>
                        <textarea id="notas-cierre" rows="2" class="form-control"
                                  wire:model="notas" placeholder="Se pagó un flete de la caja, faltó un billete..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="cerrar"
                            wire:loading.attr="disabled" wire:target="cerrar">
                        <span wire:loading.remove wire:target="cerrar">Cerrar caja</span>
                        <span wire:loading wire:target="cerrar">Cuadrando...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
