@php
    $bs = fn (float $monto): string => 'Bs '.number_format($monto, 2, ',', '.');
    $r = $this->resultado;
    $resumen = $r['resumen'];
    $tiempo = function (int $dias): string {
        if ($dias < 60) {
            return $dias.' días';
        }

        $meses = intdiv($dias, 30);

        return $meses < 24 ? $meses.' meses' : round($dias / 365, 1).' años';
    };
    $presets = [90 => '3 meses', 180 => '6 meses', 270 => '9 meses', 365 => '1 año'];
@endphp

<div class="amarilla-modulo">

    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <span class="badge text-white mb-3 crud-chip">
                    <i class="ri-alarm-warning-line me-1"></i> Inventario · Lista amarilla
                </span>
                <h4 class="text-white mb-1">Lista amarilla</h4>
                <p class="text-white-50 mb-0">
                    Aparatos que llevan {{ $tiempo($dias) }} o más en la tienda, contados a hoy
                    ({{ now()->translatedFormat('d \d\e F \d\e Y') }}). Es mercadería que no rota: conviene bajarle el precio,
                    ponerla en vitrina o no volver a comprarla.
                </p>
            </div>
        </div>
    </div>

    {{-- ===================== Filtros ===================== --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <div>
                <span class="form-label fs-12 mb-1 d-block">En tienda hace más de</span>
                <div class="precios-filtros" role="group" aria-label="Tiempo en tienda">
                    @foreach ($presets as $valor => $etiqueta)
                        <button type="button" wire:click="$set('dias', {{ $valor }})"
                            @class(['precios-filtro', 'precios-filtro--activo' => $dias === $valor])
                            aria-pressed="{{ $dias === $valor ? 'true' : 'false' }}">{{ $etiqueta }}</button>
                    @endforeach
                </div>
            </div>
            <div style="width: 8.5rem;">
                <label class="form-label fs-12 mb-1" for="la-dias">O días exactos</label>
                <input type="number" id="la-dias" min="1" max="3650" class="form-control form-control-sm"
                    wire:model.live.debounce.500ms="dias">
            </div>
            <div class="flex-grow-1" style="min-width: 14rem;">
                <label class="form-label fs-12 mb-1" for="la-buscar">Buscar</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="ri-search-line"></i></span>
                    <input type="search" id="la-buscar" class="form-control" placeholder="Producto, marca, serial o código"
                        wire:model.live.debounce.400ms="buscar">
                </div>
            </div>
            <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                <span class="visually-hidden">Cargando...</span>
            </span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Aparatos en la lista" value="{{ $resumen['unidades'] }}" icon="bx-error" color="warning"
                caption="{{ $resumen['productos'] }} {{ $resumen['productos'] === 1 ? 'producto' : 'productos' }}" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Críticos" value="{{ $resumen['criticos'] }}" icon="bx-time-five" color="danger"
                caption="Productos con el doble del tiempo o más" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Valor a precio de hoy" value="{{ $bs($resumen['valor_venta']) }}" icon="bx-purchase-tag" color="primary"
                caption="Lo que se cobraría si se vendieran" />
        </div>
        <div class="col-xl-3 col-sm-6">
            @if ($verCostos)
                <x-stat-card label="Capital parado" value="{{ $bs($resumen['capital']) }}" icon="bx-wallet" color="info"
                    caption="Lo que costaron" />
            @else
                <x-stat-card label="El más antiguo" value="{{ $tiempo($resumen['dias_max']) }}" icon="bx-calendar-x" color="info"
                    caption="En la tienda" />
            @endif
        </div>
    </div>

    <div class="row g-4">
        <div @class(['col-12', 'col-xxl-9' => auth()->user()->can('ajustes.editar')])>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    @if ($r['productos'] === [])
                        <div class="text-center py-5 px-3">
                            <div class="crud-empty-icon mx-auto mb-3">
                                <span class="avatar-title fs-2"><i class="ri-checkbox-circle-line"></i></span>
                            </div>
                            <h6 class="mb-1">Nada estancado</h6>
                            <p class="text-muted mb-0 fs-13">
                                Ningún aparato lleva {{ $tiempo($dias) }} o más en la tienda{{ $buscar !== '' ? ' con esa búsqueda' : '' }}.
                            </p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 amarilla-tabla">
                                <thead>
                                    <tr class="text-uppercase fs-11 text-muted">
                                        <th class="ps-4">Producto</th>
                                        <th class="text-end">Aparatos</th>
                                        <th>El más antiguo</th>
                                        <th class="text-end">Promedio</th>
                                        <th class="text-end">Precio hoy</th>
                                        @if ($verCostos)
                                            <th class="text-end pe-4">Capital</th>
                                        @endif
                                    </tr>
                                </thead>
                                @foreach ($r['productos'] as $p)
                                    <tbody wire:key="amarilla-{{ $p['producto_id'] }}">
                                        <tr class="amarilla-fila" wire:click="alternar({{ $p['producto_id'] }})" role="button"
                                            aria-expanded="{{ $abierto === $p['producto_id'] ? 'true' : 'false' }}">
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center gap-3">
                                                    <i class="ri-arrow-{{ $abierto === $p['producto_id'] ? 'down' : 'right' }}-s-line text-muted"></i>
                                                    <span @class(['amarilla-nivel', 'amarilla-nivel--critico' => $p['nivel'] === 'critico'])
                                                        title="{{ $p['nivel'] === 'critico' ? 'Crítico' : 'Atención' }}"></span>
                                                    <div class="min-w-0">
                                                        <div class="fw-semibold text-truncate">{{ $p['producto'] }}</div>
                                                        <small class="text-muted">{{ collect([$p['marca'], $p['modelo'], $p['categoria']])->filter()->join(' · ') }}</small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-end tabular fw-semibold">{{ $p['unidades'] }}</td>
                                            <td>
                                                <span @class(['amarilla-dias', 'amarilla-dias--critico' => $p['nivel'] === 'critico'])>
                                                    {{ $tiempo($p['dias_max']) }}
                                                </span>
                                                <small class="d-block text-muted">desde {{ \Illuminate\Support\Carbon::parse($p['ingreso_mas_antiguo'])->format('d/m/Y') }}</small>
                                            </td>
                                            <td class="text-end tabular text-muted">{{ $p['dias_promedio'] }} días</td>
                                            <td class="text-end tabular">{{ $bs($p['precio']) }}</td>
                                            @if ($verCostos)
                                                <td class="text-end tabular pe-4">{{ $bs($p['capital']) }}</td>
                                            @endif
                                        </tr>

                                        @if ($abierto === $p['producto_id'])
                                            <tr class="amarilla-detalle">
                                                <td colspan="{{ $verCostos ? 6 : 5 }}" class="px-4 pb-4">
                                                    <div class="table-responsive">
                                                        <table class="table table-sm mb-3">
                                                            <thead>
                                                                <tr class="fs-11 text-muted">
                                                                    <th>Código</th>
                                                                    <th>Serial</th>
                                                                    <th>Entró</th>
                                                                    <th class="text-end">Días</th>
                                                                    <th>Compra</th>
                                                                    @if ($verCostos)
                                                                        <th class="text-end">Costo</th>
                                                                    @endif
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($p['aparatos'] as $a)
                                                                    <tr>
                                                                        <td class="font-monospace">{{ $a['codigo'] }}</td>
                                                                        <td class="font-monospace text-muted">{{ $a['serial'] ?? '—' }}</td>
                                                                        <td>{{ \Illuminate\Support\Carbon::parse($a['ingresado_en'])->format('d/m/Y') }}</td>
                                                                        <td class="text-end tabular fw-semibold">{{ $a['dias'] }}</td>
                                                                        <td class="text-muted">
                                                                            {{ $a['compra'] ?? 'Alta manual' }}
                                                                            @if ($a['estado'] === 'reservado')
                                                                                <span class="badge bg-info-subtle text-info ms-1">En un carrito</span>
                                                                            @endif
                                                                        </td>
                                                                        @if ($verCostos)
                                                                            <td class="text-end tabular">{{ $bs($a['costo']) }}</td>
                                                                        @endif
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                    <div class="d-flex flex-wrap gap-2">
                                                        @can('caja.gestionar')
                                                            <a href="{{ route('precios.index') }}" class="btn btn-sm btn-soft-warning">
                                                                <i class="ri-price-tag-3-line me-1"></i> Revisar su precio del día
                                                            </a>
                                                        @endcan
                                                        @can('unidades.ver')
                                                            <a href="{{ route('dashboard.producto', $p['producto_id']) }}" class="btn btn-sm btn-soft-secondary">
                                                                <i class="ri-barcode-box-line me-1"></i> Ver sus unidades
                                                            </a>
                                                        @endcan
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
        </div>

        @can('ajustes.editar')
            <div class="col-12 col-xxl-3">
                <div class="card border-0 shadow-sm amarilla-ajuste">
                    <div class="card-body">
                        <h6 class="mb-1"><i class="ri-settings-3-line me-1"></i> Umbral de la tienda</h6>
                        <p class="text-muted fs-13 mb-3">
                            Desde cuántos días un aparato entra a la lista. Es el que se abre por defecto aquí y en el
                            teléfono, y el que cuenta el aviso del inicio. Hoy: <strong>{{ $umbral }} días</strong>.
                        </p>
                        <form wire:submit="guardarUmbral" class="d-flex gap-2 align-items-start">
                            <div class="flex-grow-1">
                                <label class="visually-hidden" for="la-umbral">Días</label>
                                <input type="number" id="la-umbral" min="30" max="1095"
                                    @class(['form-control form-control-sm', 'is-invalid' => $errors->has('umbralNuevo')])
                                    wire:model="umbralNuevo">
                                @error('umbralNuevo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                        </form>
                    </div>
                </div>
            </div>
        @endcan
    </div>
</div>
