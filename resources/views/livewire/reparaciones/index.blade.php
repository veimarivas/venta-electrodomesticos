<div class="items-modulo">

    @php
        $pillEstado = [
            'recibida' => 'rep-estado-recibida',
            'en_reparacion' => 'rep-estado-en-reparacion',
            'esperando_repuesto' => 'rep-estado-esperando',
            'lista' => 'rep-estado-lista',
            'entregada' => 'rep-estado-entregada',
            'irreparable' => 'rep-estado-irreparable',
            'cancelada' => 'rep-estado-cancelada',
        ];
    @endphp

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-tools-line me-1"></i> Servicio Técnico
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-wrench-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Órdenes de taller</h4>
                                <p class="text-white-50 mb-0">
                                    Recepción, diagnóstico y entrega de aparatos en reparación.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @if ($puedeRecibir)
                                <button type="button" class="btn btn-light crud-nueva-hero" wire:click="abrirRecepcion">
                                    <i class="ri-add-line align-bottom me-1"></i> Recibir aparato
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4 crud-kpis">
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="En el taller" icon="bx-wrench" color="primary" value="{{ $this->enTaller }}"
                caption="Órdenes sin cerrar" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Atrasadas" icon="bx-error-circle" color="danger" value="{{ $this->atrasadas }}"
                caption="Pasó la fecha prometida" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Listas" icon="bx-check-circle" color="success" value="{{ $this->listas }}"
                caption="Esperando que las recojan" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="En garantía" icon="bx-shield" color="info" value="{{ $this->enGarantia }}"
                caption="Abiertas que no se cobran" />
        </div>
    </div>

    {{-- ===================== Tabla de órdenes ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden">
        <div class="card-header bg-transparent py-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Órdenes de taller
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando..</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">{{ $reparaciones->total() }}
                        {{ $reparaciones->total() === 1 ? 'orden' : 'órdenes' }}</small>
                </div>

                <div class="col-md-8">
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                        <div class="search-box flex-grow-1" style="max-width: 18rem">
                            <input type="text" class="form-control" placeholder="Orden, serial o cliente.."
                                wire:model.live.debounce.400ms="buscar">
                            <i class="ri-search-line search-icon"></i>
                        </div>

                        <select class="form-select" style="max-width: 12rem" wire:model.live="filtro">
                            <option value="abiertas">Abiertas</option>
                            <option value="atrasadas">Atrasadas</option>
                            <option value="en_taller">En el taller</option>
                            <option value="listas">Listas</option>
                            <option value="cerradas">Cerradas</option>
                            <option value="todas">Todas</option>
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
                            <th class="ps-4">Orden</th>
                            <th>Aparato</th>
                            <th>Falla</th>
                            <th>Estado</th>
                            <th class="text-end">Costo</th>
                            <th class="text-end pe-4" style="width: 1%;">
                                <span class="visually-hidden">Acciones</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reparaciones as $orden)
                            <tr wire:key="orden-{{ $orden->id }}">
                                <td class="ps-4">
                                    <span class="unidad-codigo">{{ $orden->codigo }}</span>
                                    <small class="text-muted d-block mt-1">
                                        {{ $orden->cliente?->persona?->nombre_completo ?? 'Sin cliente' }}
                                    </small>
                                    <small class="text-muted">
                                        Entró {{ $orden->recibida_en->format('d/m/Y') }}
                                        @if ($orden->prometida_para)
                                            · para el {{ $orden->prometida_para->format('d/m/Y') }}
                                        @endif
                                    </small>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="unidad-imagen-mini">
                                            @if ($orden->unidad?->producto?->imagen)
                                                <img src="{{ asset('storage/'.$orden->unidad->producto->imagen) }}" alt="{{ $orden->unidad->producto->nombre }}">
                                            @else
                                                <img src="{{ asset('assets/images/sin_imagen.png') }}" alt="Aparato" class="unidad-imagen-mini-sin">
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <span class="d-block text-truncate" style="max-width: 12rem">{{ $orden->unidad?->producto?->nombre ?? '—' }}</span>
                                            <small class="text-muted font-monospace">
                                                {{ $orden->unidad?->serial ?: $orden->unidad?->codigo_interno }}
                                            </small>
                                            @if ($orden->en_garantia)
                                                <span class="badge bg-info-subtle text-info d-block mt-1"
                                                    style="width: fit-content">
                                                    <i class="ri-shield-check-line align-bottom"></i> Garantía
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td style="max-width: 16rem">
                                    <small class="d-block text-truncate">{{ $orden->falla_reportada }}</small>
                                    @if ($orden->diagnostico)
                                        <small class="text-muted d-block text-truncate">
                                            <i class="ri-stethoscope-line align-bottom"></i>
                                            {{ $orden->diagnostico }}
                                        </small>
                                    @endif
                                    @if ($orden->tecnico)
                                        <small class="text-muted d-block">Atiende {{ $orden->tecnico->name }}</small>
                                    @endif
                                </td>

                                <td>
                                    @if ($orden->estado === 'entregada')
                                        <span class="unidad-estado unidad-estado-vendido">
                                            <span class="unidad-estado-dot"></span> Entregada
                                        </span>
                                        <small class="text-muted d-block">A {{ $orden->entregada_a }}</small>
                                    @elseif ($orden->estado === 'irreparable')
                                        <span class="unidad-estado unidad-estado-perdido">
                                            <span class="unidad-estado-dot"></span> Sin arreglo
                                        </span>
                                    @elseif ($orden->estado === 'cancelada')
                                        <span class="unidad-estado unidad-estado-danado">
                                            <span class="unidad-estado-dot"></span> Cancelada
                                        </span>
                                    @elseif ($orden->esta_lista)
                                        <span class="unidad-estado unidad-estado-stock">
                                            <span class="unidad-estado-dot"></span> Lista
                                        </span>
                                    @elseif ($orden->esta_atrasada)
                                        <span class="unidad-estado unidad-estado-reservado">
                                            <span class="unidad-estado-dot"></span> Atrasada
                                        </span>
                                    @else
                                        <span class="unidad-estado unidad-estado-garantia">
                                            <span class="unidad-estado-dot"></span> {{ $estados[$orden->estado] }}
                                        </span>
                                    @endif

                                    @if ($orden->esta_abierta)
                                        <small class="text-muted d-block mt-1">
                                            {{ $orden->dias_en_taller }}
                                            {{ $orden->dias_en_taller === 1 ? 'día' : 'días' }} en taller
                                        </small>
                                    @endif
                                </td>

                                <td class="text-end">
                                    @if ($orden->en_garantia)
                                        <span class="text-muted">Sin costo</span>
                                    @else
                                        <span class="fw-semibold">Bs {{ number_format((float) $orden->costo, 2, ',', '.') }}</span>
                                    @endif
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @if ($puedeAtender && in_array($orden->estado, ['recibida', 'en_reparacion', 'esperando_repuesto'], true))
                                            <button type="button" class="btn btn-sm btn-ghost-info btn-icon rounded-circle"
                                                title="Diagnóstico" wire:click="abrirDiagnostico({{ $orden->id }})">
                                                <i class="ri-stethoscope-line fs-16"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-ghost-warning btn-icon rounded-circle"
                                                title="Esperando repuesto" wire:click="abrirEspera({{ $orden->id }})">
                                                <i class="ri-time-line fs-16"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-ghost-success btn-icon rounded-circle"
                                                title="Ya está lista" wire:click="abrirCierre({{ $orden->id }})">
                                                <i class="ri-check-double-line fs-16"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon rounded-circle"
                                                title="No tiene arreglo" wire:click="abrirIrreparable({{ $orden->id }})">
                                                <i class="ri-close-circle-line fs-16"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-ghost-danger btn-icon rounded-circle"
                                                title="Cancelar" wire:click="abrirCancelacion({{ $orden->id }})">
                                                <i class="ri-delete-bin-line fs-16"></i>
                                                </button>
                                        @endif

                                        @if (($puedeRecibir || $puedeAtender) && in_array($orden->estado, ['lista', 'irreparable'], true))
                                            <button type="button" class="btn btn-sm btn-success"
                                                title="El cliente se lo lleva" wire:click="abrirEntrega({{ $orden->id }})">
                                                <i class="ri-hand-heart-line align-bottom me-1"></i> Entregar
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="ri-tools-line display-6 d-block mb-2"></i>
                                    No hay órdenes que mostrar con este filtro.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($reparaciones->hasPages())
            <div class="card-footer paginacion-compacta">
                {{ $reparaciones->links() }}
            </div>
        @endif
    </div>

    {{-- ===================== Recibir un aparato ===================== --}}
    <div class="modal fade" id="modalRecibirAparato" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Recibir un aparato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    @if ($this->unidadElegida === null)
                        <div class="mb-3">
                            <label for="buscar-unidad" class="form-label">Serial o código del aparato</label>
                            <input type="text" id="buscar-unidad" class="form-control" wire:model.live.debounce.400ms="buscarUnidad"
                                placeholder="Escanea o teclea el serial..">
                            <small class="text-muted">El sistema busca entre los aparatos que ya conoce.</small>
                            @error('unidadId')
                                <div class="text-danger fs-12 mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="list-group list-group-flush">
                            @forelse ($this->coincidencias as $unidad)
                                <button type="button" class="list-group-item list-group-item-action px-0"
                                    wire:key="unidad-{{ $unidad->id }}"
                                    wire:click="elegirUnidad({{ $unidad->id }})">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="unidad-imagen-mini">
                                            @if ($unidad->producto?->imagen)
                                                <img src="{{ asset('storage/'.$unidad->producto->imagen) }}" alt="{{ $unidad->producto->nombre }}">
                                            @else
                                                <img src="{{ asset('assets/images/sin_imagen.png') }}" alt="Aparato" class="unidad-imagen-mini-sin">
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <span class="d-block fw-semibold">{{ $unidad->producto?->nombre }}</span>
                                            <small class="text-muted font-monospace">
                                                {{ $unidad->serial ?: $unidad->codigo_interno }}
                                            </small>
                                        </div>
                                    </div>
                                </button>
                            @empty
                                @if (mb_strlen(trim($buscarUnidad)) >= 2)
                                    <p class="text-muted text-center py-3 mb-0">
                                        Ningún aparato con ese serial o código.
                                    </p>
                                @endif
                            @endforelse
                        </div>
                    @else
                        <div class="alert alert-light border d-flex align-items-start justify-content-between gap-3">
                            <div class="d-flex align-items-center gap-2 min-w-0">
                                <div class="unidad-imagen-mini">
                                    @if ($this->unidadElegida->producto?->imagen)
                                        <img src="{{ asset('storage/'.$this->unidadElegida->producto->imagen) }}" alt="{{ $this->unidadElegida->producto->nombre }}">
                                    @else
                                        <img src="{{ asset('assets/images/sin_imagen.png') }}" alt="Aparato" class="unidad-imagen-mini-sin">
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <span class="fw-semibold d-block">{{ $this->unidadElegida->producto?->nombre }}</span>
                                    <small class="text-muted font-monospace d-block">
                                        {{ $this->unidadElegida->serial ?: $this->unidadElegida->codigo_interno }}
                                    </small>
                                    @if ($this->unidadElegida->en_garantia)
                                        <span class="badge bg-info-subtle text-info mt-1">
                                            <i class="ri-shield-check-line align-bottom"></i>
                                            En garantía hasta {{ $this->unidadElegida->garantia_hasta->format('d/m/Y') }}
                                        </span>
                                    @elseif ($this->unidadElegida->garantia_hasta)
                                        <span class="badge bg-warning-subtle text-warning mt-1">
                                            Garantía vencida el {{ $this->unidadElegida->garantia_hasta->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="badge bg-light text-body mt-1">Este producto no lleva garantía</span>
                                    @endif
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-light" wire:click="quitarUnidad">
                                Cambiar
                            </button>
                        </div>

                        @if ($this->ordenAbierta)
                            <div class="alert alert-warning alert-borderless fs-13">
                                <i class="ri-alert-line align-bottom me-1"></i>
                                Ese aparato ya está en el taller con la orden
                                <strong>{{ $this->ordenAbierta->codigo }}</strong>. Recibirlo otra vez partiría su
                                historial en dos.
                            </div>
                        @endif

                        <div class="mb-3">
                            <label for="falla" class="form-label">¿Qué le pasa, según el cliente?</label>
                            <textarea id="falla" rows="2" class="form-control @error('fallaReportada') is-invalid @enderror"
                                wire:model="fallaReportada" placeholder="No enciende, hace un ruido al centrifugar.."></textarea>
                            @error('fallaReportada')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="prometida" class="form-label">
                                    ¿Para cuándo? <span class="text-muted">(opcional)</span>
                                </label>
                                <input type="date" id="prometida"
                                    class="form-control @error('prometidaPara') is-invalid @enderror"
                                    wire:model="prometidaPara">
                                @error('prometidaPara')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="costo-estimado" class="form-label">
                                    Costo estimado
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">Bs</span>
                                    <input type="number" step="0.01" min="0" id="costo-estimado"
                                        class="form-control" wire:model="costoEstimado"
                                        @disabled($this->unidadElegida->en_garantia)>
                                </div>
                                @if ($this->unidadElegida->en_garantia)
                                    <small class="text-muted">En garantía no se cobra.</small>
                                @endif
                            </div>

                            <div class="col-md-4">
                                <label for="tecnico" class="form-label">
                                    Técnico <span class="text-muted">(opcional)</span>
                                </label>
                                <select id="tecnico" class="form-select" wire:model="tecnicoId">
                                    <option value="">Sin asignar</option>
                                    @foreach ($this->tecnicos as $tecnico)
                                        <option value="{{ $tecnico->id }}">{{ $tecnico->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label for="notas-orden" class="form-label">
                                Notas <span class="text-muted">(opcional)</span>
                            </label>
                            <textarea id="notas-orden" rows="2" class="form-control" wire:model="notas"
                                placeholder="Vino sin el cable, tiene un golpe en la puerta.."></textarea>
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="recibir" wire:loading.attr="disabled"
                        wire:target="recibir" @disabled($this->unidadElegida === null)>
                        Abrir la orden
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Diagnóstico ===================== --}}
    <div class="modal fade" id="modalDiagnostico" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Diagnóstico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="diagnostico" class="form-label">¿Qué encontraste?</label>
                        <textarea id="diagnostico" rows="3" class="form-control @error('diagnostico') is-invalid @enderror"
                            wire:model="diagnostico" placeholder="Bomba de desagüe trabada, tarjeta quemada.."></textarea>
                        @error('diagnostico')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="costo-real" class="form-label">Costo</label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input type="number" step="0.01" min="0" id="costo-real" class="form-control"
                                    wire:model="costo">
                            </div>
                            <small class="text-muted">Si entró en garantía se queda en cero.</small>
                        </div>
                        <div class="col-md-6">
                            <label for="tecnico-diag" class="form-label">Técnico</label>
                            <select id="tecnico-diag" class="form-select" wire:model="tecnicoId">
                                <option value="">Sin asignar</option>
                                @foreach ($this->tecnicos as $tecnico)
                                    <option value="{{ $tecnico->id }}">{{ $tecnico->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="diagnosticar"
                        wire:loading.attr="disabled" wire:target="diagnosticar">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Esperando repuesto ===================== --}}
    <div class="modal fade" id="modalEsperarRepuesto" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Esperando repuesto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="repuesto" class="form-label">¿Qué hace falta?</label>
                    <textarea id="repuesto" rows="2" class="form-control @error('motivo') is-invalid @enderror"
                        wire:model="motivo" placeholder="Tarjeta electrónica, pedida al proveedor.."></textarea>
                    @error('motivo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block mt-2">
                        Queda anotado con la fecha. El aparato sigue contando días en el taller.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" wire:click="esperarRepuesto"
                        wire:loading.attr="disabled" wire:target="esperarRepuesto">Anotar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Ya está lista ===================== --}}
    <div class="modal fade" id="modalListo" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Lista para entregar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="trabajo" class="form-label">¿Qué se le hizo?</label>
                    <textarea id="trabajo" rows="3" class="form-control @error('trabajoRealizado') is-invalid @enderror"
                        wire:model="trabajoRealizado" placeholder="Se cambió la bomba y se limpió el filtro.."></textarea>
                    @error('trabajoRealizado')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block mt-2">
                        Es lo que se le explica al cliente cuando venga a recogerlo.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" wire:click="marcarLista"
                        wire:loading.attr="disabled" wire:target="marcarLista">Está lista</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Sin arreglo ===================== --}}
    <div class="modal fade" id="modalIrreparable" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">No tiene arreglo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="motivo-irreparable" class="form-label">¿Por qué?</label>
                    <textarea id="motivo-irreparable" rows="3" class="form-control @error('motivo') is-invalid @enderror"
                        wire:model="motivo" placeholder="Tambor partido; el repuesto cuesta más que el aparato.."></textarea>
                    @error('motivo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block mt-2">
                        El costo se pone en cero y el aparato queda esperando a que el cliente lo recoja.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-dark" wire:click="declararIrreparable"
                        wire:loading.attr="disabled" wire:target="declararIrreparable">Anotar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Entregar al cliente ===================== --}}
    <div class="modal fade" id="modalEntregarReparacion" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Entregar al cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="entregada-a" class="form-label">¿Quién se lo lleva?</label>
                    <input type="text" id="entregada-a"
                        class="form-control @error('entregadaA') is-invalid @enderror" wire:model="entregadaA"
                        placeholder="Nombre de quien retira">
                    @error('entregadaA')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block mt-2">
                        El aparato vuelve al estado en el que entró al taller.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" wire:click="entregar" wire:loading.attr="disabled"
                        wire:target="entregar">Entregado</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Cancelar la orden ===================== --}}
    <div class="modal fade" id="modalCancelarReparacion" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Cancelar la orden</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="motivo-cancelar" class="form-label">¿Por qué?</label>
                    <textarea id="motivo-cancelar" rows="2" class="form-control @error('motivo') is-invalid @enderror"
                        wire:model="motivo" placeholder="Se recibió por error, el cliente se lo lleva sin tocarlo.."></textarea>
                    @error('motivo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block mt-2">
                        El aparato vuelve al estado en el que entró y la orden queda en el histórico.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Volver</button>
                    <button type="button" class="btn btn-danger" wire:click="cancelar" wire:loading.attr="disabled"
                        wire:target="cancelar">Cancelar la orden</button>
                </div>
            </div>
        </div>
    </div>
</div>
