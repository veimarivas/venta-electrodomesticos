<div class="items-modulo kardex-modulo">

    @php
        // Paleta de tipos de movimiento, compartida por el listado y la ficha.
        $tonoTipo = [
            'entrada' => 'success',
            'salida' => 'primary',
            'ajuste' => 'warning',
            'devolucion' => 'info',
            'dano' => 'danger',
            'traspaso' => 'secondary',
        ];
        $iconoTipo = [
            'entrada' => 'ri-login-circle-line',
            'salida' => 'ri-logout-circle-line',
            'ajuste' => 'ri-equalizer-line',
            'devolucion' => 'ri-arrow-go-back-line',
            'dano' => 'ri-error-warning-line',
            'traspaso' => 'ri-swap-box-line',
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
                            <i class="ri-history-line me-1"></i> Inventario · Kardex
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-history-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Kardex</h4>
                                <p class="text-white-50 mb-0">
                                    Qué le pasó a cada aparato, cuándo y por qué. Busca por su serial.
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
            <x-stat-card label="Movimientos" value="{{ $totalMovimientos }}" icon="bx-transfer"
                color="primary" caption="Historial completo" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Hoy" value="{{ $movimientosHoy }}" icon="bx-calendar-event"
                color="info" caption="Movimientos de la jornada" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Entradas del mes" value="{{ $entradasDelMes }}" icon="bx-log-in-circle"
                color="success" caption="{{ ucfirst(now()->translatedFormat('F Y')) }}" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Ajustes del mes" value="{{ $ajustesDelMes }}" icon="bx-slider-alt"
                color="warning" caption="Ajustes, daños y devoluciones" />
        </div>
    </div>

    {{-- ===================== Buscador ===================== --}}
    <div class="card border-0 shadow-sm mb-4 kardex-buscador">
        <div class="card-body p-4">
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="kardex-buscador-icono">
                    <i class="ri-search-line"></i>
                </span>
                <div>
                    <h6 class="mb-0 kardex-buscador-titulo">Buscar aparato</h6>
                    <small class="text-muted">Serial, código interno, SKU o nombre del producto</small>
                </div>
            </div>
            <div class="search-box">
                <input type="text" id="k-buscar" class="form-control form-control-lg crud-busqueda"
                    placeholder="Escanea o escribe el serial, el código interno, el SKU o el nombre..."
                    wire:model.live.debounce.350ms="buscar" @if (! $unidadId) autofocus @endif>
                <i class="ri-search-line search-icon"></i>
                @if ($buscar !== '')
                    <button type="button"
                        class="btn btn-sm btn-link text-muted position-absolute end-0 top-50 translate-middle-y me-2 p-1"
                        wire:click="$set('buscar', '')" title="Limpiar búsqueda">
                        <i class="ri-close-circle-fill fs-18"></i>
                    </button>
                @endif
            </div>

            {{-- Coincidencias mientras no haya una unidad abierta --}}
            @if (! $unidadId && $this->coincidencias->isNotEmpty())
                <div class="kardex-coincidencias mt-3">
                    @foreach ($this->coincidencias as $coincidencia)
                        <button type="button" class="kardex-coincidencia-item"
                            wire:key="coincidencia-{{ $coincidencia->id }}"
                            wire:click="abrirUnidad({{ $coincidencia->id }})">
                            <div class="kardex-coincidencia-icono">
                                <i class="ri-box-3-line"></i>
                            </div>
                            <span class="min-w-0 flex-grow-1 text-start">
                                <span class="d-block fw-semibold text-truncate kardex-coincidencia-nombre">
                                    {{ $coincidencia->producto?->nombre ?? 'Producto' }}
                                </span>
                                <span class="d-block text-muted fs-12 text-truncate">
                                    <code>{{ $coincidencia->codigo_interno }}</code>
                                    @if ($coincidencia->serial) · Serial {{ $coincidencia->serial }} @endif
                                </span>
                            </span>
                            <span class="kardex-coincidencia-estado">
                                <span class="unidad-estado-dot"></span>
                                {{ $estados[$coincidencia->estado] ?? $coincidencia->estado }}
                            </span>
                            <span class="kardex-coincidencia-flecha"><i class="ri-arrow-right-s-line"></i></span>
                        </button>
                    @endforeach
                </div>
            @elseif (! $unidadId && mb_strlen(trim($buscar)) >= 2)
                <div class="kardex-sin-resultados">
                    <i class="ri-search-eye-line"></i>
                    <span>Ningún aparato coincide con «{{ $buscar }}»</span>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== Ficha de la unidad ===================== --}}
    @if ($this->unidad)
        @php $unidad = $this->unidad; @endphp

        <div class="card border-0 shadow-sm mb-4 kardex-ficha">
            <div class="kardex-ficha-header">
                <div class="kardex-ficha-header-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <div class="d-flex align-items-start gap-3">
                            <div class="kardex-ficha-icono flex-shrink-0">
                                <i class="ri-box-3-line"></i>
                            </div>
                            <div class="min-w-0">
                                @if ($unidad->producto?->categoria)
                                    <div class="kardex-ficha-ruta">
                                        <i class="ri-folder-3-line"></i>
                                        {{ str_replace(' / ', ' › ', $unidad->producto->categoria->ruta) }}
                                    </div>
                                @endif
                                <h4 class="kardex-ficha-nombre mb-2">{{ $unidad->producto?->nombre ?? 'Producto' }}</h4>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="kardex-ficha-sku">{{ $unidad->codigo_interno }}</span>
                                    @if ($unidad->serial)
                                        <span class="kardex-ficha-serial">
                                            <i class="ri-fingerprint-line"></i> {{ $unidad->serial }}
                                        </span>
                                    @endif
                                    @if ($unidad->producto?->marca)
                                        <span class="kardex-ficha-marca">
                                            <i class="ri-trademark-line"></i> {{ $unidad->producto->marca->nombre }}
                                        </span>
                                    @endif
                                    <span class="unidad-estado {{ $unidad->estado === 'en_stock' ? 'unidad-estado-stock' : 'unidad-estado-vendido' }}">
                                        <span class="unidad-estado-dot"></span>
                                        {{ $estados[$unidad->estado] ?? $unidad->estado }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="kardex-ficha-stats">
                            <div class="kardex-ficha-stat">
                                <div class="kardex-ficha-stat-icono kardex-stat-precio">
                                    <i class="ri-money-dollar-circle-line"></i>
                                </div>
                                <div>
                                    <span class="kardex-ficha-stat-label">Precio</span>
                                    <span class="kardex-ficha-stat-valor">Bs {{ number_format((float) $unidad->precio_venta, 2, ',', '.') }}</span>
                                </div>
                            </div>
                            <div class="kardex-ficha-stat">
                                <div class="kardex-ficha-stat-icono kardex-stat-fecha">
                                    <i class="ri-calendar-line"></i>
                                </div>
                                <div>
                                    <span class="kardex-ficha-stat-label">Ingresó</span>
                                    <span class="kardex-ficha-stat-valor">{{ $unidad->ingresado_en?->format('d/m/Y') ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="kardex-ficha-stat">
                                <div class="kardex-ficha-stat-icono kardex-stat-garantia">
                                    <i class="ri-shield-check-line"></i>
                                </div>
                                <div>
                                    <span class="kardex-ficha-stat-label">Garantía</span>
                                    <span class="kardex-ficha-stat-valor">{{ $unidad->garantia_hasta?->format('d/m/Y') ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="kardex-ficha-stat">
                                <div class="kardex-ficha-stat-icono kardex-stat-movimientos">
                                    <i class="ri-history-line"></i>
                                </div>
                                <div>
                                    <span class="kardex-ficha-stat-label">Movimientos</span>
                                    <span class="kardex-ficha-stat-valor">{{ $this->historia->count() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-body">
                {{-- ---------- Ajuste de estado ---------- --}}
                @can('inventario.ajustar')
                    <div class="kardex-ajuste">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="kardex-ajuste-icono">
                                <i class="ri-equalizer-line"></i>
                            </span>
                            <div>
                                <h6 class="kardex-ajuste-titulo mb-0">Ajustar estado</h6>
                                <small class="text-muted">Cambia el estado y queda registrado en el kardex</small>
                            </div>
                        </div>

                        <form wire:submit="ajustar" autocomplete="off">
                            <div class="row g-3 align-items-start">
                                <div class="col-md-3">
                                    <label for="k-estado" class="form-label">
                                        Nuevo estado <span class="text-danger">*</span>
                                    </label>
                                    <select id="k-estado" wire:model.live="nuevoEstado"
                                        class="form-select @error('nuevoEstado') is-invalid @enderror">
                                        @foreach ($estados as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                    @error('nuevoEstado')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-7">
                                    <label for="k-motivo" class="form-label">
                                        Motivo <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" id="k-motivo" wire:model.live.debounce.400ms="motivo"
                                        class="form-control @error('motivo') is-invalid @enderror"
                                        placeholder="Ej. Pantalla rota en el traslado" maxlength="500">
                                    @error('motivo')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">
                                        Queda escrito en el kardex: un ajuste sin explicación no sirve de auditoría.
                                    </div>
                                </div>

                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn kardex-ajuste-btn w-100"
                                        wire:loading.attr="disabled" wire:target="ajustar">
                                        <span wire:loading.remove wire:target="ajustar">
                                            <i class="ri-check-line align-bottom me-1"></i> Registrar
                                        </span>
                                        <span wire:loading wire:target="ajustar">
                                            <span class="spinner-border spinner-border-sm" role="status"></span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                @endcan

                {{-- ---------- Historia de la unidad ---------- --}}
                <div class="kardex-seccion-titulo">
                    <span class="kardex-seccion-icono">
                        <i class="ri-history-line"></i>
                    </span>
                    Historia del aparato
                </div>

                <div class="kardex-linea-tiempo">
                    @forelse ($this->historia as $movimiento)
                        <div class="kardex-evento" wire:key="mov-{{ $movimiento->id }}">
                            <span class="kardex-evento-punto kardex-tipo-{{ $movimiento->tipo }}">
                                <i class="{{ $iconoTipo[$movimiento->tipo] ?? 'ri-circle-line' }}"></i>
                            </span>

                            <div class="kardex-evento-contenido">
                                <div class="kardex-evento-cabecera">
                                    <span class="kardex-evento-tipo kardex-tipo-chip-{{ $movimiento->tipo }}">
                                        <i class="{{ $iconoTipo[$movimiento->tipo] ?? 'ri-circle-line' }}"></i>
                                        {{ $tipos[$movimiento->tipo] ?? $movimiento->tipo }}
                                    </span>
                                    @if ($movimiento->estado_anterior)
                                        <span class="kardex-evento-transicion">
                                            <span class="kardex-transicion-estado">{{ $estados[$movimiento->estado_anterior] ?? $movimiento->estado_anterior }}</span>
                                            <i class="ri-arrow-right-s-line"></i>
                                            <span class="kardex-transicion-estado kardex-transicion-nuevo">{{ $estados[$movimiento->estado_nuevo] ?? $movimiento->estado_nuevo }}</span>
                                        </span>
                                    @else
                                        <span class="kardex-evento-transicion">
                                            Entra como
                                            <span class="kardex-transicion-estado kardex-transicion-nuevo">{{ $estados[$movimiento->estado_nuevo] ?? $movimiento->estado_nuevo }}</span>
                                        </span>
                                    @endif
                                </div>

                                @if ($movimiento->notas)
                                    <div class="kardex-evento-notas">{{ $movimiento->notas }}</div>
                                @endif

                                <div class="kardex-evento-meta">
                                    <span class="kardex-evento-fecha">
                                        <i class="ri-time-line"></i>
                                        {{ $movimiento->created_at->format('d/m/Y H:i') }}
                                    </span>
                                    <span class="kardex-evento-usuario">
                                        <i class="ri-user-3-line"></i>
                                        {{ $movimiento->user?->name ?? 'Sistema' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="kardex-sin-movimientos">
                            <i class="ri-inbox-line"></i>
                            <span>Este aparato todavía no tiene movimientos registrados.</span>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @else
        {{-- ===================== Listado general ===================== --}}
        <div class="card border-0 shadow-sm crud-listado">
            <div class="card-header bg-transparent py-3 crud-toolbar">
                <div class="row g-3 align-items-center">
                    <div class="col-md-8">
                        <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                            Movimientos recientes
                            <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                                <span class="visually-hidden">Cargando...</span>
                            </span>
                        </h5>
                        <small class="text-muted fs-13">
                            {{ $movimientos->total() }}
                            {{ $movimientos->total() === 1 ? 'movimiento' : 'movimientos' }}
                            @if ($buscar !== '')
                                para «{{ $buscar }}»
                            @endif
                        </small>
                    </div>

                    <div class="col-md-4">
                        <select class="form-select" wire:model.live="tipoFiltro">
                            <option value="">Todos los tipos</option>
                            @foreach ($tipos as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 tabla-crud"
                        wire:loading.class="opacity-50" wire:target="buscar, tipoFiltro">
                        <thead>
                            <tr class="text-uppercase fs-11 text-muted">
                                <th scope="col" class="ps-4">Fecha</th>
                                <th scope="col">Aparato</th>
                                <th scope="col">Movimiento</th>
                                <th scope="col">Motivo</th>
                                <th scope="col">Usuario</th>
                                <th scope="col" class="text-end pe-4"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($movimientos as $movimiento)
                                <tr wire:key="movimiento-{{ $movimiento->id }}">
                                    <td class="ps-4">
                                        <div>{{ $movimiento->created_at->format('d/m/Y') }}</div>
                                        <small class="text-muted">{{ $movimiento->created_at->format('H:i') }}</small>
                                    </td>

                                    <td>
                                        <div class="text-truncate">
                                            {{ $movimiento->unidad?->producto?->nombre ?? 'Producto' }}
                                        </div>
                                        <small class="text-muted">
                                            <code class="fs-11">{{ $movimiento->unidad?->codigo_interno }}</code>
                                            @if ($movimiento->unidad?->serial)
                                                · {{ $movimiento->unidad->serial }}
                                            @endif
                                        </small>
                                    </td>

                                    <td>
                                        <span class="badge bg-{{ $tonoTipo[$movimiento->tipo] ?? 'secondary' }}-subtle text-{{ $tonoTipo[$movimiento->tipo] ?? 'secondary' }}">
                                            <i class="{{ $iconoTipo[$movimiento->tipo] ?? 'ri-circle-line' }} align-middle me-1"></i>
                                            {{ $tipos[$movimiento->tipo] ?? $movimiento->tipo }}
                                        </span>
                                        <div class="fs-11 text-muted mt-1">
                                            @if ($movimiento->estado_anterior)
                                                {{ $estados[$movimiento->estado_anterior] ?? $movimiento->estado_anterior }}
                                                <i class="ri-arrow-right-line align-middle"></i>
                                            @endif
                                            {{ $estados[$movimiento->estado_nuevo] ?? $movimiento->estado_nuevo }}
                                        </div>
                                    </td>

                                    <td>
                                        <span class="text-muted text-truncate d-block" style="max-width: 18rem">
                                            {{ $movimiento->notas ?: '—' }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="text-muted">{{ $movimiento->user?->name ?? 'Sistema' }}</span>
                                    </td>

                                    <td class="text-end pe-4">
                                        @if ($movimiento->unidad)
                                            <button type="button" class="btn btn-sm btn-ghost-info btn-icon rounded-circle"
                                                wire:click="abrirUnidad({{ $movimiento->unidad_id }})"
                                                title="Ver la historia de este aparato">
                                                <i class="ri-history-line fs-16"></i>
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="text-center py-5">
                                            <div class="crud-empty-icon mx-auto mb-4">
                                                <span class="avatar-title rounded-circle fs-1">
                                                    <i class="ri-history-line"></i>
                                                </span>
                                            </div>
                                            @if ($buscar !== '' || $tipoFiltro !== '')
                                                <h5 class="mb-1">Sin movimientos con estos filtros</h5>
                                                <p class="text-muted mb-3">Prueba con otros términos o quita el filtro.</p>
                                                <button type="button" class="btn btn-soft-secondary btn-sm"
                                                    wire:click="$set('buscar', ''); $set('tipoFiltro', '')">
                                                    <i class="ri-close-line align-bottom me-1"></i> Quitar filtros
                                                </button>
                                            @else
                                                <h5 class="mb-1">Todavía no hay movimientos</h5>
                                                <p class="text-muted mb-0">
                                                    El kardex se llena solo: cada compra recepcionada y
                                                    cada cambio de estado deja aquí su rastro.
                                                </p>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if ($movimientos->hasPages())
                <div class="card-footer bg-transparent border-top-dashed">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <p class="text-muted mb-0 fs-13">
                            Mostrando {{ $movimientos->firstItem() }}-{{ $movimientos->lastItem() }}
                            de {{ $movimientos->total() }}
                        </p>
                        <div class="crud-paginacion">
                            {{ $movimientos->onEachSide(1)->links() }}
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
