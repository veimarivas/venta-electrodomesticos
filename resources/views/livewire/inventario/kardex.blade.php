<div class="items-modulo kardex-modulo">

    @php
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

    {{-- ===================== Encabezado ===================== --}}
    <div class="kardex-hero mb-4">
        <div class="kardex-hero-bg" aria-hidden="true"></div>
        <div class="kardex-hero-content">
            <div class="kardex-hero-texto">
                <span class="kardex-hero-chip">
                    <i class="ri-history-line"></i>
                    Inventario
                </span>
                <h1 class="kardex-hero-titulo">Kardex</h1>
                <p class="kardex-hero-subtitulo">
                    Historial completo de cada aparato: cuándo entró, cómo cambió y por qué.
                </p>
            </div>
            <div class="kardex-hero-acciones">
                <div class="kardex-hero-stat">
                    <span class="kardex-hero-stat-valor">{{ number_format($totalMovimientos) }}</span>
                    <span class="kardex-hero-stat-label">movimientos</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Resumen rápido ===================== --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="kardex-mini-stat">
                <div class="kardex-mini-stat-icono kardex-mini-stat--hoy">
                    <i class="ri-calendar-check-line"></i>
                </div>
                <div>
                    <span class="kardex-mini-stat-valor">{{ $movimientosHoy }}</span>
                    <span class="kardex-mini-stat-label">Movimientos hoy</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kardex-mini-stat">
                <div class="kardex-mini-stat-icono kardex-mini-stat--entradas">
                    <i class="ri-arrow-down-circle-line"></i>
                </div>
                <div>
                    <span class="kardex-mini-stat-valor">{{ $entradasDelMes }}</span>
                    <span class="kardex-mini-stat-label">Entradas este mes</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kardex-mini-stat">
                <div class="kardex-mini-stat-icono kardex-mini-stat--ajustes">
                    <i class="ri-settings-3-line"></i>
                </div>
                <div>
                    <span class="kardex-mini-stat-valor">{{ $ajustesDelMes }}</span>
                    <span class="kardex-mini-stat-label">Ajustes este mes</span>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="kardex-mini-stat">
                <div class="kardex-mini-stat-icono kardex-mini-stat--total">
                    <i class="ri-inbox-line"></i>
                </div>
                <div>
                    <span class="kardex-mini-stat-valor">{{ number_format($totalMovimientos) }}</span>
                    <span class="kardex-mini-stat-label">Total histórico</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Buscador ===================== --}}
    <div class="kardex-buscador-card mb-4">
        <div class="kardex-buscador-encabezado">
            <div class="kardex-buscador-icono">
                <i class="ri-search-2-line"></i>
            </div>
            <div>
                <h6 class="kardex-buscador-titulo">Buscar aparato</h6>
                <p class="kardex-buscador-hint">Serial, código interno o nombre del producto</p>
            </div>
        </div>
        <div class="kardex-buscador-input-wrap">
            <i class="ri-search-line kardex-buscador-input-icono"></i>
            <input type="text" id="k-buscar" class="kardex-buscador-input"
                placeholder="Escribe al menos 2 caracteres para buscar..."
                wire:model.live.debounce.350ms="buscar" @if (! $unidadId) autofocus @endif>
            @if ($buscar !== '')
                <button type="button" class="kardex-buscador-limpiar"
                    wire:click="$set('buscar', '')" title="Limpiar">
                    <i class="ri-close-line"></i>
                </button>
            @endif
        </div>

        @if (! $unidadId && $this->coincidencias->isNotEmpty())
            <div class="kardex-coincidencias">
                @foreach ($this->coincidencias as $coincidencia)
                    <button type="button" class="kardex-coincidencia"
                        wire:key="coinc-{{ $coincidencia->id }}"
                        wire:click="abrirUnidad({{ $coincidencia->id }})">
                        <div class="kardex-coincidencia-avatar">
                            <i class="ri-box-3-line"></i>
                        </div>
                        <div class="kardex-coincidencia-info">
                            <span class="kardex-coincidencia-nombre">
                                {{ $coincidencia->producto?->nombre ?? 'Producto' }}
                            </span>
                            <span class="kardex-coincidencia-meta">
                                <code>{{ $coincidencia->codigo_interno }}</code>
                                @if ($coincidencia->serial)
                                    <span class="kardex-coincidencia-sep">·</span>
                                    <span class="kardex-coincidencia-serial">{{ $coincidencia->serial }}</span>
                                @endif
                            </span>
                        </div>
                        <span class="kardex-coincidencia-estado">
                            <span class="kardex-estado-punto kardex-estado--{{ $coincidencia->estado }}"></span>
                            {{ $estados[$coincidencia->estado] ?? $coincidencia->estado }}
                        </span>
                        <i class="ri-arrow-right-s-line kardex-coincidencia-flecha"></i>
                    </button>
                @endforeach
            </div>
        @elseif (! $unidadId && mb_strlen(trim($buscar)) >= 2)
            <div class="kardex-vacio">
                <i class="ri-search-eye-line"></i>
                <span>No encontramos ningún aparato con «{{ $buscar }}»</span>
            </div>
        @endif
    </div>

    {{-- ===================== Ficha de la unidad ===================== --}}
    @if ($this->unidad)
        @php $unidad = $this->unidad; @endphp

        <div class="kardex-ficha mb-4">
            {{-- Encabezado de la ficha --}}
            <div class="kardex-ficha-cabecera">
                <button type="button" class="kardex-ficha-cerrar" wire:click="cerrarUnidad" title="Volver al listado">
                    <i class="ri-arrow-left-line"></i>
                </button>
                <div class="kardex-ficha-icono">
                    <i class="ri-box-3-line"></i>
                </div>
                <div class="kardex-ficha-info">
                    @if ($unidad->producto?->categoria)
                        <div class="kardex-ficha-ruta">
                            {{ str_replace(' / ', ' › ', $unidad->producto->categoria->ruta) }}
                        </div>
                    @endif
                    <h3 class="kardex-ficha-nombre">{{ $unidad->producto?->nombre ?? 'Producto' }}</h3>
                    <div class="kardex-ficha-tags">
                        <span class="kardex-tag kardex-tag--sku">{{ $unidad->codigo_interno }}</span>
                        @if ($unidad->serial)
                            <span class="kardex-tag kardex-tag--serial">
                                <i class="ri-fingerprint-line"></i> {{ $unidad->serial }}
                            </span>
                        @endif
                        @if ($unidad->producto?->marca)
                            <span class="kardex-tag kardex-tag--marca">
                                {{ $unidad->producto->marca->nombre }}
                            </span>
                        @endif
                        <span class="kardex-tag kardex-tag--{{ $unidad->estado }}">
                            <span class="kardex-estado-punto kardex-estado--{{ $unidad->estado }}"></span>
                            {{ $estados[$unidad->estado] ?? $unidad->estado }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Stats de la ficha --}}
            <div class="kardex-ficha-stats">
                <div class="kardex-ficha-stat">
                    <div class="kardex-ficha-stat-icono kardex-stat--precio">
                        <i class="ri-money-dollar-circle-line"></i>
                    </div>
                    <div class="kardex-ficha-stat-dato">
                        <span class="kardex-ficha-stat-label">Precio</span>
                        <span class="kardex-ficha-stat-valor">Bs {{ number_format((float) $unidad->precio_venta, 2, ',', '.') }}</span>
                    </div>
                </div>
                <div class="kardex-ficha-stat">
                    <div class="kardex-ficha-stat-icono kardex-stat--fecha">
                        <i class="ri-calendar-line"></i>
                    </div>
                    <div class="kardex-ficha-stat-dato">
                        <span class="kardex-ficha-stat-label">Ingresó</span>
                        <span class="kardex-ficha-stat-valor">{{ $unidad->ingresado_en?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                </div>
                <div class="kardex-ficha-stat">
                    <div class="kardex-ficha-stat-icono kardex-stat--garantia">
                        <i class="ri-shield-check-line"></i>
                    </div>
                    <div class="kardex-ficha-stat-dato">
                        <span class="kardex-ficha-stat-label">Garantía</span>
                        <span class="kardex-ficha-stat-valor">{{ $unidad->garantia_hasta?->format('d/m/Y') ?? '—' }}</span>
                    </div>
                </div>
                <div class="kardex-ficha-stat">
                    <div class="kardex-ficha-stat-icono kardex-stat--movimientos">
                        <i class="ri-history-line"></i>
                    </div>
                    <div class="kardex-ficha-stat-dato">
                        <span class="kardex-ficha-stat-label">Eventos</span>
                        <span class="kardex-ficha-stat-valor">{{ $this->historia->count() }}</span>
                    </div>
                </div>
            </div>

            <div class="kardex-ficha-cuerpo">
                {{-- Ajuste de estado --}}
                @can('inventario.ajustar')
                    <div class="kardex-ajuste">
                        <div class="kardex-ajuste-encabezado">
                            <div class="kardex-ajuste-icono">
                                <i class="ri-equalizer-line"></i>
                            </div>
                            <div>
                                <h6 class="kardex-ajuste-titulo">Ajustar estado</h6>
                                <p class="kardex-ajuste-subtitulo">Cambia el estado y queda registrado en el kardex</p>
                            </div>
                        </div>

                        <form wire:submit="ajustar" autocomplete="off" class="kardex-ajuste-form">
                            <div class="kardex-ajuste-campos">
                                <div class="kardex-ajuste-campo">
                                    <label class="kardex-ajuste-label">
                                        Nuevo estado <span class="text-danger">*</span>
                                    </label>
                                    <select wire:model.live="nuevoEstado"
                                        class="kardex-ajuste-select @error('nuevoEstado') is-invalid @enderror">
                                        @foreach ($estados as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                    @error('nuevoEstado')
                                        <span class="kardex-ajuste-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="kardex-ajuste-campo kardex-ajuste-campo--grow">
                                    <label class="kardex-ajuste-label">
                                        Motivo <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" wire:model.live.debounce.400ms="motivo"
                                        class="kardex-ajuste-input @error('motivo') is-invalid @enderror"
                                        placeholder="Ej. Pantalla rota en el traslado" maxlength="500">
                                    @error('motivo')
                                        <span class="kardex-ajuste-error">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="kardex-ajuste-campo">
                                    <button type="submit" class="kardex-ajuste-btn"
                                        wire:loading.attr="disabled" wire:target="ajustar">
                                        <span wire:loading.remove wire:target="ajustar">
                                            <i class="ri-check-line"></i> Registrar
                                        </span>
                                        <span wire:loading wire:target="ajustar">
                                            <span class="spinner-border spinner-border-sm"></span>
                                        </span>
                                    </button>
                                </div>
                            </div>
                            <p class="kardex-ajuste-nota">
                                <i class="ri-information-line"></i>
                                Queda escrito en el kardex como registro de auditoría.
                            </p>
                        </form>
                    </div>
                @endcan

                {{-- Historia --}}
                <div class="kardex-historia-encabezado">
                    <div class="kardex-historia-icono">
                        <i class="ri-history-line"></i>
                    </div>
                    <h5 class="kardex-historia-titulo">Historia del aparato</h5>
                    <span class="kardex-historia-badge">{{ $this->historia->count() }}</span>
                </div>

                <div class="kardex-timeline">
                    @forelse ($this->historia as $movimiento)
                        <div class="kardex-timeline-evento" wire:key="mov-{{ $movimiento->id }}">
                            <div class="kardex-timeline-linea">
                                <span class="kardex-timeline-punto kardex-punto--{{ $movimiento->tipo }}">
                                    <i class="{{ $iconoTipo[$movimiento->tipo] ?? 'ri-circle-line' }}"></i>
                                </span>
                            </div>

                            <div class="kardex-timeline-contenido">
                                <div class="kardex-timeline-cabecera">
                                    <span class="kardex-timeline-tipo kardex-chip--{{ $movimiento->tipo }}">
                                        {{ $tipos[$movimiento->tipo] ?? $movimiento->tipo }}
                                    </span>
                                    @if ($movimiento->estado_anterior)
                                        <span class="kardex-timeline-transicion">
                                            <span class="kardex-estado-pill">{{ $estados[$movimiento->estado_anterior] ?? $movimiento->estado_anterior }}</span>
                                            <i class="ri-arrow-right-s-line"></i>
                                            <span class="kardex-estado-pill kardex-estado-pill--nuevo">{{ $estados[$movimiento->estado_nuevo] ?? $movimiento->estado_nuevo }}</span>
                                        </span>
                                    @else
                                        <span class="kardex-timeline-transicion">
                                            Entró como
                                            <span class="kardex-estado-pill kardex-estado-pill--nuevo">{{ $estados[$movimiento->estado_nuevo] ?? $movimiento->estado_nuevo }}</span>
                                        </span>
                                    @endif
                                </div>

                                @if ($movimiento->notas)
                                    <p class="kardex-timeline-notas">{{ $movimiento->notas }}</p>
                                @endif

                                <div class="kardex-timeline-meta">
                                    <span class="kardex-timeline-fecha">
                                        <i class="ri-time-line"></i>
                                        {{ $movimiento->created_at->format('d/m/Y H:i') }}
                                    </span>
                                    <span class="kardex-timeline-usuario">
                                        <i class="ri-user-3-line"></i>
                                        {{ $movimiento->user?->name ?? 'Sistema' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="kardex-timeline-vacio">
                            <div class="kardex-timeline-vacio-icono">
                                <i class="ri-inbox-line"></i>
                            </div>
                            <p class="kardex-timeline-vacio-texto">
                                Este aparato todavía no tiene movimientos registrados.
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    @else
        {{-- ===================== Listado general ===================== --}}
        <div class="kardex-listado mb-4">
            <div class="kardex-listado-toolbar">
                <div class="kardex-listado-info">
                    <h5 class="kardex-listado-titulo">
                        Movimientos recientes
                        <span class="spinner-border spinner-border-sm text-muted" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <p class="kardex-listado-contador">
                        {{ $movimientos->total() }}
                        {{ $movimientos->total() === 1 ? 'movimiento' : 'movimientos' }}
                        @if ($buscar !== '')
                            para «{{ $buscar }}»
                        @endif
                    </p>
                </div>
                <div class="kardex-listado-filtro">
                    <select class="kardex-listado-select" wire:model.live="tipoFiltro">
                        <option value="">Todos los tipos</option>
                        @foreach ($tipos as $valor => $etiqueta)
                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="kardex-listado-cuerpo">
                <table class="kardex-tabla" wire:loading.class="opacity-50" wire:target="buscar, tipoFiltro">
                    <thead>
                        <tr>
                            <th class="kardex-tabla-th--fecha">Fecha</th>
                            <th>Aparato</th>
                            <th>Movimiento</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimientos as $movimiento)
                            <tr class="kardex-tabla-fila" wire:key="mov-{{ $movimiento->id }}">
                                <td class="kardex-tabla-td--fecha">
                                    <span class="kardex-tabla-fecha">{{ $movimiento->created_at->format('d/m') }}</span>
                                    <span class="kardex-tabla-hora">{{ $movimiento->created_at->format('H:i') }}</span>
                                </td>
                                <td>
                                    <div class="kardex-tabla-producto">
                                        {{ $movimiento->unidad?->producto?->nombre ?? 'Producto' }}
                                    </div>
                                    <div class="kardex-tabla-codigo">
                                        <code>{{ $movimiento->unidad?->codigo_interno }}</code>
                                        @if ($movimiento->unidad?->serial)
                                            <span class="kardex-tabla-sep">·</span>
                                            <span>{{ $movimiento->unidad->serial }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="kardex-chip kardex-chip--{{ $movimiento->tipo }}">
                                        <i class="{{ $iconoTipo[$movimiento->tipo] ?? 'ri-circle-line' }}"></i>
                                        {{ $tipos[$movimiento->tipo] ?? $movimiento->tipo }}
                                    </span>
                                    <div class="kardex-tabla-estados">
                                        @if ($movimiento->estado_anterior)
                                            {{ $estados[$movimiento->estado_anterior] ?? $movimiento->estado_anterior }}
                                            <i class="ri-arrow-right-s-line"></i>
                                        @endif
                                        {{ $estados[$movimiento->estado_nuevo] ?? $movimiento->estado_nuevo }}
                                    </div>
                                </td>
                                <td>
                                    <span class="kardex-tabla-notas">
                                        {{ $movimiento->notas ?: '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="kardex-tabla-usuario">{{ $movimiento->user?->name ?? 'Sistema' }}</span>
                                </td>
                                <td class="kardex-tabla-td--accion">
                                    @if ($movimiento->unidad)
                                        <button type="button" class="kardex-tabla-ver"
                                            wire:click="abrirUnidad({{ $movimiento->unidad_id }})"
                                            title="Ver historial de este aparato">
                                            <i class="ri-arrow-right-s-line"></i>
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="kardex-tabla-vacio">
                                        <div class="kardex-tabla-vacio-icono">
                                            <i class="ri-history-line"></i>
                                        </div>
                                        @if ($buscar !== '' || $tipoFiltro !== '')
                                            <h6>Sin movimientos con estos filtros</h6>
                                            <p>Prueba con otros términos o quita el filtro.</p>
                                            <button type="button" class="kardex-btn-secundario"
                                                wire:click="$set('buscar', ''); $set('tipoFiltro', '')">
                                                <i class="ri-close-line"></i> Quitar filtros
                                            </button>
                                        @else
                                            <h6>Todavía no hay movimientos</h6>
                                            <p>El kardex se llena solo: cada compra recepcionada y cada cambio de estado deja aquí su rastro.</p>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($movimientos->hasPages())
                <div class="kardex-listado-pie">
                    <span class="kardex-listado-paginfo">
                        Mostrando {{ $movimientos->firstItem() }}–{{ $movimientos->lastItem() }}
                        de {{ $movimientos->total() }}
                    </span>
                    <div class="kardex-paginacion">
                        {{ $movimientos->onEachSide(1)->links() }}
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
