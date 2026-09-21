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
                                    La jornada empieza aquí: hasta guardarlos, el punto de venta no cobra.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            <button type="button" class="btn btn-light crud-nueva-hero"
                                wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
                                <span wire:loading.remove wire:target="guardar">
                                    <i class="ri-save-line align-bottom me-1"></i> Guardar precios
                                </span>
                                <span wire:loading wire:target="guardar">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Guardando...
                                </span>
                            </button>
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
        <div class="col-xl-4 col-md-4">
            <x-stat-card label="Con stock" value="{{ $total }}" icon="bx-package" color="primary"
                caption="Productos por revisar" />
        </div>
        <div class="col-xl-4 col-md-4">
            <x-stat-card label="Ya fijados hoy" value="{{ $fijados }}" icon="bx-check-circle" color="success"
                caption="Con precio de esta jornada" />
        </div>
        <div class="col-xl-4 col-md-4">
            <x-stat-card label="Pendientes" value="{{ $pendientes }}" icon="bx-time-five" color="warning"
                caption="Sin precio de hoy" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-6">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Productos con stock
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        Se propone el precio de la jornada anterior; si no hay, el inicial.
                    </small>
                </div>

                <div class="col-md-6">
                    <div class="d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                        <div class="search-box" style="min-width: 14rem;">
                            <input type="text" class="form-control"
                                placeholder="Buscar por producto o categoría..."
                                wire:model.live.debounce.400ms="buscar">
                            <i class="ri-search-line search-icon"></i>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" role="switch"
                                id="solo-pendientes" wire:model.live="soloPendientes">
                            <label class="form-check-label" for="solo-pendientes">Solo pendientes</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            @if ($filas->isEmpty())
                <p class="text-muted text-center py-5 mb-0">
                    @if ($total === 0)
                        No hay productos con stock para fijar precios.
                    @else
                        No hay productos que coincidan con el filtro.
                    @endif
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr class="text-uppercase fs-11 text-muted">
                                <th class="ps-4">Producto</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Costo</th>
                                <th class="text-end">Anterior</th>
                                <th class="text-end pe-4" style="min-width: 13rem;">Precio de hoy</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $fila)
                                @php
                                    $id = $fila->producto->id;
                                    $anterior = $fila->precio_anterior;
                                    $actual = (float) ($precios[$id] ?? $anterior);
                                    $delta = round($actual - $anterior, 2);
                                @endphp
                                <tr wire:key="precio-{{ $id }}"
                                    @class(['precios-fila-ya' => $fila->precio_hoy !== null])>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="precios-imagen-mini">
                                                @if ($fila->producto->imagen)
                                                    <img src="{{ asset('storage/'.$fila->producto->imagen) }}"
                                                        alt="{{ $fila->producto->nombre }}">
                                                @else
                                                    <img src="{{ asset('assets/images/sin_imagen.png') }}"
                                                        alt="{{ $fila->producto->nombre }}" class="precios-imagen-mini-sin">
                                                @endif
                                            </div>
                                            <div class="min-w-0">
                                                <h6 class="mb-0 text-truncate" style="max-width: 16rem">
                                                    {{ $fila->producto->nombre }}
                                                </h6>
                                                <small class="text-muted">
                                                    {{ $fila->producto->categoria?->nombre ?? 'Sin categoría' }}
                                                    @if ($fila->precio_hoy !== null)
                                                        · <span class="precios-ya"><i class="ri-check-line"></i>Ya fijado</span>
                                                    @endif
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end">{{ $fila->disponibles }}</td>
                                    <td class="text-end text-muted">
                                        Bs {{ number_format($fila->costo, 2, ',', '.') }}
                                    </td>
                                    <td class="text-end text-muted">
                                        Bs {{ number_format($anterior, 2, ',', '.') }}
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="input-group input-group-sm precios-input">
                                            <span class="input-group-text">Bs</span>
                                            <input type="number" step="0.01" min="0.01"
                                                class="form-control text-end @error('precios.'.$id) is-invalid @enderror"
                                                wire:model.live.debounce.500ms="precios.{{ $id }}">
                                            @error('precios.'.$id)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <span @class([
                                            'precios-cambio',
                                            'precios-cambio--sube' => $delta > 0,
                                            'precios-cambio--baja' => $delta < 0,
                                            'precios-cambio--igual' => $delta === 0.0,
                                        ])>
                                            @if ($delta > 0)
                                                + Bs {{ number_format($delta, 2, ',', '.') }}
                                            @elseif ($delta < 0)
                                                − Bs {{ number_format(abs($delta), 2, ',', '.') }}
                                            @else
                                                Sin cambio
                                            @endif
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== Barra de guardar ===================== --}}
    @if ($total > 0)
        <div class="precios-barra">
            <div class="precios-barra-info">
                @if ($pendientes > 0)
                    Faltan <strong>{{ $pendientes }}</strong>
                    {{ $pendientes === 1 ? 'producto' : 'productos' }} por fijar hoy.
                @else
                    <strong>Todo fijado.</strong> El punto de venta puede cobrar.
                @endif
            </div>
            <button type="button" class="btn btn-primary" wire:click="guardar"
                wire:loading.attr="disabled" wire:target="guardar">
                <span wire:loading.remove wire:target="guardar">
                    <i class="ri-save-line align-bottom me-1"></i> Guardar precios del día
                </span>
                <span wire:loading wire:target="guardar">
                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                    Guardando...
                </span>
            </button>
        </div>
    @endif
</div>
