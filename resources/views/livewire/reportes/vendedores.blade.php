@php
    $bs = fn (float $monto): string => 'Bs '.number_format($monto, 2, ',', '.');
@endphp

<div class="resumen-modulo">

    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <span class="badge text-white mb-3 crud-chip">
                    <i class="ri-user-star-line me-1"></i> Análisis · Ventas por vendedor
                </span>
                <h4 class="text-white mb-1">Ventas por vendedor</h4>
                <p class="text-white-50 mb-0">
                    Qué vendió cada uno y a qué precio: cuánto rebajó de la lista y cuánto cobró por encima.
                </p>
            </div>
        </div>
    </div>

    {{-- ===================== Filtros ===================== --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <div class="precios-filtros" role="group" aria-label="Período">
                @foreach (['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'rango' => 'Rango'] as $valor => $etiqueta)
                    <button type="button" wire:click="$set('periodo', '{{ $valor }}')"
                        @class(['precios-filtro', 'precios-filtro--activo' => $periodo === $valor])
                        aria-pressed="{{ $periodo === $valor ? 'true' : 'false' }}">{{ $etiqueta }}</button>
                @endforeach
            </div>
            <div>
                <label class="form-label fs-12 mb-1" for="v-desde">Desde</label>
                <input type="date" id="v-desde" class="form-control form-control-sm" wire:model.live="desde" max="{{ now()->toDateString() }}">
            </div>
            <div>
                <label class="form-label fs-12 mb-1" for="v-hasta">Hasta</label>
                <input type="date" id="v-hasta" class="form-control form-control-sm" wire:model.live="hasta" max="{{ now()->toDateString() }}">
            </div>
            <div style="min-width: 12rem;">
                <label class="form-label fs-12 mb-1" for="v-vendedor">Vendedor</label>
                <select id="v-vendedor" class="form-select form-select-sm" wire:model.live="vendedorId">
                    <option value="">Todos</option>
                    @foreach ($this->opciones as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                <span class="visually-hidden">Cargando...</span>
            </span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Vendido" value="{{ $bs($totales['total']) }}" icon="bx-cart" color="primary"
                caption="{{ $totales['unidades'] }} unidades" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Descuentos dados" value="{{ $bs($totales['descuento']) }}" icon="bx-trending-down" color="warning"
                caption="Rebajado de la lista" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Sobreprecio" value="{{ $bs($totales['sobreprecio']) }}" icon="bx-trending-up" color="success"
                caption="Cobrado por encima de la lista" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Balance" value="{{ $bs($totales['sobreprecio'] - $totales['descuento']) }}" icon="bx-scale" color="info"
                caption="Sobreprecio menos descuentos" />
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if ($filas->isEmpty())
                <div class="text-center py-5 px-3">
                    <div class="crud-empty-icon mx-auto mb-3">
                        <span class="avatar-title fs-2"><i class="ri-user-star-line"></i></span>
                    </div>
                    <h6 class="mb-1">Sin ventas en el período</h6>
                    <p class="text-muted mb-0 fs-13">Prueba con otro rango de fechas.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0 vendedores-tabla">
                        <thead>
                            <tr class="text-uppercase fs-11 text-muted">
                                <th class="ps-4">Vendedor</th>
                                <th class="text-end">Ventas</th>
                                <th class="text-end">Unidades</th>
                                <th class="text-end">Vendido</th>
                                <th class="text-end">Descuento</th>
                                <th class="text-end">Sobreprecio</th>
                                <th class="text-end pe-4">Balance</th>
                            </tr>
                        </thead>
                        @foreach ($filas as $v)
                            <tbody wire:key="vendedor-{{ $v['vendedor_id'] ?? 0 }}" class="vendedores-grupo">
                                <tr class="vendedores-fila" wire:click="alternar({{ $v['vendedor_id'] ?? 'null' }})"
                                    role="button" aria-expanded="{{ $abierto === $v['vendedor_id'] ? 'true' : 'false' }}">
                                    <td class="ps-4">
                                        <i class="ri-arrow-{{ $abierto === $v['vendedor_id'] ? 'down' : 'right' }}-s-line text-muted"></i>
                                        <span class="fw-semibold">{{ $v['vendedor'] }}</span>
                                    </td>
                                    <td class="text-end tabular">{{ $v['ventas'] }}</td>
                                    <td class="text-end tabular">{{ $v['unidades'] }}</td>
                                    <td class="text-end tabular fw-semibold">{{ $bs($v['total']) }}</td>
                                    <td class="text-end tabular">
                                        <span class="vendedores-descuento">{{ $bs($v['descuento']) }}</span>
                                        <small class="d-block text-muted">{{ $v['con_descuento'] }} con rebaja</small>
                                    </td>
                                    <td class="text-end tabular">
                                        <span class="vendedores-sobreprecio">{{ $bs($v['sobreprecio']) }}</span>
                                        <small class="d-block text-muted">{{ $v['con_sobreprecio'] }} por encima</small>
                                    </td>
                                    <td class="text-end pe-4 tabular">
                                        <span @class(['fw-semibold', 'text-danger' => $v['balance'] < 0, 'text-success' => $v['balance'] > 0])>
                                            {{ $v['balance'] > 0 ? '+' : '' }}{{ $bs($v['balance']) }}
                                        </span>
                                    </td>
                                </tr>

                                @if ($abierto === $v['vendedor_id'])
                                    <tr class="vendedores-detalle">
                                        <td colspan="7" class="px-4 pb-4">
                                            <h6 class="mt-2 mb-2 fs-13 text-uppercase text-muted">Por producto</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-3">
                                                    <thead>
                                                        <tr class="fs-11 text-muted">
                                                            <th>Producto</th>
                                                            <th class="text-end">Unidades</th>
                                                            <th class="text-end">Lista</th>
                                                            <th class="text-end">Cobrado</th>
                                                            <th class="text-end">Descuento</th>
                                                            <th class="text-end">Sobreprecio</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($v['productos'] as $p)
                                                            <tr>
                                                                <td>{{ $p['producto'] }}</td>
                                                                <td class="text-end tabular">{{ $p['unidades'] }}</td>
                                                                <td class="text-end tabular text-muted">{{ $bs($p['total_lista']) }}</td>
                                                                <td class="text-end tabular">{{ $bs($p['total']) }}</td>
                                                                <td class="text-end tabular vendedores-descuento">{{ $p['descuento'] > 0 ? $bs($p['descuento']) : '—' }}</td>
                                                                <td class="text-end tabular vendedores-sobreprecio">{{ $p['sobreprecio'] > 0 ? $bs($p['sobreprecio']) : '—' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>

                                            @if ($v['desvios'] !== [])
                                                <h6 class="mb-2 fs-13 text-uppercase text-muted">Ventas fuera de la lista</h6>
                                                <div class="table-responsive">
                                                    <table class="table table-sm mb-0">
                                                        <thead>
                                                            <tr class="fs-11 text-muted">
                                                                <th>Venta</th>
                                                                <th>Fecha</th>
                                                                <th>Producto</th>
                                                                <th class="text-end">Lista</th>
                                                                <th class="text-end">Cobrado</th>
                                                                <th class="text-end">Diferencia</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            @foreach ($v['desvios'] as $d)
                                                                <tr>
                                                                    <td>
                                                                        @can('ventas.ver')
                                                                            <a href="{{ route('ventas.show', $d['venta_id']) }}" class="font-monospace">{{ $d['codigo'] }}</a>
                                                                        @else
                                                                            <span class="font-monospace">{{ $d['codigo'] }}</span>
                                                                        @endcan
                                                                    </td>
                                                                    <td class="text-muted">{{ \Illuminate\Support\Carbon::parse($d['fecha'])->format('d/m H:i') }}</td>
                                                                    <td>{{ $d['producto'] }}</td>
                                                                    <td class="text-end tabular text-muted">{{ $bs($d['lista']) }}</td>
                                                                    <td class="text-end tabular">{{ $bs($d['cobrado']) }}</td>
                                                                    <td @class(['text-end tabular fw-semibold', 'vendedores-descuento' => $d['diferencia'] < 0, 'vendedores-sobreprecio' => $d['diferencia'] > 0])>
                                                                        {{ $d['diferencia'] > 0 ? '+' : '' }}{{ $bs($d['diferencia']) }}
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            @endif
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
