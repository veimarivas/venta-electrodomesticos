<div class="caja-modulo">

    {{-- ===================== Estado del turno ===================== --}}
    @if ($abierta)
        <div class="card caja-turno mb-4">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                    <div class="min-w-0">
                        <span class="caja-turno-estado">
                            <span class="caja-latido"></span> Caja abierta
                        </span>
                        <h4 class="caja-turno-titulo mt-2 mb-1">
                            Bs {{ number_format((float) $abierta->monto_inicial, 2, ',', '.') }}
                            <small class="text-muted fs-14 fw-normal">de fondo</small>
                        </h4>
                        <p class="text-muted mb-0 fs-13">
                            Abierta por {{ $abierta->abiertaPor?->name ?? '—' }} ·
                            {{ $abierta->abierta_en->translatedFormat('d \d\e F, H:i') }}
                        </p>
                    </div>

                    @if ($puedeGestionar)
                        <button type="button" class="btn btn-primary" wire:click="confirmarCierre">
                            <i class="ri-safe-2-line align-bottom me-1"></i> Cerrar y cuadrar
                        </button>
                    @endif
                </div>

                <div class="row g-3 mt-1">
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
                    {{-- No se suman solas: un arqueo que se inventa de dónde
                         salió el dinero deja de detectar faltantes. --}}
                    <div class="alert alert-warning alert-borderless mt-3 mb-0 fs-13">
                        <i class="ri-alert-line align-bottom me-1"></i>
                        Hay <strong>{{ $sueltas }}</strong>
                        {{ $sueltas === 1 ? 'venta en efectivo' : 'ventas en efectivo' }}
                        de este horario que no quedaron atadas a la caja. No cuentan en el
                        cuadre; revísalas antes de cerrar.
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="card caja-turno caja-turno--cerrada mb-4">
            <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <h5 class="mb-1">No hay ninguna caja abierta</h5>
                    <p class="text-muted mb-0 fs-13">
                        Las ventas se registran igual, pero no entran en ningún cuadre.
                    </p>
                </div>

                @if ($puedeGestionar)
                    <button type="button" class="btn btn-primary"
                        data-bs-toggle="modal" data-bs-target="#modalAbrirCaja">
                        <i class="ri-inbox-unarchive-line align-bottom me-1"></i> Abrir caja
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- ===================== Histórico de cierres ===================== --}}
    @if ($puedeVer)
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <h5 class="card-title mb-0">Cierres anteriores</h5>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
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
                                        {{-- El estado se ve por la forma, no solo por el
                                             número: un faltante tiene que saltar a la vista
                                             al recorrer la columna. --}}
                                        @if ($caja->cuadra)
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="ri-check-line align-bottom"></i> Cuadra
                                            </span>
                                        @elseif ($caja->falta)
                                            <span class="badge bg-danger-subtle text-danger caja-num">
                                                Faltan {{ number_format(abs((float) $caja->diferencia), 2, ',', '.') }}
                                            </span>
                                        @else
                                            <span class="badge bg-warning-subtle text-warning caja-num">
                                                Sobran {{ number_format((float) $caja->diferencia, 2, ',', '.') }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="ri-safe-2-line fs-1 d-block mb-2 opacity-50"></i>
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

                    {{-- El campo va en blanco y NO se propone lo esperado: si el
                         sistema lo rellenara, cerrar sería darle a aceptar y el
                         arqueo dejaría de comparar dos números para comparar
                         uno consigo mismo. --}}
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
