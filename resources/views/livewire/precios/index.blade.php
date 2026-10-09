@php
    $bs = fn (float $monto): string => 'Bs '.number_format($monto, 2, ',', '.');
    $bsEntero = fn (float $monto): string => 'Bs '.number_format($monto, $monto == floor($monto) ? 0 : 2, ',', '.');
    $sinCambio = $total - $suben - $bajan;
@endphp

<div class="precios-modulo">

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-price-tag-3-line me-1"></i>
                            Catálogo · Precios del día
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                                    <i class="ri-calendar-check-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Precios del día</h4>
                                <p class="text-white-50 mb-0">
                                    Jornada del {{ now()->translatedFormat('l j \d\e F') }}. Se vende con lo que
                                    confirmes aquí; lo que no cambies sigue al precio de ayer.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex justify-content-lg-end">
                            @if ($total === 0)
                                <span class="precios-jornada precios-jornada--libre">
                                    <i class="ri-checkbox-circle-line"></i> Nada que confirmar hoy
                                </span>
                            @elseif ($confirmados)
                                <span class="precios-jornada precios-jornada--ok">
                                    <i class="ri-checkbox-circle-line"></i> Confirmados · el punto de venta cobra
                                </span>
                            @else
                                <span class="precios-jornada precios-jornada--pendiente">
                                    <i class="ri-time-line"></i> Sin confirmar · el punto de venta no cobra
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @error('precios')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4 crud-kpis">
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Con stock" value="{{ $total }}" icon="bx-package" color="primary"
                caption="Productos por revisar" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Confirmados hoy" value="{{ $fijados }}" icon="bx-check-circle" color="success"
                caption="Con precio de esta jornada" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Pendientes" value="{{ $pendientes }}" icon="bx-time-five" color="warning"
                caption="Sin confirmar hoy" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Sugerencias" value="{{ $sugerencias }}" icon="bx-bulb" color="info"
                caption="Por compras con otro costo" />
        </div>
    </div>

    {{-- ===================== Aviso de sugerencias ===================== --}}
    @if ($sugerencias > 0)
        <div class="precios-aviso mb-4">
            <span class="precios-aviso-icono" aria-hidden="true"><i class="ri-lightbulb-flash-line"></i></span>
            <div class="precios-aviso-texto">
                <h6 class="mb-1">
                    {{ $sugerencias === 1 ? 'Un producto llegó' : $sugerencias.' productos llegaron' }}
                    con otro costo
                </h6>
                <p class="mb-0">
                    Se propone mover el precio en la misma proporción que el costo para conservar el margen.
                    <strong>Nada cambia hasta que lo apliques y confirmes.</strong>
                </p>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm flex-shrink-0"
                wire:click="aplicarSugerencias" wire:loading.attr="disabled">
                <i class="ri-magic-line align-bottom me-1"></i> Aplicar todas
            </button>
        </div>
    @endif

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3">
            <div class="row g-3 align-items-center">
                <div class="col-lg-5">
                    <div class="search-box">
                        <input type="text" class="form-control"
                            placeholder="Buscar por producto o categoría..."
                            wire:model.live.debounce.400ms="buscar">
                        <i class="ri-search-line search-icon"></i>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="precios-filtros justify-content-lg-end" role="group" aria-label="Filtrar productos">
                        @foreach ([
                            'todos' => ['Todos', $total],
                            'pendientes' => ['Pendientes', $pendientes],
                            'sugerencias' => ['Con sugerencia', $sugerencias],
                            'cambiados' => ['Cambiados', $suben + $bajan],
                        ] as $valor => [$etiqueta, $cuenta])
                            <button type="button" wire:click="$set('filtro', '{{ $valor }}')"
                                @class(['precios-filtro', 'precios-filtro--activo' => $filtro === $valor])
                                aria-pressed="{{ $filtro === $valor ? 'true' : 'false' }}">
                                {{ $etiqueta }} <span class="precios-filtro-cuenta">{{ $cuenta }}</span>
                            </button>
                        @endforeach
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            @if ($filas->isEmpty())
                <div class="text-center py-5 px-3">
                    <div class="crud-empty-icon mx-auto mb-3">
                        <span class="avatar-title fs-2"><i class="ri-price-tag-3-line"></i></span>
                    </div>
                    <h6 class="mb-1">
                        @if ($total === 0)
                            No hay productos con stock
                        @elseif ($filtro === 'sugerencias')
                            Sin sugerencias para hoy
                        @elseif ($filtro === 'cambiados')
                            Ningún precio cambiado todavía
                        @elseif ($filtro === 'pendientes')
                            Todo confirmado
                        @else
                            Nada coincide con la búsqueda
                        @endif
                    </h6>
                    <p class="text-muted mb-0 fs-13">
                        @if ($total === 0)
                            Los precios se fijan para lo que hay en la estantería.
                        @elseif ($filtro === 'sugerencias')
                            Las compras recibidas hasta ayer mantienen el costo de siempre.
                        @elseif ($filtro === 'cambiados')
                            Si confirmas así, todo se vende al precio de ayer.
                        @else
                            Prueba con otro filtro o con otra palabra.
                        @endif
                    </p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0 precios-tabla">
                        <thead>
                            <tr class="text-uppercase fs-11 text-muted">
                                <th class="ps-4">Producto</th>
                                <th class="text-end d-none d-md-table-cell">Stock</th>
                                <th class="text-end d-none d-md-table-cell">Costo</th>
                                <th class="text-end d-none d-md-table-cell">Ayer</th>
                                <th class="text-end pe-4 precios-col-hoy">Precio de hoy</th>
                            </tr>
                        </thead>
                        @foreach ($filas as $fila)
                            @php
                                $id = $fila->producto->id;
                                $anterior = $fila->precio_anterior;
                                $texto = $precios[$id] ?? '';
                                $actual = is_numeric($texto) ? (float) $texto : null;
                                $delta = $actual === null ? 0.0 : round($actual - $anterior, 2);
                                $margen = $actual !== null && $actual > 0 && $fila->costo > 0
                                    ? ($actual - $fila->costo) / $actual * 100
                                    : null;
                                $sug = $fila->sugerencia;
                                $sugAplicada = $sug !== null && $actual !== null && abs($actual - $sug->precio_sugerido) < 0.005;
                            @endphp
                            <tbody wire:key="precio-{{ $id }}" @class([
                                'precios-grupo',
                                'precios-grupo--ya' => $fila->precio_hoy !== null,
                                'precios-grupo--cambio' => $delta !== 0.0,
                            ])>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="precios-imagen-mini">
                                                @if ($fila->producto->imagen)
                                                    <img src="{{ asset('storage/'.$fila->producto->imagen) }}"
                                                        alt="{{ $fila->producto->nombre }}">
                                                @else
                                                    <img src="{{ asset('assets/images/sin_imagen.png') }}"
                                                        alt="" class="precios-imagen-mini-sin">
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <h6 class="mb-0 precios-nombre">
                                                    {{ $fila->producto->nombre }}
                                                </h6>
                                                <small class="text-muted">
                                                    {{ $fila->producto->categoria?->nombre ?? 'Sin categoría' }}
                                                    @if ($fila->precio_hoy !== null)
                                                        · <span class="precios-ya"><i class="ri-check-line"></i>Confirmado</span>
                                                    @endif
                                                </small>
                                                {{-- En el teléfono las columnas de referencia van aquí. --}}
                                                <small class="d-block d-md-none text-muted tabular">
                                                    {{ $fila->disponibles }} en stock · costo {{ $bs($fila->costo) }} · ayer {{ $bs($anterior) }}
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end tabular d-none d-md-table-cell">{{ $fila->disponibles }}</td>
                                    <td class="text-end text-muted tabular d-none d-md-table-cell">{{ $bs($fila->costo) }}</td>
                                    <td class="text-end text-muted tabular d-none d-md-table-cell">{{ $bs($anterior) }}</td>
                                    <td class="text-end pe-4">
                                        <div class="input-group input-group-sm precios-input">
                                            <span class="input-group-text">Bs</span>
                                            <input type="number" step="0.01" min="0.01" inputmode="decimal"
                                                aria-label="Precio de hoy de {{ $fila->producto->nombre }}"
                                                class="form-control text-end @error('precios.'.$id) is-invalid @enderror"
                                                wire:model.live.debounce.500ms="precios.{{ $id }}">
                                            @error('precios.'.$id)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="precios-pie">
                                            @if ($margen !== null)
                                                <span @class([
                                                    'precios-margen',
                                                    'precios-margen--perdida' => $margen <= 0,
                                                ])>
                                                    {{ $margen <= 0 ? 'Bajo el costo' : 'Margen '.number_format($margen, 0).' %' }}
                                                </span>
                                            @endif
                                            <span @class([
                                                'precios-cambio',
                                                'precios-cambio--sube' => $delta > 0,
                                                'precios-cambio--baja' => $delta < 0,
                                                'precios-cambio--igual' => $delta === 0.0,
                                            ])>
                                                @if ($delta > 0)
                                                    <i class="ri-arrow-up-line"></i>{{ $bs($delta) }}
                                                @elseif ($delta < 0)
                                                    <i class="ri-arrow-down-line"></i>{{ $bs(abs($delta)) }}
                                                @else
                                                    Igual que ayer
                                                @endif
                                            </span>
                                            @if ($delta !== 0.0)
                                                <button type="button" class="precios-deshacer"
                                                    wire:click="restablecer({{ $id }})"
                                                    title="Volver al precio de ayer">
                                                    <i class="ri-arrow-go-back-line"></i>
                                                    <span class="visually-hidden">Volver al precio de ayer</span>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                @if ($sug !== null)
                                    <tr class="precios-sugerencia-fila">
                                        <td colspan="5" class="ps-4 pe-4 pt-0">
                                            <div @class([
                                                'precios-sugerencia',
                                                'precios-sugerencia--aplicada' => $sugAplicada,
                                            ])>
                                                <i class="ri-lightbulb-line precios-sugerencia-icono" aria-hidden="true"></i>
                                                <div class="precios-sugerencia-cuerpo">
                                                    <span class="precios-sugerencia-texto">
                                                        {{ $sug->compra_codigo ? 'Compra '.$sug->compra_codigo : 'Compra nueva' }}
                                                        del {{ \Illuminate\Support\Carbon::parse($sug->recibida_en)->format('d/m') }}:
                                                        costo {{ $bs($sug->costo_anterior) }} → <strong>{{ $bs($sug->costo_nuevo) }}</strong>
                                                        ({{ $sug->variacion_costo > 0 ? '+' : '' }}{{ number_format($sug->variacion_costo, 1, ',', '.') }} %).
                                                    </span>
                                                    <span class="precios-sugerencia-propuesta">
                                                        Para conservar el margen,
                                                        {{ $sug->tipo === 'sube' ? 'subir' : 'bajar' }} a
                                                        <strong>{{ $bsEntero($sug->precio_sugerido) }}</strong>.
                                                    </span>
                                                </div>
                                                @if ($sugAplicada)
                                                    <span class="precios-sugerencia-ok">
                                                        <i class="ri-check-line"></i> Aplicada
                                                    </span>
                                                @else
                                                    <button type="button" class="btn btn-sm btn-soft-primary flex-shrink-0"
                                                        wire:click="aplicarSugerencia({{ $id }})">
                                                        Aplicar {{ $bsEntero($sug->precio_sugerido) }}
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== Barra de confirmar ===================== --}}
    @if ($total > 0)
        <div class="precios-barra">
            <div class="precios-barra-info">
                <div class="precios-barra-cuentas">
                    <span><strong>{{ $suben }}</strong> suben</span>
                    <span><strong>{{ $bajan }}</strong> bajan</span>
                    <span><strong>{{ $sinCambio }}</strong> igual que ayer</span>
                </div>
                <small>
                    @error('precios')
                        <span class="text-danger">No se confirmó: hay precios en o por debajo del costo (marcados en rojo).</span>
                    @elseif ($confirmados && $sinGuardar === 0)
                        <span class="text-success">Jornada confirmada: el punto de venta cobra con estos precios.</span>
                    @elseif ($confirmados)
                        Hay {{ $sinGuardar }} {{ $sinGuardar === 1 ? 'corrección' : 'correcciones' }} sin guardar.
                    @else
                        Al confirmar, el punto de venta cobra con estos precios.
                    @enderror
                </small>
            </div>
            <button type="button" class="btn btn-primary" wire:click="guardar"
                wire:loading.attr="disabled" wire:target="guardar">
                <span wire:loading.remove wire:target="guardar">
                    <i class="ri-check-double-line align-bottom me-1"></i>
                    @if ($confirmados)
                        {{ $sinGuardar === 0 ? 'Volver a confirmar' : 'Guardar correcciones' }}
                    @else
                        {{ $suben + $bajan === 0 ? 'Confirmar sin cambios' : 'Confirmar precios del día' }}
                    @endif
                </span>
                <span wire:loading wire:target="guardar">
                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                    Confirmando...
                </span>
            </button>
        </div>
    @endif
</div>
