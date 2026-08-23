<div class="items-modulo qrs-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-qr-code-line me-1"></i> Ventas · QR de cobro
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-qr-scan-2-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">QR de cobro</h4>
                                <p class="text-white-50 mb-0">
                                    El punto de venta muestra al cliente los QR vigentes. Los caducados salen solos.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('qrs_cobro.crear')
                                <button type="button" class="btn btn-light crud-nueva-hero" wire:click="abrirCrear">
                                    <i class="ri-add-line align-bottom me-1"></i> Nuevo QR
                                </button>
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
            <x-stat-card label="Registrados" value="{{ $totalQrs }}" icon="bx-qr"
                color="primary" caption="Total en el sistema" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Vigentes" value="{{ $vigentes }}" icon="bx-check-circle"
                color="success" caption="Disponibles en el POS" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Por vencer" value="{{ $porVencer }}" icon="bx-time-five"
                color="warning" caption="Caducan en 7 días o menos" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Cobros por QR" value="{{ $ventasConQr }}" icon="bx-receipt"
                color="info" caption="Ventas con respaldo bancario" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm crud-listado">
        <div class="card-header bg-transparent py-3 crud-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        QR registrados
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $qrs->total() }} {{ $qrs->total() === 1 ? 'registro' : 'registros' }}
                    </small>
                </div>

                <div class="col-md-5">
                    <div class="search-box">
                        <input type="text" class="form-control crud-busqueda"
                            placeholder="Buscar por nombre, banco o titular..."
                            wire:model.live.debounce.400ms="buscar">
                        <i class="ri-search-line search-icon"></i>
                    </div>
                </div>

                <div class="col-md-3">
                    <select class="form-select" wire:model.live="filtroEstado" aria-label="Filtrar por vigencia">
                        <option value="vigentes">Vigentes</option>
                        <option value="caducados">Caducados o inactivos</option>
                        <option value="todos">Todos</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-crud border-top">
                    <thead class="table-light">
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4">QR</th>
                            <th scope="col">Cuenta</th>
                            <th scope="col">Vigencia</th>
                            <th scope="col" class="text-center">Cobros</th>
                            <th scope="col" class="text-center">Estado</th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($qrs as $qr)
                            <tr wire:key="qr-{{ $qr->id }}" class="{{ $qr->esta_vigente ? '' : 'fila-dado-de-baja' }}">
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="qr-miniatura">
                                            @if ($qr->imagen_url)
                                                <img src="{{ $qr->imagen_url }}" alt="QR {{ $qr->nombre }}">
                                            @else
                                                <div class="qr-miniatura-placeholder">
                                                    <i class="ri-qr-code-line"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <h6 class="mb-0 text-truncate">{{ $qr->nombre }}</h6>
                                            @if ($qr->notas)
                                                <span class="text-muted fs-12 text-truncate d-block">{{ $qr->notas }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    @if ($qr->banco)
                                        <span class="qr-banco-chip">{{ $qr->banco }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                    <br>
                                    <small class="text-muted">{{ $qr->titular ?? 'Sin titular' }}</small>
                                </td>

                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($qr->esta_vigente)
                                            <span class="qr-vigencia-dot qr-vigencia-dot-ok"></span>
                                        @else
                                            <span class="qr-vigencia-dot qr-vigencia-dot-expired"></span>
                                        @endif
                                        <div>
                                            <span class="d-block fs-13 fw-medium">{{ $qr->fecha_limite->format('d/m/Y') }}</span>
                                            @if ($qr->dias_restantes < 0)
                                                <small class="text-danger">Caducó hace {{ abs($qr->dias_restantes) }} día(s)</small>
                                            @elseif ($qr->dias_restantes <= 7)
                                                <small class="text-warning">Quedan {{ $qr->dias_restantes }} día(s)</small>
                                            @else
                                                <small class="text-muted">Quedan {{ $qr->dias_restantes }} días</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td class="text-center">
                                    <span class="qr-cobros-pill {{ $qr->ventas_count > 0 ? 'qr-cobros-pill-activo' : '' }}">
                                        {{ $qr->ventas_count }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    @if ($qr->esta_vigente)
                                        <span class="qr-estado-chip qr-estado-vigente">
                                            <span class="qr-estado-dot"></span>
                                            Vigente
                                        </span>
                                    @elseif (! $qr->activo)
                                        <span class="qr-estado-chip qr-estado-inactivo">
                                            <span class="qr-estado-dot"></span>
                                            Inactivo
                                        </span>
                                    @else
                                        <span class="qr-estado-chip qr-estado-caducado">
                                            <span class="qr-estado-dot"></span>
                                            Caducado
                                        </span>
                                    @endif
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @can('qrs_cobro.editar')
                                            <button type="button" class="btn btn-sm btn-ghost-secondary btn-icon rounded-circle qr-accion-toggle"
                                                wire:click="alternarActivo({{ $qr->id }})"
                                                title="{{ $qr->activo ? 'Desactivar' : 'Activar' }}"
                                                aria-label="{{ $qr->activo ? 'Desactivar' : 'Activar' }} {{ $qr->nombre }}">
                                                <i class="{{ $qr->activo ? 'ri-toggle-fill' : 'ri-toggle-line' }} fs-16"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-ghost-primary btn-icon rounded-circle qr-accion-editar"
                                                wire:click="abrirEditar({{ $qr->id }})" title="Editar"
                                                aria-label="Editar {{ $qr->nombre }}">
                                                <i class="ri-pencil-line fs-16"></i>
                                            </button>
                                        @endcan
                                        @can('qrs_cobro.eliminar')
                                            <button type="button" class="btn btn-sm btn-ghost-danger btn-icon rounded-circle qr-accion-eliminar"
                                                wire:click="confirmarEliminar({{ $qr->id }})" title="Archivar"
                                                aria-label="Archivar {{ $qr->nombre }}">
                                                <i class="ri-archive-line fs-16"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="text-center py-5">
                                        <div class="avatar-lg mx-auto mb-4">
                                            <div class="avatar-title bg-light text-primary rounded-circle fs-1 shadow-sm">
                                                <i class="ri-qr-code-line"></i>
                                            </div>
                                        </div>
                                        <h5 class="mb-1 fw-semibold text-primary">No hay QR que mostrar</h5>
                                        <p class="text-muted mb-3">
                                            Sin un QR vigente, el punto de venta solo puede cobrar en efectivo.
                                        </p>
                                        @can('qrs_cobro.crear')
                                            <button type="button" class="btn btn-success btn-sm rounded-pill shadow-sm"
                                                wire:click="abrirCrear">
                                                <i class="ri-add-line align-bottom me-1"></i> Registrar QR
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($qrs->hasPages())
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando {{ $qrs->firstItem() }}-{{ $qrs->lastItem() }} de {{ $qrs->total() }}
                    </p>
                    <div class="crud-paginacion">{{ $qrs->onEachSide(1)->links() }}</div>
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal registro / edición ===================== --}}
    <div class="modal fade" id="modalQr" tabindex="-1" aria-hidden="true" wire:ignore.self data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-crud-dialog">
            <div class="modal-content border-0 modal-crud-content {{ $qrId ? 'modal-editar-crud' : '' }}">
                <div class="modal-header modal-crud-header p-4">
                    <div class="modal-crud-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-crud-icon rounded-circle fs-4">
                                <i class="{{ $qrId ? 'ri-pencil-line' : 'ri-qr-code-line' }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">{{ $qrId ? 'Editar QR de cobro' : 'Nuevo QR de cobro' }}</h5>
                            <small class="text-muted">
                                La imagen y su fecha límite son lo que el mostrador enseña al cliente.
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form wire:submit="guardar" autocomplete="off">
                    <div class="modal-body modal-crud-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="qr-nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" id="qr-nombre" maxlength="100"
                                    class="form-control @error('nombre') is-invalid @elseif ($nombre !== '') is-valid @enderror"
                                    wire:model.live.debounce.400ms="nombre" placeholder="Ej. QR tienda central">
                                @error('nombre') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="qr-fecha" class="form-label">
                                    Fecha límite <span class="text-danger">*</span>
                                </label>
                                <input type="date" id="qr-fecha"
                                    class="form-control @error('fechaLimite') is-invalid @enderror"
                                    wire:model.live="fechaLimite">
                                @error('fechaLimite') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Desde el día siguiente deja de ofrecerse en el punto de venta.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="qr-banco" class="form-label">
                                    Banco <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <input type="text" id="qr-banco" maxlength="100"
                                    class="form-control @error('banco') is-invalid @enderror"
                                    wire:model.live.debounce.400ms="banco" placeholder="Ej. Banco Unión">
                                @error('banco') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="qr-titular" class="form-label">
                                    Titular <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <input type="text" id="qr-titular" maxlength="150"
                                    class="form-control @error('titular') is-invalid @enderror"
                                    wire:model.live.debounce.400ms="titular">
                                @error('titular') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12">
                                <label class="form-label">
                                    Imagen del QR @if (! $qrId) <span class="text-danger">*</span> @endif
                                </label>
                                <div class="crud-upload">
                                    <div class="crud-upload-preview">
                                        @if ($imagen)
                                            <img src="{{ $imagen->temporaryUrl() }}" alt="Nuevo QR">
                                        @elseif ($imagenActual !== '')
                                            <img src="{{ asset('storage/'.$imagenActual) }}" alt="QR actual">
                                        @else
                                            <span class="avatar-title"><i class="ri-qr-code-line"></i></span>
                                        @endif
                                    </div>

                                    <div class="flex-grow-1 min-w-0">
                                        <input type="file" wire:model="imagen" accept="image/jpeg,image/png,image/webp"
                                            class="form-control @error('imagen') is-invalid @enderror">
                                        @error('imagen') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                        <div wire:loading wire:target="imagen" class="form-text text-primary">
                                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                            Subiendo imagen...
                                        </div>
                                        <div class="form-text">JPG, PNG o WebP de hasta 3 MB.</div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="qr-notas" class="form-label">
                                    Notas <span class="text-muted fw-normal fs-12">(opcional)</span>
                                </label>
                                <textarea id="qr-notas" rows="2" maxlength="500"
                                    class="form-control @error('notas') is-invalid @enderror"
                                    wire:model.live.debounce.400ms="notas"
                                    placeholder="Ej. Solo para cobros mayores a Bs 500."></textarea>
                                @error('notas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 d-flex justify-content-end">
                                <div class="form-check form-switch form-switch-lg">
                                    <input type="checkbox" id="qr-activo" class="form-check-input" wire:model.live="isActive">
                                    <label for="qr-activo" class="form-check-label">Ofrecer en el punto de venta</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer modal-crud-footer p-4">
                        <div class="d-flex align-items-center justify-content-between w-100 gap-3 flex-wrap">
                            <span class="modal-pista-guardar {{ $this->formularioValido ? 'modal-pista-ok' : '' }}">
                                @if ($this->formularioValido)
                                    <i class="ri-checkbox-circle-fill"></i> Listo para guardar
                                @else
                                    <i class="ri-information-line"></i>
                                    Completa los campos con <span class="text-danger">*</span>
                                @endif
                            </span>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-light modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-success modal-guardar" @disabled(! $this->formularioValido)
                                    wire:loading.attr="disabled" wire:target="guardar">
                                    <span wire:loading.remove wire:target="guardar">
                                        <i class="ri-save-line align-bottom me-1"></i>
                                        {{ $qrId ? 'Guardar cambios' : 'Registrar QR' }}
                                    </span>
                                    <span wire:loading wire:target="guardar">
                                        <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                        Guardando...
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- ===================== Modal archivar ===================== --}}
    <div class="modal fade zoomIn" id="modalEliminarQr" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 shadow-lg modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1"><i class="ri-archive-line"></i></span>
                    </div>

                    <h5 class="mb-2">¿Archivar este QR?</h5>
                    <p class="text-muted mb-4">
                        <strong class="modal-eliminar-nombre">{{ $eliminarNombre }}</strong> dejará de ofrecerse en el
                        punto de venta. Las ventas cobradas con él conservan su respaldo.
                    </p>

                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger modal-eliminar-btn w-100" wire:click="eliminar"
                            wire:loading.attr="disabled" wire:target="eliminar">
                            <span wire:loading.remove wire:target="eliminar">Sí, archivar</span>
                            <span wire:loading wire:target="eliminar">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Archivando...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
