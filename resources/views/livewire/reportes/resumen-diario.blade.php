@php
    $r = $this->resumen;
    $bs = fn (float $monto): string => 'Bs '.number_format($monto, 2, ',', '.');
    $esHoy = $fecha === now()->toDateString();
    $ing = $r['ingresos'];
    $egr = $r['egresos'];
@endphp

<div class="resumen-modulo">

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-calendar-check-line me-1"></i> Análisis · Resumen del día
                        </span>
                        <h4 class="text-white mb-1">
                            {{ $esHoy ? 'Hoy, ' : '' }}{{ \Illuminate\Support\Carbon::parse($fecha)->translatedFormat('l j \d\e F') }}
                        </h4>
                        <p class="text-white-50 mb-0">
                            Lo que entró y lo que salió, en efectivo y por el banco. No depende de la caja.
                        </p>
                    </div>
                    <div class="col-lg-5">
                        <div class="resumen-neto">
                            <span class="resumen-neto-etiqueta">Neto del día</span>
                            <span @class(['resumen-neto-valor', 'resumen-neto-valor--negativo' => $r['neto'] < 0])>
                                {{ $bs($r['neto']) }}
                            </span>
                        </div>
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
            aria-label="Día del resumen">
        <button type="button" class="btn btn-light btn-icon" wire:click="diaSiguiente" @disabled($esHoy)
            title="Día siguiente" aria-label="Día siguiente">
            <i class="ri-arrow-right-s-line"></i>
        </button>
        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
            <span class="visually-hidden">Cargando...</span>
        </span>
    </div>

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Ingresos" value="{{ $bs($ing['total']) }}" icon="bx-trending-up" color="success"
                caption="{{ $ing['ventas']['cantidad'] }} ventas · {{ $ing['cobranza']['cantidad'] }} cuotas" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Egresos" value="{{ $bs($egr['total']) }}" icon="bx-trending-down" color="danger"
                caption="Proveedores, gastos y retiros" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Efectivo del día" value="{{ $bs($r['efectivo']['neto']) }}" icon="bx-money" color="primary"
                caption="Entró {{ $bs($r['efectivo']['entra']) }} · salió {{ $bs($r['efectivo']['sale']) }}" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Vendido a crédito" value="{{ $bs($ing['ventas']['a_credito']) }}" icon="bx-time-five" color="warning"
                caption="Por cobrar: no es dinero del día" />
        </div>
    </div>

    <div class="row g-4">
        {{-- ===================== Ingresos ===================== --}}
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="ri-arrow-down-circle-line text-success me-1"></i> Ingresos</h5>
                    <strong class="resumen-total resumen-total--ingreso">{{ $bs($ing['total']) }}</strong>
                </div>
                <div class="card-body">
                    <div class="resumen-bloque">
                        <div class="resumen-fila resumen-fila--titulo">
                            <span>Ventas ({{ $ing['ventas']['cantidad'] }})</span>
                            <strong>{{ $bs($ing['ventas']['cobrado']) }}</strong>
                        </div>
                        <div class="resumen-fila"><span>En efectivo</span><span>{{ $bs($ing['ventas']['efectivo']) }}</span></div>
                        <div class="resumen-fila"><span>Por QR</span><span>{{ $bs($ing['ventas']['qr']) }}</span></div>
                        @if ($ing['ventas']['otros'] > 0)
                            <div class="resumen-fila"><span>Otros medios</span><span>{{ $bs($ing['ventas']['otros']) }}</span></div>
                        @endif
                    </div>

                    <div class="resumen-bloque">
                        <div class="resumen-fila resumen-fila--titulo">
                            <span>Cuotas cobradas ({{ $ing['cobranza']['cantidad'] }})</span>
                            <strong>{{ $bs($ing['cobranza']['total']) }}</strong>
                        </div>
                        <div class="resumen-fila"><span>En efectivo</span><span>{{ $bs($ing['cobranza']['efectivo']) }}</span></div>
                        <div class="resumen-fila"><span>QR o transferencia</span><span>{{ $bs($ing['cobranza']['otros']) }}</span></div>
                    </div>

                    <div class="resumen-bloque">
                        <div class="resumen-fila resumen-fila--titulo">
                            <span>Ingresos a la caja</span>
                            <strong>{{ $bs($ing['caja']['total']) }}</strong>
                        </div>
                        @foreach ($ing['caja']['detalle'] as $m)
                            <div class="resumen-fila">
                                <span>{{ $m['hora'] }} · {{ $m['motivo'] }}</span><span>{{ $bs($m['monto']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Egresos ===================== --}}
        <div class="col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="ri-arrow-up-circle-line text-danger me-1"></i> Egresos</h5>
                    <strong class="resumen-total resumen-total--egreso">{{ $bs($egr['total']) }}</strong>
                </div>
                <div class="card-body">
                    <div class="resumen-bloque">
                        <div class="resumen-fila resumen-fila--titulo">
                            <span>Compras pagadas a proveedores</span>
                            <strong>{{ $bs($egr['proveedores']['total']) }}</strong>
                        </div>
                        @forelse ($egr['proveedores']['detalle'] as $p)
                            <div class="resumen-fila">
                                <span>
                                    @can('compras.ver')
                                        <a href="{{ route('compras.show', $p['compra_id']) }}" class="font-monospace">{{ $p['compra'] }}</a>
                                    @else
                                        <span class="font-monospace">{{ $p['compra'] }}</span>
                                    @endcan
                                    · {{ $p['proveedor'] }}
                                </span>
                                <span>{{ $bs($p['monto']) }}</span>
                            </div>
                        @empty
                            <div class="resumen-fila resumen-fila--vacia"><span>Ningún pago a proveedores este día.</span></div>
                        @endforelse
                    </div>

                    <div class="resumen-bloque">
                        <div class="resumen-fila resumen-fila--titulo">
                            <span>Gastos</span>
                            <strong>{{ $bs($egr['gastos']['total']) }}</strong>
                        </div>
                        @forelse ($egr['gastos']['por_categoria'] as $c)
                            <div class="resumen-fila">
                                <span>{{ $c['etiqueta'] }} ({{ $c['cantidad'] }})</span><span>{{ $bs($c['total']) }}</span>
                            </div>
                        @empty
                            <div class="resumen-fila resumen-fila--vacia"><span>Sin gastos anotados.</span></div>
                        @endforelse
                        @if ($egr['gastos']['total'] > 0)
                            <div class="resumen-chips">
                                @foreach (\App\Models\Gasto::METODOS as $clave => $etiqueta)
                                    @if (($egr['gastos']['por_metodo'][$clave] ?? 0) > 0)
                                        <span class="gastos-categoria">{{ $etiqueta }} · {{ $bs($egr['gastos']['por_metodo'][$clave]) }}</span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="resumen-bloque">
                        <div class="resumen-fila resumen-fila--titulo">
                            <span>Retiros de la caja</span>
                            <strong>{{ $bs($egr['caja']['total']) }}</strong>
                        </div>
                        @foreach ($egr['caja']['detalle'] as $m)
                            <div class="resumen-fila">
                                <span>{{ $m['hora'] }} · {{ $m['motivo'] }}</span><span>{{ $bs($m['monto']) }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="resumen-bloque">
                        <div class="resumen-fila resumen-fila--titulo">
                            <span>Devoluciones a clientes ({{ $egr['devoluciones']['cantidad'] }})</span>
                            <strong>{{ $bs($egr['devoluciones']['total']) }}</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Gastos por persona y detalle ===================== --}}
        @if ($egr['gastos']['detalle'] !== [])
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent py-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Gastos del día</h5>
                        <div class="resumen-chips">
                            @foreach ($egr['gastos']['por_persona'] as $p)
                                <span class="gastos-categoria"><i class="ri-user-line"></i> {{ $p['persona'] }} · {{ $bs($p['total']) }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr class="text-uppercase fs-11 text-muted">
                                        <th class="ps-4">Concepto</th>
                                        <th>Categoría</th>
                                        <th>Para quién</th>
                                        <th>Pago</th>
                                        <th class="text-end pe-4">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($egr['gastos']['detalle'] as $g)
                                        <tr>
                                            <td class="ps-4">{{ $g['concepto'] }}</td>
                                            <td>{{ $g['categoria'] }}</td>
                                            <td>{{ $g['persona'] ?? 'La tienda' }}</td>
                                            <td>{{ $g['metodo'] }} @if ($g['de_caja']) <small class="text-muted">· del cajón</small> @endif</td>
                                            <td class="text-end pe-4 fw-semibold">{{ $bs($g['monto']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <p class="text-muted fs-12 mt-3 mb-0">
        <i class="ri-information-line"></i>
        «Efectivo del día» suma las ventas y cuotas cobradas en billetes y los ingresos a la caja, y resta los gastos en
        efectivo y los retiros. Los pagos a proveedores y las devoluciones no dicen con qué se pagaron: cuentan en los egresos,
        no en el efectivo.
    </p>
</div>
