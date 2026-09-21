<div class="caja-modulo">

    {{-- ===================== Precios del día pendientes ===================== --}}
    {{-- Al empezar la jornada, antes de vender hay que revisar los precios. --}}
    @if ($preciosPendientes > 0)
        <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <i class="ri-price-tag-3-line me-1"></i>
                <strong>Faltan los precios de hoy:</strong>
                {{ $preciosPendientes }}
                {{ $preciosPendientes === 1 ? 'producto con stock' : 'productos con stock' }}
                sin precio de la jornada.
            </div>
            <a href="{{ route('precios.index') }}" class="btn btn-sm btn-warning">
                Fijar precios del día
            </a>
        </div>
    @endif

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 caja-encabezado">
        <div class="card-body p-0">
            <div class="p-4 caja-hero">
                <div class="caja-hero-bg"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge text-white mb-3 caja-chip">
                            <i class="ri-safe-2-line me-1"></i> Finanzas · Caja
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title rounded-3 fs-3"
                                      style="background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.24);">
                                    <i class="ri-money-dollar-circle-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Control de caja</h4>
                                <p class="mb-0" style="color: rgba(255,255,255,.65);">
                                    Apertura, cuadre y histórico de turnos de caja.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                            <div class="caja-hero-stat">
                                <span class="caja-hero-stat-label">Cierres hoy</span>
                                <span class="caja-hero-stat-value">
                                    @if ($puedeVer && $cierres)
                                        {{ $cierres->getCollection()->filter(fn($c) => $c->cerrada_en?->isToday())->count() }}
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                            @if ($abierta)
                                <div class="caja-hero-stat caja-hero-stat--abierta">
                                    <span class="caja-hero-stat-label">Estado</span>
                                    <span class="caja-hero-stat-value caja-hero-stat-value--ok">Abierta</span>
                                </div>
                            @endif
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
                    <div class="caja-turno-hero-bg"></div>

                    {{-- Cabecera --}}
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
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn caja-nueva-hero"
                                    wire:click="confirmarMovimiento('ingreso')">
                                    <i class="ri-add-circle-line align-bottom me-1"></i> Ingreso
                                </button>
                                <button type="button" class="btn caja-nueva-hero caja-nueva-hero--retiro"
                                    wire:click="confirmarMovimiento('retiro')">
                                    <i class="ri-indeterminate-circle-line align-bottom me-1"></i> Retiro
                                </button>
                                <button type="button" class="btn caja-nueva-hero caja-nueva-hero--cerrar"
                                    wire:click="confirmarCierre">
                                    <i class="ri-safe-2-line align-bottom me-1"></i> Cerrar y cuadrar
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- KPIs del turno --}}
                    <div class="row g-2 mt-3 position-relative" style="z-index: 1">
                        <div class="col-6 col-lg-3">
                            <div class="caja-kpi">
                                <div class="caja-kpi-icon">
                                    <i class="ri-wallet-3-line"></i>
                                </div>
                                <div class="caja-kpi-body">
                                    <span class="caja-kpi-label">Fondo</span>
                                    <span class="caja-kpi-valor">Bs {{ number_format((float) $abierta->monto_inicial, 2, ',', '.') }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-6 col-lg-3">
                            <div class="caja-kpi">
                                <div class="caja-kpi-icon caja-kpi-icon--ventas">
                                    <i class="ri-shopping-cart-2-line"></i>
                                </div>
                                <div class="caja-kpi-body">
                                    <span class="caja-kpi-label">Ventas</span>
                                    <span class="caja-kpi-valor">{{ $ventasDelTurno }}</span>
                                </div>
                            </div>
                        </div>

                        @if ((float) $movimientosNeto !== 0.0)
                            <div class="col-6 col-lg-3">
                                <div class="caja-kpi {{ (float) $movimientosNeto > 0 ? 'caja-kpi--ok' : 'caja-kpi--mal' }}">
                                    <div class="caja-kpi-icon caja-kpi-icon--neto">
                                        <i class="ri-exchange-line"></i>
                                    </div>
                                    <div class="caja-kpi-body">
                                        <span class="caja-kpi-label">Neto movimientos</span>
                                        <span class="caja-kpi-valor">
                                            {{ (float) $movimientosNeto >= 0 ? '+' : '−' }}
                                            Bs {{ number_format(abs((float) $movimientosNeto), 2, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($esperado !== null)
                            <div class="col-6 col-lg-3">
                                <div class="caja-kpi">
                                    <div class="caja-kpi-icon caja-kpi-icon--esperado">
                                        <i class="ri-bar-chart-grouped-line"></i>
                                    </div>
                                    <div class="caja-kpi-body">
                                        <span class="caja-kpi-label">Debería haber</span>
                                        <span class="caja-kpi-valor">Bs {{ number_format((float) $esperado, 2, ',', '.') }}</span>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Alerta de sueltas --}}
                    @if ($sueltas > 0)
                        <div class="caja-alerta mt-3 position-relative" style="z-index: 1">
                            <i class="ri-alert-line"></i>
                            <div>
                                <strong>{{ $sueltas }}</strong>
                                {{ $sueltas === 1 ? 'venta en efectivo' : 'ventas en efectivo' }}
                                de este horario no quedaron atadas a la caja. No cuentan en el
                                cuadre; revísalas antes de cerrar.
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="card border-0 shadow-sm caja-turno-card mb-4">
            <div class="card-body p-0">
                <div class="caja-turno-hero caja-turno-hero--cerrada">
                    <div class="caja-turno-hero-bg"></div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 position-relative" style="z-index: 1">
                        <div class="d-flex align-items-center gap-3">
                            <div class="caja-cerrada-icon">
                                <i class="ri-safe-2-line"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 text-white">No hay ninguna caja abierta</h5>
                                <p class="mb-0 fs-13" style="color: rgba(255,255,255,.6)">
                                    Las ventas se registran igual, pero no entran en ningún cuadre.
                                </p>
                            </div>
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

    {{-- ===================== Movimientos del turno ===================== --}}
    @if ($abierta && $movimientos->isNotEmpty())
        <div class="card border-0 shadow-sm mb-4 caja-movimientos-card">
            <div class="caja-section-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="caja-section-icon">
                        <i class="ri-exchange-line"></i>
                    </div>
                    <h5 class="card-title mb-0">Movimientos de efectivo</h5>
                </div>
                <span class="caja-neto-badge {{ (float) $movimientosNeto >= 0 ? 'caja-neto-badge--ok' : 'caja-neto-badge--mal' }}">
                    Neto: {{ (float) $movimientosNeto >= 0 ? '+' : '−' }}
                    Bs {{ number_format(abs((float) $movimientosNeto), 2, ',', '.') }}
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 caja-tabla-mov">
                        <thead>
                            <tr class="text-uppercase fs-11 text-muted">
                                <th class="ps-4">Tipo</th>
                                <th>Motivo</th>
                                <th>Registró</th>
                                <th class="text-end pe-4">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($movimientos as $mov)
                                <tr wire:key="mov-{{ $mov->id }}">
                                    <td class="ps-4">
                                        @if ($mov->tipo === 'ingreso')
                                            <span class="caja-estado caja-estado--ingreso">
                                                <i class="ri-arrow-up-line"></i> Ingreso
                                            </span>
                                        @else
                                            <span class="caja-estado caja-estado--retiro">
                                                <i class="ri-arrow-down-line"></i> Retiro
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $mov->motivo }}</td>
                                    <td>
                                        <span class="caja-mov-user">{{ $mov->user?->name ?? '—' }}</span>
                                        <small class="text-muted d-block">{{ $mov->created_at?->format('H:i') }}</small>
                                    </td>
                                    <td class="text-end pe-4 caja-num {{ $mov->tipo === 'retiro' ? 'text-danger' : 'text-success' }}">
                                        <span class="caja-monto {{ $mov->tipo === 'retiro' ? 'caja-monto--retiro' : 'caja-monto--ingreso' }}">
                                            {{ $mov->tipo === 'retiro' ? '−' : '+' }}
                                            {{ number_format((float) $mov->monto, 2, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== Histórico de cierres ===================== --}}
    @if ($puedeVer)
        <div class="card border-0 shadow-sm caja-listado">
            <div class="caja-section-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="caja-section-icon caja-section-icon--historial">
                        <i class="ri-history-line"></i>
                    </div>
                    <div>
                        <h5 class="card-title mb-0">Cierres anteriores</h5>
                        <small class="text-muted">Historial de turnos y cuadres</small>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 tabla-caja">
                        <thead>
                            <tr class="text-uppercase fs-11 text-muted">
                                <th class="ps-4">Turno</th>
                                <th>Cerró</th>
                                <th class="text-center">Ventas</th>
                                <th class="text-end">Esperado</th>
                                <th class="text-end">Contado</th>
                                <th class="text-end pe-4">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($cierres as $caja)
                                <tr wire:key="caja-{{ $caja->id }}">
                                    <td class="ps-4">
                                        <div class="caja-turno-fecha">
                                            {{ $caja->abierta_en->translatedFormat('d \d\e F') }}
                                        </div>
                                        <small class="caja-turno-horario">
                                            {{ $caja->abierta_en->format('H:i') }} –
                                            {{ $caja->cerrada_en?->format('H:i') }}
                                        </small>
                                        <small class="caja-turno-cajero d-block">
                                            {{ $caja->abiertaPor?->name }}
                                        </small>
                                    </td>
                                    <td>
                                        <span class="caja-turno-cajero">{{ $caja->cerradaPor?->name ?? '—' }}</span>
                                    </td>
                                    <td class="text-center">
                                        <span class="caja-ventas-count">{{ $caja->ventas_count }}</span>
                                    </td>
                                    <td class="text-end caja-num">
                                        {{ number_format((float) $caja->monto_esperado, 2, ',', '.') }}
                                    </td>
                                    <td class="text-end caja-num">
                                        {{ number_format((float) $caja->monto_declarado, 2, ',', '.') }}
                                    </td>
                                    <td class="text-end pe-4">
                                        @if ($caja->cuadra)
                                            <span class="caja-estado caja-estado--cuadra">
                                                <i class="ri-check-line"></i> Cuadra
                                            </span>
                                        @elseif ($caja->falta)
                                            <span class="caja-estado caja-estado--falta">
                                                <i class="ri-arrow-down-line"></i>
                                                Faltan {{ number_format(abs((float) $caja->diferencia), 2, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="caja-estado caja-estado--sobra">
                                                <i class="ri-arrow-up-line"></i>
                                                Sobran {{ number_format((float) $caja->diferencia, 2, ',', '.') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="caja-empty text-center py-5">
                                            <div class="caja-empty-icon mx-auto mb-3">
                                                <i class="ri-safe-2-line"></i>
                                            </div>
                                            <h6 class="mb-1">Todavía no se ha cerrado ninguna caja</h6>
                                            <p class="text-muted mb-0 fs-13">
                                                Los cierres aparecerán aquí una vez que se complete un turno.
                                            </p>
                                        </div>
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
    <div class="modal fade caja-modal" id="modalAbrirCaja" tabindex="-1" aria-hidden="true"
         wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 caja-modal-content">
                <div class="caja-modal-header caja-modal-header--abrir">
                    <div class="d-flex align-items-center gap-3">
                        <div class="caja-modal-icon">
                            <i class="ri-inbox-unarchive-line"></i>
                        </div>
                        <div>
                            <h5 class="modal-title">Abrir caja</h5>
                            <small style="color: rgba(255,255,255,.7)">Configura el fondo inicial del turno</small>
                        </div>
                    </div>
                    <button type="button" class="btn caja-modal-close" data-bs-dismiss="modal" aria-label="Cerrar">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="monto-inicial" class="form-label fw-semibold">¿Con cuánto empieza el cajón?</label>
                        <div class="input-group caja-input-group">
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
                        <label for="notas-apertura" class="form-label fw-semibold">Notas <span class="text-muted">(opcional)</span></label>
                        <textarea id="notas-apertura" rows="2" class="form-control"
                                  wire:model="notas" placeholder="Turno de la mañana, caja 1..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light caja-btn" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary caja-btn caja-btn--primary" wire:click="abrir"
                            wire:loading.attr="disabled" wire:target="abrir">
                        <i class="ri-inbox-unarchive-line align-bottom me-1"></i> Abrir caja
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Cerrar ===================== --}}
    <div class="modal fade caja-modal" id="modalCerrarCaja" tabindex="-1" aria-hidden="true"
         wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 caja-modal-content">
                <div class="caja-modal-header caja-modal-header--cerrar">
                    <div class="d-flex align-items-center gap-3">
                        <div class="caja-modal-icon">
                            <i class="ri-safe-2-line"></i>
                        </div>
                        <div>
                            <h5 class="modal-title">Cerrar y cuadrar</h5>
                            <small style="color: rgba(255,255,255,.7)">Cuenta el efectivo y registra el cierre</small>
                        </div>
                    </div>
                    <button type="button" class="btn caja-modal-close" data-bs-dismiss="modal" aria-label="Cerrar">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="monto-declarado" class="form-label fw-semibold">¿Cuánto contaste en el cajón?</label>
                        <div class="input-group caja-input-group">
                            <span class="input-group-text">Bs</span>
                            <input type="number" step="0.01" min="0" id="monto-declarado"
                                   class="form-control @error('montoDeclarado') is-invalid @enderror"
                                   wire:model="montoDeclarado" placeholder="0.00" autofocus>
                            @error('montoDeclarado')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="caja-info-box">
                        <i class="ri-information-line"></i>
                        <div>
                            Cuenta los billetes y monedas <strong>antes</strong> de mirar el sistema.
                            Al confirmar te dirá si cuadra.
                        </div>
                    </div>

                    <div>
                        <label for="notas-cierre" class="form-label fw-semibold">Notas <span class="text-muted">(opcional)</span></label>
                        <textarea id="notas-cierre" rows="2" class="form-control"
                                  wire:model="notas" placeholder="Se pagó un flete de la caja, faltó un billete..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light caja-btn" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary caja-btn caja-btn--primary" wire:click="cerrar"
                            wire:loading.attr="disabled" wire:target="cerrar">
                        <span wire:loading.remove wire:target="cerrar">
                            <i class="ri-safe-2-line align-bottom me-1"></i> Cerrar caja
                        </span>
                        <span wire:loading wire:target="cerrar">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span> Cuadrando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Movimiento de caja ===================== --}}
    <div class="modal fade caja-modal" id="modalMovimientoCaja" tabindex="-1" aria-hidden="true"
         wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 caja-modal-content">
                <div class="caja-modal-header {{ $tipoMovimiento === 'retiro' ? 'caja-modal-header--retiro' : 'caja-modal-header--ingreso' }}">
                    <div class="d-flex align-items-center gap-3">
                        <div class="caja-modal-icon">
                            <i class="ri-{{ $tipoMovimiento === 'retiro' ? 'indeterminate-circle' : 'add-circle' }}-line"></i>
                        </div>
                        <div>
                            <h5 class="modal-title">
                                {{ $tipoMovimiento === 'retiro' ? 'Retiro de caja' : 'Ingreso a caja' }}
                            </h5>
                            <small style="color: rgba(255,255,255,.7)">Registra un movimiento de efectivo</small>
                        </div>
                    </div>
                    <button type="button" class="btn caja-modal-close" data-bs-dismiss="modal" aria-label="Cerrar">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="monto-movimiento" class="form-label fw-semibold">Importe</label>
                        <div class="input-group caja-input-group">
                            <span class="input-group-text">Bs</span>
                            <input type="number" step="0.01" min="0.01" id="monto-movimiento"
                                   class="form-control @error('montoMovimiento') is-invalid @enderror"
                                   wire:model="montoMovimiento" placeholder="0.00" autofocus>
                            @error('montoMovimiento')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="motivo-movimiento" class="form-label fw-semibold">¿Para qué es?</label>
                        <input type="text" id="motivo-movimiento" maxlength="255"
                               class="form-control @error('motivoMovimiento') is-invalid @enderror"
                               wire:model="motivoMovimiento"
                               placeholder="{{ $tipoMovimiento === 'retiro' ? 'Flete a Santa Cruz, pago al proveedor...' : 'Ingreso extraordinario, reposición...' }}">
                        @error('motivoMovimiento')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted d-block mt-2">
                            Queda en el arqueo del turno: es lo que explica por qué el cajón
                            no cuadra con las ventas.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light caja-btn" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button"
                            class="btn caja-btn {{ $tipoMovimiento === 'retiro' ? 'btn-danger caja-btn--danger' : 'btn-primary caja-btn--primary' }}"
                            wire:click="registrarMovimiento" wire:loading.attr="disabled"
                            wire:target="registrarMovimiento">
                        <span wire:loading.remove wire:target="registrarMovimiento">
                            <i class="ri-{{ $tipoMovimiento === 'retiro' ? 'indeterminate-circle' : 'add-circle' }}-line align-bottom me-1"></i>
                            {{ $tipoMovimiento === 'retiro' ? 'Registrar retiro' : 'Registrar ingreso' }}
                        </span>
                        <span wire:loading wire:target="registrarMovimiento">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
