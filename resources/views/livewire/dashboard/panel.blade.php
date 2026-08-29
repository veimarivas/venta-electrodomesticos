<div class="reportes-modulo dashboard-modulo">

    {{-- ===================== Hero / Saludo ===================== --}}
    <div class="dash-hero mb-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="min-w-0">
                <h1 class="dash-hero-saludo">Hola, {{ Str::before(auth()->user()->name, ' ') }}</h1>
                <p class="dash-hero-fecha">{{ ucfirst(now()->translatedFormat('l d \d\e F')) }} · así va la tienda.</p>
            </div>

            <div class="dash-hero-acciones">
                <span class="dash-hero-badge">
                    <span class="dash-latido"></span> En vivo
                </span>
                @can('ventas.crear')
                    <a href="{{ route('ventas.create') }}" class="dash-hero-btn">
                        <i class="ri-shopping-cart-2-line"></i> Nueva venta
                    </a>
                @endcan
            </div>
        </div>
    </div>

    @php
        $hoy = $this->hoy;
        $semana = $this->semana;
        $mes = $this->mes;
    @endphp

    {{--
        Todo lo que sigue son importes, y van tras `reportes.ver` igual que su
        equivalente en la API (GET /api/v1/dashboard/*). Sin esa comprobación,
        un vendedor -que NO tiene ese permiso- veía la caja del día en el panel
        aunque la app se la negara: la misma cuenta enseñaba cosas distintas
        según por dónde entrase.

        No se corta el acceso al dashboard entero, que es la pantalla de
        aterrizaje: quien no puede ver reportes sigue viendo el almacén y el
        stock bajo, que sí es información suya.
    --}}
    @if ($puedeVerReportes)

    {{-- ===================== KPIs ===================== --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card dash-kpi h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="min-w-0">
                            <span class="dash-kpi-label">Ventas de hoy</span>
                            <span class="dash-kpi-valor">Bs {{ number_format($hoy['ingreso'], 2, ',', '.') }}</span>
                            <span class="dash-kpi-nota">{{ $hoy['ventas'] }} {{ $hoy['ventas'] === 1 ? 'venta' : 'ventas' }}</span>
                        </div>
                        <span class="dash-kpi-icono dash-kpi-icono--ingreso"><i class="ri-money-dollar-circle-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card dash-kpi h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="min-w-0">
                            <span class="dash-kpi-label">Esta semana</span>
                            <span class="dash-kpi-valor">Bs {{ number_format($semana['ingreso'], 2, ',', '.') }}</span>
                            <span class="dash-kpi-nota">{{ $semana['ventas'] }} {{ $semana['ventas'] === 1 ? 'venta' : 'ventas' }}</span>
                        </div>
                        <span class="dash-kpi-icono dash-kpi-icono--ventas"><i class="ri-calendar-check-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card dash-kpi h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="min-w-0">
                            <span class="dash-kpi-label">Este mes</span>
                            <span class="dash-kpi-valor">Bs {{ number_format($mes['ingreso'], 2, ',', '.') }}</span>
                            <span class="dash-kpi-nota">{{ $mes['ventas'] }} {{ $mes['ventas'] === 1 ? 'venta' : 'ventas' }}</span>
                        </div>
                        <span class="dash-kpi-icono dash-kpi-icono--unidades"><i class="ri-bar-chart-grouped-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
        @if ($puedeVerCostos)
            <div class="col-sm-6 col-xl-3">
                <div class="card dash-kpi h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="min-w-0">
                                <span class="dash-kpi-label">Ganancia del mes</span>
                                <span class="dash-kpi-valor" style="color: #1baf7a;">Bs {{ number_format($mes['ganancia'], 2, ',', '.') }}</span>
                                <span class="dash-kpi-nota">Margen neto</span>
                            </div>
                            <span class="dash-kpi-icono dash-kpi-icono--ganancia"><i class="ri-line-chart-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Ticket promedio y margen ===================== --}}
    {{--
        Van en su propia fila y no entre los KPI de arriba: aquellos son
        ACUMULADOS —cuánto entró— y estos dos son RATIOS —cómo de bien entró—.
        Mezclarlos haría leer «Bs 45.000» y «Bs 1.250» como cifras del mismo
        tipo, cuando una es la caja del mes y la otra lo que deja una venta.
    --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card dash-kpi h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="min-w-0">
                            <span class="dash-kpi-label">Ticket promedio · mes</span>
                            <span class="dash-kpi-valor">Bs {{ number_format($mes['ticket'], 2, ',', '.') }}</span>
                            <span class="dash-kpi-nota">
                                {{-- Sin ventas no hay promedio que dar: enseñar «Bs 0,00»
                                     sin decir esto se lee como «se vende a cero». --}}
                                @if ($mes['ventas'] === 0)
                                    Sin ventas todavía este mes
                                @else
                                    Hoy: Bs {{ number_format($hoy['ticket'], 2, ',', '.') }}
                                @endif
                            </span>
                        </div>
                        <span class="dash-kpi-icono dash-kpi-icono--unidades"><i class="ri-receipt-line"></i></span>
                    </div>
                </div>
            </div>
        </div>

        @if ($puedeVerCostos)
            <div class="col-sm-6 col-xl-3">
                <div class="card dash-kpi h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="min-w-0">
                                <span class="dash-kpi-label">Margen · mes</span>
                                <span class="dash-kpi-valor">{{ number_format($mes['margen'], 1, ',', '.') }} %</span>
                                <span class="dash-kpi-nota">
                                    De cada Bs 100 vendidos, quedan
                                    Bs {{ number_format($mes['margen'], 1, ',', '.') }}
                                </span>
                            </div>
                            <span class="dash-kpi-icono dash-kpi-icono--ganancia"><i class="ri-percent-line"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-sm-6 col-xl-3">
            <div class="card dash-kpi h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="min-w-0">
                            <span class="dash-kpi-label">Aparatos vendidos · mes</span>
                            <span class="dash-kpi-valor">{{ number_format($mes['unidades'], 0, ',', '.') }}</span>
                            <span class="dash-kpi-nota">
                                {{ $mes['ventas'] }} {{ $mes['ventas'] === 1 ? 'venta' : 'ventas' }}
                            </span>
                        </div>
                        <span class="dash-kpi-icono dash-kpi-icono--unidades"><i class="ri-archive-2-line"></i></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Cifra del día + evolución ===================== --}}
    @endif

    <div class="row g-4 mb-4">
        @if ($puedeVerReportes)
        <div class="col-xl-8">
            <div class="card dash-card h-100">
                <div class="card-body">
                    <div class="row g-4 align-items-start">
                        <div class="col-md-5">
                            <x-viz.cifra etiqueta="Ventas de hoy"
                                :valor="'Bs '.number_format($hoy['ingreso'], 2, ',', '.')"
                                :nota="$hoy['ventas'].' '.($hoy['ventas'] === 1 ? 'venta' : 'ventas').' · '.$hoy['unidades'].' '.($hoy['unidades'] === 1 ? 'aparato' : 'aparatos')" />

                            <div class="dash-resumen dash-resumen--card">
                                <div class="dash-saldo-item">
                                    <span>Esta semana</span>
                                    <strong>Bs {{ number_format($semana['ingreso'], 2, ',', '.') }}</strong>
                                </div>
                                <div class="dash-saldo-item">
                                    <span>Este mes</span>
                                    <strong>Bs {{ number_format($mes['ingreso'], 2, ',', '.') }}</strong>
                                </div>
                                @if ($puedeVerCostos)
                                    <div class="dash-saldo-item">
                                        <span>Ganancia del mes</span>
                                        <strong style="color: #1baf7a;">
                                            Bs {{ number_format($mes['ganancia'], 2, ',', '.') }}
                                        </strong>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-7">
                            <div class="d-flex align-items-baseline justify-content-between mb-2">
                                <h6 class="mb-0" style="color: #14243d; font-weight: 650;">Últimos 14 días</h6>
                                <small class="text-muted fs-12">Ingresos por día</small>
                            </div>

                            <x-viz.serie-tiempo :puntos="$this->serie->map(fn ($d) => [
                                'etiqueta' => $d['etiqueta'],
                                'valor' => $d['ingreso'],
                                'serie' => $d['ventas'].' '.($d['ventas'] === 1 ? 'venta' : 'ventas'),
                            ])->all()" :alto="170" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @endif

        {{-- ===================== Estado del almacén ===================== --}}
        {{-- Ocupa la fila entera cuando no hay indicadores al lado: media fila
             vacía se lee como que algo no cargó. --}}
        <div class="{{ $puedeVerReportes ? 'col-xl-4' : 'col-12' }}">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <span class="dash-card-header-icono dash-card-header-icono--almacen"><i class="ri-archive-2-line"></i></span>
                        Almacén
                    </h5>
                </div>
                <div class="card-body">
                    @php $inv = $this->inventario; @endphp

                    <ul class="dash-saldo">
                        <li class="dash-saldo-item">
                            <span>Aparatos disponibles</span>
                            <strong>{{ $unidadesEnStock }}</strong>
                        </li>
                        <li class="dash-saldo-item">
                            <span>Valor a precio de venta</span>
                            <strong>Bs {{ number_format($inv['valor'], 2, ',', '.') }}</strong>
                        </li>
                        @if ($puedeVerCostos)
                            <li class="dash-saldo-item">
                                <span>Ganancia potencial</span>
                                <strong style="color: #1baf7a;">
                                    Bs {{ number_format($inv['potencial'], 2, ',', '.') }}
                                </strong>
                            </li>
                        @endif
                    </ul>

                    <h6 class="fs-13 mt-4 mb-2" style="color: #c98500; font-weight: 650;">
                        <i class="ri-alert-line align-bottom me-1"></i> Bajo mínimo
                    </h6>

                    @forelse ($this->bajoMinimo as $producto)
                        <div class="dash-alerta" wire:key="minimo-{{ $producto->id }}">
                            <div class="min-w-0">
                                <div class="dash-alerta-nombre">{{ $producto->nombre }}</div>
                                <small class="dash-alerta-marca">{{ $producto->marca?->nombre ?? $producto->sku }}</small>
                            </div>
                            <span class="dash-alerta-badge {{ $producto->disponibles === 0 ? 'dash-alerta-badge--peligro' : 'dash-alerta-badge--alerta' }}">
                                {{ $producto->disponibles }} / {{ $producto->stock_minimo }}
                            </span>
                        </div>
                    @empty
                        <p class="dash-alerta-ok mb-0">
                            <i class="ri-checkbox-circle-line"></i>
                            Todo el catálogo está por encima de su mínimo.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Últimas ventas + Más vendidos ===================== --}}
    {{--
        Las dos tarjetas llevan importes y cada una va tras SU permiso: la lista
        de ventas tras `ventas.ver`, el ranking tras `reportes.ver`. Antes solo
        estaba condicionado el enlace «Ver todas», así que quien no podía entrar
        al listado veía igualmente los totales de las últimas ventas en el
        panel.

        Cuando una de las dos se oculta, la otra ocupa el ancho entero en vez de
        dejar media fila vacía.
    --}}
    @php
        $columnasDeVentas = $puedeVerVentas && $puedeVerReportes ? 'col-xl-7' : 'col-12';
        $columnasDeTop = $puedeVerVentas && $puedeVerReportes ? 'col-xl-5' : 'col-12';
    @endphp

    <div class="row g-4">
        @if ($puedeVerVentas)
        <div class="{{ $columnasDeVentas }}">
            <div class="card dash-card h-100">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="card-title mb-0">Últimas ventas</h5>
                        <small class="text-muted fs-13">Se actualizan solas al registrarse una</small>
                    </div>
                    @if ($puedeVerVentas)
                        <a href="{{ route('ventas.index') }}" class="dash-ver-todas">
                            Ver todas <i class="ri-arrow-right-line"></i>
                        </a>
                    @endif
                </div>

                <div class="card-body p-0 dash-ventas-lista">
                    @foreach ($enVivo as $venta)
                        <div class="dash-venta esta-nueva" wire:key="vivo-{{ $venta['id'] }}">
                            <span class="dash-venta-icono dash-venta-icono--vivo"><i class="ri-shopping-bag-3-line"></i></span>
                            <div class="min-w-0 flex-grow-1">
                                <div class="dash-venta-codigo">{{ $venta['codigo'] }}</div>
                                <small class="dash-venta-meta">
                                    {{ $venta['hora'] }} · {{ $venta['vendedor'] }} · {{ $venta['cliente'] }}
                                </small>
                            </div>
                            <span class="dash-venta-monto">
                                Bs {{ number_format($venta['total'], 2, ',', '.') }}
                            </span>
                        </div>
                    @endforeach

                    @forelse ($this->ultimasVentas as $venta)
                        <div class="dash-venta" wire:key="venta-{{ $venta->id }}">
                            <span class="dash-venta-icono dash-venta-icono--normal"><i class="ri-receipt-line"></i></span>
                            <div class="min-w-0 flex-grow-1">
                                <div class="dash-venta-codigo">{{ $venta->codigo }}</div>
                                <small class="dash-venta-meta">
                                    {{ $venta->vendida_en->format('d/m H:i') }}
                                    · {{ $venta->user?->name }}
                                    · {{ $venta->cliente?->persona?->nombre_completo ?? 'Público general' }}
                                </small>
                            </div>
                            <span class="dash-venta-monto">
                                Bs {{ number_format((float) $venta->total, 2, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        @if ($enVivo === [])
                            <div class="text-center text-muted py-5">
                                <i class="ri-receipt-line fs-1 d-block mb-2 opacity-50"></i>
                                Todavía no hay ventas registradas.
                            </div>
                        @endif
                    @endforelse
                </div>
            </div>
        </div>

        @endif

        {{-- ===================== Más vendidos del mes ===================== --}}
        @if ($puedeVerReportes)
        <div class="{{ $columnasDeTop }}">
            <div class="card dash-card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <span class="dash-card-header-icono dash-card-header-icono--top"><i class="ri-trophy-line"></i></span>
                        Más vendidos
                    </h5>
                    <small class="text-muted fs-13">{{ ucfirst(now()->translatedFormat('F')) }}, por ingreso</small>
                </div>
                <div class="card-body">
                    <x-viz.barras :filas="$this->topProductos->map(fn ($p) => [
                        'nombre' => $p->nombre,
                        'valor' => (float) $p->ingreso,
                        'meta' => $p->unidades.' '.($p->unidades == 1 ? 'unidad' : 'unidades'),
                    ])->all()" vacio="Sin ventas este mes." />
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
