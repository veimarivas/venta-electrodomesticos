<div class="items-modulo autorizaciones-modulo" @if ($this->pendientes->isNotEmpty()) wire:poll.20s @endif>
    @php
        $pendientes = $this->pendientes;
        $resueltas = $this->resueltas;
    @endphp

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="aut-hero-bg"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-shield-keyhole-line me-1"></i> Ventas · Autorizaciones
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title rounded-3 fs-3"
                                      style="background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.24);">
                                    <i class="ri-price-tag-3-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Autorizaciones de descuento</h4>
                                <p class="mb-0" style="color: rgba(255,255,255,.65);">
                                    Rebajas por debajo del mínimo que un vendedor pide autorizar.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end gap-3">
                            <div class="aut-hero-stat">
                                <span class="aut-hero-stat-label">Pendientes</span>
                                <span class="aut-hero-stat-value">{{ $pendientes->count() }}</span>
                            </div>
                            <div class="aut-hero-stat aut-hero-stat--resueltas">
                                <span class="aut-hero-stat-label">Resueltas hoy</span>
                                <span class="aut-hero-stat-value">{{ $resueltas->filter(fn($s) => $s->resuelto_en?->isToday())->count() }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ===================== Pendientes ===================== --}}
        <div class="col-xl-7">
            <div class="aut-section-header mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="aut-section-icon">
                        <i class="ri-time-line"></i>
                    </div>
                    <h5 class="mb-0">Pendientes</h5>
                    @if ($pendientes->isNotEmpty())
                        <span class="aut-contador">{{ $pendientes->count() }}</span>
                    @endif
                </div>
                <small class="text-muted">Solicitudes que requieren tu decisión</small>
            </div>

            @forelse ($pendientes as $solicitud)
                @php
                    $minimo = max((float) $solicitud->precio_lista - (float) $solicitud->descuento_maximo, 0);
                    $margen = (float) $solicitud->precio_solicitado - (float) $solicitud->costo_unitario;
                    $pctMargen = $solicitud->costo_unitario > 0 ? round(($margen / $solicitud->costo_unitario) * 100) : 0;
                @endphp

                <article class="aut-card mb-3" wire:key="aut-{{ $solicitud->id }}">
                    <div class="aut-card-cabecera">
                        <div class="min-w-0 flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h6 class="aut-card-producto">{{ $solicitud->producto?->nombre ?? 'Producto' }}</h6>
                            </div>
                            <div class="aut-card-meta">
                                <span><i class="ri-barcode-line"></i> {{ $solicitud->unidad?->codigo_interno }}</span>
                                @if ($solicitud->unidad?->serial)
                                    <span><i class="ri-fingerprint-line"></i> {{ $solicitud->unidad->serial }}</span>
                                @endif
                                <span><i class="ri-user-3-line"></i> {{ $solicitud->solicitante?->name ?? 'Vendedor' }}</span>
                                <span><i class="ri-time-line"></i> {{ $solicitud->created_at?->format('d/m H:i') }}</span>
                            </div>
                        </div>
                        <span class="aut-pendiente">
                            <span class="aut-latido"></span> Pendiente
                        </span>
                    </div>

                    <div class="aut-card-cifras">
                        <div class="aut-cifra">
                            <span class="aut-cifra-label">Precio de lista</span>
                            <span class="aut-cifra-valor">Bs {{ number_format((float) $solicitud->precio_lista, 2, ',', '.') }}</span>
                        </div>
                        <div class="aut-cifra">
                            <span class="aut-cifra-label">Mínimo autorizado</span>
                            <span class="aut-cifra-valor">Bs {{ number_format($minimo, 2, ',', '.') }}</span>
                            <small class="aut-cifra-nota">Rebaja tope Bs {{ number_format((float) $solicitud->descuento_maximo, 2, ',', '.') }}</small>
                        </div>
                        <div class="aut-cifra">
                            <span class="aut-cifra-label">Costo de compra</span>
                            <span class="aut-cifra-valor aut-cifra-costo">Bs {{ number_format((float) $solicitud->costo_unitario, 2, ',', '.') }}</span>
                        </div>
                        <div class="aut-cifra aut-cifra-pedido">
                            <span class="aut-cifra-label">Precio pedido</span>
                            <span class="aut-cifra-valor">Bs {{ number_format((float) $solicitud->precio_solicitado, 2, ',', '.') }}</span>
                            <small class="aut-cifra-nota {{ $margen < 0 ? 'aut-cifra-nota-mal' : 'aut-cifra-nota-bien' }}">
                                Margen Bs {{ number_format($margen, 2, ',', '.') }}
                                ({{ $pctMargen }}%)
                            </small>
                        </div>
                    </div>

                    {{-- Barra visual de margen --}}
                    <div class="aut-margen-bar">
                        <div class="aut-margen-bar-track">
                            <div class="aut-margen-bar-fill {{ $margen < 0 ? 'aut-margen-bar-fill--neg' : ($pctMargen < 10 ? 'aut-margen-bar-fill--bajo' : 'aut-margen-bar-fill--ok') }}"
                                style="width: {{ min(max($pctMargen, 0), 100) }}%"></div>
                        </div>
                        <small class="aut-margen-bar-label {{ $margen < 0 ? 'aut-cifra-nota-mal' : 'aut-cifra-nota-bien' }}">
                            {{ $pctMargen }}% sobre costo
                        </small>
                    </div>

                    {{-- Formulario de monto sugerido --}}
                    @if ($sugerirId === $solicitud->id)
                        <div class="aut-form">
                            <label class="form-label fs-12 fw-semibold">Monto que autorizas (entre el costo y el mínimo)</label>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <div class="input-group aut-input-group">
                                    <span class="input-group-text">Bs</span>
                                    <input type="number" step="0.01" min="0.01"
                                        class="form-control @error('montoSugerido') is-invalid @enderror"
                                        wire:model="montoSugerido" wire:keydown.enter="confirmarSugerir">
                                </div>
                                <button type="button" class="btn btn-sm btn-primary aut-btn aut-btn--primary" wire:click="confirmarSugerir">
                                    <i class="ri-send-plane-line align-bottom me-1"></i> Autorizar este monto
                                </button>
                                <button type="button" class="btn btn-sm btn-light aut-btn" wire:click="cerrarFormularios">
                                    <i class="ri-close-line align-bottom me-1"></i> Cancelar
                                </button>
                            </div>
                            @error('montoSugerido') <div class="aut-form-error mt-1">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    {{-- Formulario de rechazo --}}
                    @if ($rechazarId === $solicitud->id)
                        <div class="aut-form aut-form--rechazo">
                            <label class="form-label fs-12 fw-semibold">Motivo del rechazo (lo verá el vendedor)</label>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <input type="text" class="form-control @error('motivoRechazo') is-invalid @enderror"
                                    style="max-width: 26rem"
                                    placeholder="Ej. El margen no alcanza para cubrir el flete"
                                    wire:model="motivoRechazo" wire:keydown.enter="confirmarRechazar">
                                <button type="button" class="btn btn-sm btn-danger aut-btn aut-btn--danger" wire:click="confirmarRechazar">
                                    <i class="ri-close-circle-line align-bottom me-1"></i> Rechazar
                                </button>
                                <button type="button" class="btn btn-sm btn-light aut-btn" wire:click="cerrarFormularios">
                                    <i class="ri-close-line align-bottom me-1"></i> Cancelar
                                </button>
                            </div>
                            @error('motivoRechazo') <div class="aut-form-error mt-1">{{ $message }}</div> @enderror
                        </div>
                    @endif

                    @if ($sugerirId !== $solicitud->id && $rechazarId !== $solicitud->id)
                        <div class="aut-card-acciones">
                            <button type="button" class="btn btn-sm btn-success aut-btn aut-btn--success" wire:click="aprobar({{ $solicitud->id }})"
                                wire:loading.attr="disabled" wire:target="aprobar({{ $solicitud->id }})">
                                <i class="ri-check-line align-bottom me-1"></i> Aprobar
                                <span class="aut-btn-monto">Bs {{ number_format((float) $solicitud->precio_solicitado, 2, ',', '.') }}</span>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-primary aut-btn aut-btn--outline" wire:click="abrirSugerir({{ $solicitud->id }})">
                                <i class="ri-edit-2-line align-bottom me-1"></i> Sugerir monto
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger aut-btn aut-btn--outline" wire:click="abrirRechazar({{ $solicitud->id }})">
                                <i class="ri-forbid-2-line align-bottom me-1"></i> Rechazar
                            </button>
                        </div>
                    @endif
                </article>
            @empty
                <div class="card border-0 shadow-sm aut-empty-card">
                    <div class="card-body text-center py-5">
                        <div class="aut-vacio-icono mx-auto mb-3">
                            <i class="ri-shield-check-line"></i>
                        </div>
                        <h6 class="mb-1">No hay solicitudes pendientes</h6>
                        <p class="text-muted mb-0 fs-13">
                            Cuando un vendedor quiera bajar del mínimo, la solicitud aparecerá aquí al instante.
                        </p>
                    </div>
                </div>
            @endforelse
        </div>

        {{-- ===================== Resueltas recientes ===================== --}}
        <div class="col-xl-5">
            <div class="aut-section-header mb-3">
                <div class="d-flex align-items-center gap-2">
                    <div class="aut-section-icon aut-section-icon--resueltas">
                        <i class="ri-check-double-line"></i>
                    </div>
                    <h5 class="mb-0">Resueltas recientes</h5>
                </div>
                <small class="text-muted">Últimas {{ $resueltas->count() }} decisiones</small>
            </div>

            <div class="card border-0 shadow-sm aut-resueltas-card">
                <div class="card-body p-0">
                    @forelse ($resueltas as $solicitud)
                        <div class="aut-resuelta" wire:key="res-{{ $solicitud->id }}">
                            <div class="aut-resuelta-icon aut-resuelta-icon--{{ $solicitud->estado }}">
                                @if ($solicitud->estado === 'aprobada')
                                    <i class="ri-check-line"></i>
                                @elseif ($solicitud->estado === 'rechazada')
                                    <i class="ri-close-line"></i>
                                @elseif ($solicitud->estado === 'consumida')
                                    <i class="ri-shopping-cart-2-line"></i>
                                @else
                                    <i class="ri-forbid-line"></i>
                                @endif
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <span class="aut-resuelta-producto">{{ $solicitud->producto?->nombre ?? 'Producto' }}</span>
                                <small class="aut-resuelta-meta">
                                    {{ $solicitud->solicitante?->name }} · {{ $solicitud->resuelto_en?->format('d/m H:i') }}
                                    @if ($solicitud->revisor) · por {{ $solicitud->revisor->name }} @endif
                                </small>
                                @if ($solicitud->motivo)
                                    <small class="aut-resuelta-motivo">
                                        <i class="ri-chat-3-line me-1"></i>{{ $solicitud->motivo }}
                                    </small>
                                @endif
                            </div>
                            <div class="text-end flex-shrink-0">
                                <span class="aut-estado aut-estado--{{ $solicitud->estado }}">
                                    {{ \App\Models\SolicitudDescuento::ESTADOS[$solicitud->estado] ?? $solicitud->estado }}
                                </span>
                                @if ($solicitud->precio_aprobado !== null)
                                    <span class="aut-resuelta-monto">Bs {{ number_format((float) $solicitud->precio_aprobado, 2, ',', '.') }}</span>
                                @else
                                    <span class="aut-resuelta-monto aut-resuelta-monto-pedido">Pedía Bs {{ number_format((float) $solicitud->precio_solicitado, 2, ',', '.') }}</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="aut-resueltas-vacio">
                            <i class="ri-file-list-3-line"></i>
                            <span>Todavía no se ha resuelto ninguna solicitud.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
