@php
    $bs = fn (float $monto): string => 'Bs '.number_format($monto, 2, ',', '.');
    $esHoy = $fecha === now()->toDateString();
@endphp

<div class="gastos-modulo">

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-wallet-3-line me-1"></i> Operaciones · Gastos
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-hand-coin-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Gastos</h4>
                                <p class="text-white-50 mb-0">
                                    Comida, fletes, servicios: lo que sale que no es mercadería. Entra en el resumen del día.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5 d-flex justify-content-lg-end">
                        @can('gastos.crear')
                            <button type="button" class="btn btn-light crud-nueva-hero" wire:click="nuevo">
                                <i class="ri-add-line align-bottom me-1"></i> Registrar gasto
                            </button>
                        @endcan
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Día ===================== --}}
    <div class="gastos-dia mb-4">
        <button type="button" class="btn btn-light btn-icon" wire:click="diaAnterior" title="Día anterior" aria-label="Día anterior">
            <i class="ri-arrow-left-s-line"></i>
        </button>
        <input type="date" class="form-control gastos-dia-fecha" wire:model.live="fecha" max="{{ now()->toDateString() }}"
            aria-label="Día de los gastos">
        <button type="button" class="btn btn-light btn-icon" wire:click="diaSiguiente" @disabled($esHoy)
            title="Día siguiente" aria-label="Día siguiente">
            <i class="ri-arrow-right-s-line"></i>
        </button>
        <span class="gastos-dia-texto">
            {{ $esHoy ? 'Hoy, ' : '' }}{{ \Illuminate\Support\Carbon::parse($fecha)->translatedFormat('l j \d\e F') }}
        </span>
    </div>

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Gastado" value="{{ $bs($total) }}" icon="bx-wallet" color="danger"
                caption="{{ $this->gastos->count() }} {{ $this->gastos->count() === 1 ? 'gasto' : 'gastos' }}" />
        </div>
        @foreach (\App\Models\Gasto::METODOS as $clave => $etiqueta)
            <div class="col-xl-3 col-sm-6">
                <x-stat-card label="Por {{ $etiqueta }}" value="{{ $bs($porMetodo[$clave] ?? 0) }}"
                    icon="{{ $clave === 'efectivo' ? 'bx-money' : ($clave === 'qr' ? 'bx-qr' : 'bx-transfer') }}"
                    color="{{ $clave === 'qr' ? 'primary' : ($clave === 'efectivo' ? 'success' : 'info') }}"
                    caption="{{ $clave === 'efectivo' ? 'En billetes' : 'Por el banco' }}" />
            </div>
        @endforeach
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3 d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <h5 class="card-title mb-0">Gastos del día</h5>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($porCategoria as $categoria => $monto)
                    <span class="gastos-categoria">
                        {{ \App\Models\Gasto::CATEGORIAS[$categoria] ?? $categoria }} · {{ $bs($monto) }}
                    </span>
                @endforeach
            </div>
        </div>
        <div class="card-body p-0">
            @if ($this->gastos->isEmpty())
                <div class="text-center py-5 px-3">
                    <div class="crud-empty-icon mx-auto mb-3">
                        <span class="avatar-title fs-2"><i class="ri-wallet-3-line"></i></span>
                    </div>
                    <h6 class="mb-1">Sin gastos este día</h6>
                    <p class="text-muted mb-0 fs-13">Registra la comida, un flete o un servicio pagado para que el resumen del día cuadre.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-uppercase fs-11 text-muted">
                                <th class="ps-4">Concepto</th>
                                <th>Para quién</th>
                                <th>Pago</th>
                                <th class="text-end">Monto</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->gastos as $gasto)
                                <tr wire:key="gasto-{{ $gasto->id }}">
                                    <td class="ps-4">
                                        <div class="fw-semibold">{{ $gasto->concepto }}</div>
                                        <small class="text-muted">
                                            {{ \App\Models\Gasto::CATEGORIAS[$gasto->categoria] ?? $gasto->categoria }}
                                            · anotó {{ $gasto->user?->name ?? '—' }}
                                            @if ($gasto->notas) · {{ \Illuminate\Support\Str::limit($gasto->notas, 60) }} @endif
                                        </small>
                                    </td>
                                    <td>{{ $gasto->beneficiario?->name ?? 'La tienda' }}</td>
                                    <td>
                                        <span class="gastos-metodo gastos-metodo--{{ $gasto->metodo_pago }}">
                                            {{ \App\Models\Gasto::METODOS[$gasto->metodo_pago] ?? $gasto->metodo_pago }}
                                        </span>
                                        @if ($gasto->caja_id)
                                            <small class="text-muted d-block">del cajón</small>
                                        @endif
                                        @if ($gasto->comprobante)
                                            <a href="{{ $gasto->comprobante_url }}" target="_blank" class="fs-12 d-block">
                                                <i class="ri-image-2-line"></i> Comprobante
                                            </a>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold">{{ $bs((float) $gasto->monto) }}</td>
                                    <td class="text-end pe-4 text-nowrap">
                                        @can('gastos.editar')
                                            <button type="button" class="btn btn-sm btn-ghost-primary btn-icon"
                                                wire:click="editar({{ $gasto->id }})" title="Corregir" aria-label="Corregir {{ $gasto->concepto }}">
                                                <i class="ri-pencil-line"></i>
                                            </button>
                                        @endcan
                                        @can('gastos.eliminar')
                                            <button type="button" class="btn btn-sm btn-ghost-danger btn-icon"
                                                wire:click="confirmarArchivar({{ $gasto->id }})" title="Archivar" aria-label="Archivar {{ $gasto->concepto }}">
                                                <i class="ri-archive-line"></i>
                                            </button>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== Modal registro / corrección ===================== --}}
    <div class="modal fade" id="modalGasto" tabindex="-1" aria-hidden="true" wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-crud-dialog">
            <div class="modal-content border-0 modal-crud-content">
                <div class="modal-header modal-crud-header p-4">
                    <div class="modal-crud-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-crud-icon rounded-circle fs-4">
                                <i class="{{ $gastoId ? 'ri-pencil-line' : 'ri-hand-coin-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">{{ $gastoId ? 'Corregir gasto' : 'Registrar gasto' }}</h5>
                            <small class="text-muted">En qué, para quién y cómo se pagó.</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-crud-body p-4">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="g-concepto" class="form-label">Concepto <span class="text-danger">*</span></label>
                                <input type="text" id="g-concepto" maxlength="160" wire:model="concepto"
                                    class="form-control @error('concepto') is-invalid @enderror"
                                    placeholder="Ej. Almuerzo del turno, flete de la licuadora">
                                @error('concepto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="g-fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                                <input type="date" id="g-fecha" wire:model.live="fechaGasto" max="{{ now()->toDateString() }}"
                                    class="form-control @error('fechaGasto') is-invalid @enderror">
                                @error('fechaGasto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="g-categoria" class="form-label">Categoría</label>
                                <select id="g-categoria" class="form-select" wire:model="categoria">
                                    @foreach (\App\Models\Gasto::CATEGORIAS as $clave => $etiqueta)
                                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="g-persona" class="form-label">
                                    Para quién <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <select id="g-persona" class="form-select" wire:model="beneficiarioId">
                                    <option value="">La tienda</option>
                                    @foreach ($this->personas as $persona)
                                        <option value="{{ $persona->id }}">{{ $persona->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="g-monto" class="form-label">Monto (Bs) <span class="text-danger">*</span></label>
                                <div class="input-group has-validation">
                                    <span class="input-group-text bg-light border-end-0">Bs</span>
                                    <input type="number" id="g-monto" step="0.01" min="0.01" wire:model="monto"
                                        class="form-control border-start-0 ps-0 @error('monto') is-invalid @enderror" placeholder="0.00">
                                    @error('monto') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <span class="form-label d-block">Cómo se pagó</span>
                                <div class="btn-group w-100" role="group" aria-label="Método de pago">
                                    @foreach (\App\Models\Gasto::METODOS as $clave => $etiqueta)
                                        <input type="radio" class="btn-check" id="g-metodo-{{ $clave }}" value="{{ $clave }}"
                                            wire:model.live="metodoPago">
                                        <label class="btn btn-outline-primary" for="g-metodo-{{ $clave }}">{{ $etiqueta }}</label>
                                    @endforeach
                                </div>
                            </div>

                            @if ($metodoPago === 'efectivo' && $fechaGasto === now()->toDateString())
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" id="g-caja" class="form-check-input" wire:model="deCaja"
                                            @disabled(! $this->hayCajaAbierta && ! $gastoId)>
                                        <label for="g-caja" class="form-check-label">
                                            Sale del cajón del turno abierto
                                        </label>
                                    </div>
                                    <small class="text-muted">
                                        @if ($this->hayCajaAbierta)
                                            Se resta del efectivo esperado en el cierre de caja.
                                        @else
                                            No hay caja abierta: el gasto se anota solo para el resumen del día.
                                        @endif
                                    </small>
                                </div>
                            @endif

                            <div class="col-md-6">
                                <label class="form-label" for="g-comprobante">
                                    Comprobante <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <input type="file" id="g-comprobante" accept="image/*" wire:model="comprobante"
                                    class="form-control @error('comprobante') is-invalid @enderror">
                                @error('comprobante') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">La captura del QR o la factura.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="g-notas" class="form-label">
                                    Notas <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <textarea id="g-notas" rows="2" maxlength="500" wire:model="notas" class="form-control"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer modal-crud-footer p-4">
                        <div class="d-flex gap-2 ms-auto">
                            <button type="button" class="btn btn-light modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success modal-guardar" wire:loading.attr="disabled" wire:target="guardar">
                                <span wire:loading.remove wire:target="guardar">
                                    <i class="ri-save-line align-bottom me-1"></i> {{ $gastoId ? 'Guardar cambios' : 'Registrar gasto' }}
                                </span>
                                <span wire:loading wire:target="guardar">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span> Guardando...
                                </span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===================== Modal archivar ===================== --}}
    <div class="modal fade zoomIn" id="modalArchivarGasto" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 shadow-lg modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1"><i class="ri-archive-line"></i></span>
                    </div>
                    <h5 class="mb-2">¿Archivar este gasto?</h5>
                    <p class="text-muted mb-4">Deja de contar en el resumen del día. Si ya entró en un cierre de caja, ese cierre no cambia.</p>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger modal-eliminar-btn w-100" wire:click="archivar">Sí, archivar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
