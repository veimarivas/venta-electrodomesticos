<div class="items-modulo reportes-modulo">

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-bar-chart-box-line me-1"></i> Análisis · Reportes
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title bg-white bg-opacity-25 text-white rounded-3 fs-3">
                                    <i class="ri-line-chart-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Reportes</h4>
                                <p class="text-white-50 mb-0">{{ $this->etiquetaPeriodo }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="d-flex flex-wrap justify-content-lg-end align-items-center gap-2">
                            <span class="badge bg-white bg-opacity-25 text-white fs-13 px-3 py-2 reportes-vivo">
                                <span class="reportes-latido"></span> En vivo
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Filtros ===================== --}}
    {{-- Los controles del período van en UNA fila, encima de todas las
         gráficas: son estándar de la interfaz, no marcas de la gráfica. --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center">
                <div class="col-lg-6">
                    <div class="btn-group flex-wrap filtro-estado" role="group" aria-label="Período">
                        @foreach (['hoy' => 'Hoy', 'semana' => 'Esta semana', 'mes' => 'Este mes', 'anio' => 'Este año'] as $valor => $etiqueta)
                            <button type="button"
                                class="btn btn-sm {{ $periodo === $valor ? 'btn-primary' : 'btn-soft-secondary' }}"
                                wire:click="aplicarPeriodo('{{ $valor }}')">
                                {{ $etiqueta }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="d-flex flex-wrap align-items-center gap-2 justify-content-lg-end">
                        <span class="text-muted fs-13">Rango propio:</span>
                        <input type="date" class="form-control form-control-sm" style="max-width: 9.5rem"
                            wire:model.live="desde" aria-label="Desde">
                        <span class="text-muted">—</span>
                        <input type="date" class="form-control form-control-sm" style="max-width: 9.5rem"
                            wire:model.live="hasta" aria-label="Hasta">
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php $r = $this->resumen; @endphp

    <div class="row g-4 mb-4">
        {{-- ===================== Cifra protagonista + evolución ===================== --}}
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="row g-4 align-items-start">
                        <div class="col-md-5">
                            {{-- El número con el que se lidera no es una gráfica
                                 de una barra: es un número grande. --}}
                            <x-viz.cifra etiqueta="Ingresos del período"
                                :valor="'Bs '.number_format($r['ingreso'], 2, ',', '.')"
                                :nota="$r['ventas'].' '.($r['ventas'] === 1 ? 'venta' : 'ventas').' · ticket promedio Bs '.number_format($r['ticket'], 2, ',', '.')" />

                            <div class="reportes-resumen mt-4">
                                <div class="reportes-resumen-dato">
                                    <span>Aparatos vendidos</span>
                                    <strong>{{ $r['unidades'] }}</strong>
                                </div>
                                @if ($puedeVerCostos)
                                    <div class="reportes-resumen-dato">
                                        <span>Ganancia</span>
                                        <strong class="text-success">
                                            Bs {{ number_format($r['ganancia'], 2, ',', '.') }}
                                        </strong>
                                    </div>
                                    <div class="reportes-resumen-dato">
                                        <span>Margen</span>
                                        <strong>{{ number_format($r['margen'], 1, ',', '.') }} %</strong>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="d-flex align-items-baseline justify-content-between mb-2">
                                <h6 class="mb-0">Evolución diaria</h6>
                                <small class="text-muted fs-12">Ingresos por día</small>
                            </div>

                            @php
                                $datosSerie = $this->serie->map(fn ($d) => [
                                    'etiqueta' => $d['etiqueta'],
                                    'valor' => $d['ingreso'],
                                    'ventas' => $d['ventas'].' '.($d['ventas'] === 1 ? 'venta' : 'ventas'),
                                ])->all();
                            @endphp
                            <div class="reportes-chart-container chart-serie {{ empty($datosSerie) ? 'vacio' : '' }}">
                                <canvas id="chart-serie-tiempo" data-colors='["--vz-success"]'></canvas>
                                @if (empty($datosSerie))
                                    <div class="reportes-chart-vacio">
                                        <i class="ri-line-chart-line d-block"></i>
                                        Sin datos en este período.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Panel en vivo ===================== --}}
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent py-3">
                    <h5 class="card-title mb-0">
                        <span class="reportes-latido me-1"></span> Ventas en vivo
                    </h5>
                    <small class="text-muted fs-13">Llegan solas, sin recargar</small>
                </div>
                <div class="card-body">
                    @forelse ($enVivo as $venta)
                        <div class="reportes-evento" wire:key="vivo-{{ $venta['id'] }}">
                            <span class="reportes-evento-punto"><i class="ri-shopping-bag-3-line"></i></span>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex justify-content-between gap-2">
                                    <span class="fw-semibold font-monospace fs-13">{{ $venta['codigo'] }}</span>
                                    <span class="fw-semibold text-success">
                                        Bs {{ number_format($venta['total'], 2, ',', '.') }}
                                    </span>
                                </div>
                                <small class="text-muted d-block text-truncate">
                                    {{ $venta['unidades'] }}
                                    {{ $venta['unidades'] === 1 ? 'aparato' : 'aparatos' }}
                                    · {{ $venta['cliente'] }}
                                </small>
                                <small class="text-muted">{{ $venta['hora'] }} · {{ $venta['vendedor'] }}</small>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-5">
                            <i class="ri-radar-line fs-1 d-block mb-2 opacity-50"></i>
                            Esperando ventas...
                            <div class="fs-12 mt-1">Las que se registren aparecerán aquí al instante.</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- ===================== Top de productos ===================== --}}
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent py-3">
                    <h5 class="card-title mb-0"><i class="ri-trophy-line align-bottom me-1"></i> Productos más vendidos</h5>
                    <small class="text-muted fs-13">Por ingreso del período</small>
                </div>
                <div class="card-body">
                    @php $datosTop = $this->topProductos->all(); @endphp
                    <div class="reportes-chart-container chart-barras {{ empty($datosTop) ? 'vacio' : '' }}">
                        <canvas id="chart-top-productos" data-colors='["--vz-primary"]'></canvas>
                        @if (empty($datosTop))
                            <div class="reportes-chart-vacio">
                                <i class="ri-bar-chart-box-line d-block"></i>
                                Sin datos en este período.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Ventas por vendedor ===================== --}}
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent py-3">
                    <h5 class="card-title mb-0"><i class="ri-user-star-line align-bottom me-1"></i> Ventas por vendedor</h5>
                </div>
                <div class="card-body">
                    @php $datosVendedor = $this->porVendedor->all(); @endphp
                    <div class="reportes-chart-container chart-barras {{ empty($datosVendedor) ? 'vacio' : '' }}">
                        <canvas id="chart-por-vendedor" data-colors='["--vz-success"]'></canvas>
                        @if (empty($datosVendedor))
                            <div class="reportes-chart-vacio">
                                <i class="ri-bar-chart-box-line d-block"></i>
                                Sin ventas en este período.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Cómo se cobró ===================== --}}
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-transparent py-3">
                    <h5 class="card-title mb-0"><i class="ri-wallet-3-line align-bottom me-1"></i> Cómo se cobró</h5>
                    <small class="text-muted fs-13">Reparto del ingreso por método de pago</small>
                </div>
                <div class="card-body">
                    @php
                        $datosMetodo = $this->porMetodoPago->map(fn ($m) => [
                            'nombre' => $metodosPago[$m->metodo_pago] ?? $m->metodo_pago,
                            'ingreso' => (float) $m->ingreso,
                        ])->all();
                    @endphp
                    <div class="reportes-chart-container chart-doughnut {{ empty($datosMetodo) ? 'vacio' : '' }}">
                        <canvas id="chart-por-metodo" data-colors='["--vz-primary", "--vz-success", "--vz-warning", "--vz-danger", "--vz-info"]'></canvas>
                        @if (empty($datosMetodo))
                            <div class="reportes-chart-vacio">
                                <i class="ri-wallet-3-line d-block"></i>
                                Sin cobros en este período.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Rentabilidad por proveedor ===================== --}}
        @if ($puedeVerCostos)
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent py-3">
                        <h5 class="card-title mb-0"><i class="ri-truck-line align-bottom me-1"></i> Rentabilidad por proveedor</h5>
                        <small class="text-muted fs-13">
                            Histórico completo, no del período: una compra se recupera a lo largo de meses.
                        </small>
                    </div>
                    <div class="card-body">
                        @php $datosProveedor = $this->porProveedor->all(); @endphp
                        <div class="reportes-chart-container chart-proveedor {{ empty($datosProveedor) ? 'vacio' : '' }}">
                            <canvas id="chart-por-proveedor" data-colors='["--vz-success", "--vz-warning", "--vz-danger"]'></canvas>
                            @if (empty($datosProveedor))
                                <div class="reportes-chart-vacio">
                                    <i class="ri-truck-line d-block"></i>
                                    Todavía no hay compras recepcionadas.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @script
    <script>
        const Rc = window.ReportesCharts;
        Rc.initSerieTiempo('chart-serie-tiempo', @js(
            $this->serie->map(fn ($d) => [
                'etiqueta' => $d['etiqueta'],
                'valor' => $d['ingreso'],
                'ventas' => $d['ventas'].' '.($d['ventas'] === 1 ? 'venta' : 'ventas'),
            ])->all()
        ));
    </script>
    @endscript

    @script
    <script>
        const Rc = window.ReportesCharts;
        Rc.initTopProductos('chart-top-productos', @js($this->topProductos->all()));
    </script>
    @endscript

    @script
    <script>
        const Rc = window.ReportesCharts;
        Rc.initPorVendedor('chart-por-vendedor', @js($this->porVendedor->all()));
    </script>
    @endscript

    @script
    <script>
        const Rc = window.ReportesCharts;
        Rc.initPorMetodoPago('chart-por-metodo', @js(
            $this->porMetodoPago->map(fn ($m) => [
                'nombre' => $metodosPago[$m->metodo_pago] ?? $m->metodo_pago,
                'ingreso' => (float) $m->ingreso,
            ])->all()
        ));
    </script>
    @endscript

    @if ($puedeVerCostos)
    @script
    <script>
        const Rc = window.ReportesCharts;
        Rc.initPorProveedor('chart-por-proveedor', @js($this->porProveedor->all()));
    </script>
    @endscript
    @endif
</div>
