{{--
    Un grupo de marca del Stock Actual. Recibe $grupo (array) con marca|null,
    productos y resumen; $clave (string) y $colapsadas (array) para el colapso.
--}}
<section class="stock-grupo-marca {{ ($colapsadas[$clave] ?? false) ? 'is-collapsed' : '' }}">
    <header class="stock-grupo-cabecera">
        <div class="d-flex flex-wrap align-items-center gap-2 w-100">
            <button type="button" class="btn btn-sm btn-icon rounded-circle btn-ghost-secondary stock-grupo-toggle"
                wire:click="toggleGrupo('{{ $clave }}')"
                title="{{ ($colapsadas[$clave] ?? false) ? 'Expandir grupo' : 'Contraer grupo' }}"
                aria-label="{{ ($colapsadas[$clave] ?? false) ? 'Expandir' : 'Contraer' }} {{ $grupo['marca']?->nombre ?? 'Sin marca' }}">
                <i class="ri-arrow-down-s-line fs-18"></i>
            </button>

            <span class="stock-marca-logo avatar-sm flex-shrink-0">
                @if ($grupo['marca'] && $grupo['marca']->logo_ruta)
                    <img src="{{ asset('storage/'.$grupo['marca']->logo_ruta) }}" alt="Logo de {{ $grupo['marca']->nombre }}"
                        class="img-fluid object-fit-contain w-100 h-100" loading="lazy">
                @else
                    <span class="avatar-title">
                        <i class="ri-trademark-line"></i>
                    </span>
                @endif
            </span>

            <h6 class="mb-0 flex-grow-1 text-truncate">{{ $grupo['marca']?->nombre ?? 'Sin marca' }}</h6>

            <span class="stock-chip" title="Productos de esta marca">
                <i class="ri-price-tag-3-line"></i>{{ $grupo['resumen']['productos'] }}
            </span>
            <span class="stock-chip stock-chip-unidades" title="Unidades disponibles">
                <i class="ri-archive-2-line"></i>{{ $grupo['resumen']['unidades'] }}
            </span>
            <span class="stock-chip stock-chip-valor" title="Valor en stock (precio de venta)">
                <i class="ri-wallet-2-line"></i>Bs {{ number_format($grupo['resumen']['valor'], 2, ',', '.') }}
            </span>
        </div>

        @if (! ($colapsadas[$clave] ?? false) && $grupo['resumen']['productos'] > 0)
            @php
                $totalSalud = $grupo['resumen']['productos'];
                $sanos = max(0, $totalSalud - $grupo['resumen']['agotados'] - $grupo['resumen']['bajoMinimo']);
                $pctSanos = (int) round($sanos / $totalSalud * 100);
                $pctBajo = (int) round($grupo['resumen']['bajoMinimo'] / $totalSalud * 100);
                $pctAgotados = (int) round($grupo['resumen']['agotados'] / $totalSalud * 100);
            @endphp
            <div class="stock-salud">
                <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center">
                    <small style="color: var(--marca-apagado);">Salud del stock</small>
                    <small class="stock-salud-leyenda">
                        <span style="color: var(--marca-azul-texto);"><i class="ri-checkbox-blank-circle-fill fs-10 align-middle me-1"></i>{{ $sanos }} sanos</span>
                        <span class="ms-2" style="color: #c98500;"><i class="ri-checkbox-blank-circle-fill fs-10 align-middle me-1"></i>{{ $grupo['resumen']['bajoMinimo'] }} bajo mínimo</span>
                        <span class="ms-2" style="color: #e34948;"><i class="ri-checkbox-blank-circle-fill fs-10 align-middle me-1"></i>{{ $grupo['resumen']['agotados'] }} agotados</span>
                    </small>
                </div>
                <div class="d-flex stock-salud-barra" role="img"
                    aria-label="{{ $sanos }} sanos, {{ $grupo['resumen']['bajoMinimo'] }} bajo mínimo, {{ $grupo['resumen']['agotados'] }} agotados">
                    <span class="bg-success" style="width: {{ $pctSanos }}%"></span>
                    <span class="bg-warning" style="width: {{ $pctBajo }}%"></span>
                    <span class="bg-danger" style="width: {{ $pctAgotados }}%"></span>
                </div>
            </div>
        @endif
    </header>

    @unless ($colapsadas[$clave] ?? false)
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0 stock-tabla">
                <thead>
                    <tr class="text-uppercase">
                        <th scope="col" class="ps-3">Producto</th>
                        <th scope="col">Categoría</th>
                        <th scope="col" class="text-center">Disponibles</th>
                        <th scope="col" class="text-center">Mínimo</th>
                        <th scope="col" class="text-end">Precio venta</th>
                        <th scope="col" class="text-end pe-3">Valor en stock</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grupo['productos'] as $producto)
                        @php
                            $disponibles = (int) $producto->disponibles;
                            $tono = match (true) {
                                $disponibles === 0 => 'danger',
                                $disponibles <= $producto->stock_minimo => 'warning',
                                default => 'success',
                            };
                            $etiqueta = match (true) {
                                $disponibles === 0 => 'Agotado',
                                $disponibles <= $producto->stock_minimo => 'Bajo mínimo',
                                default => 'En stock',
                            };
                            $claseEstado = match ($tono) {
                                'success' => 'stock-estado--ok',
                                'warning' => 'stock-estado--alerta',
                                'danger' => 'stock-estado--peligro',
                            };
                        @endphp
                        <tr class="stock-producto-fila">
                            <td class="ps-3">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="stock-miniatura flex-shrink-0">
                                        @if ($producto->imagen)
                                            <img src="{{ asset('storage/'.$producto->imagen) }}" alt="" loading="lazy">
                                        @else
                                            <i class="ri-image-line"></i>
                                        @endif
                                    </span>
                                    <span class="min-w-0">
                                        @can('unidades.ver')
                                            <button type="button" class="stock-producto-nombre"
                                                wire:click="verUnidades({{ $producto->id }})"
                                                title="Ver unidades de {{ $producto->nombre }} en inventario"
                                                aria-label="Ver unidades de {{ $producto->nombre }}">
                                                <span class="fw-medium text-truncate d-block">{{ $producto->nombre }}</span>
                                                <i class="ri-box-3-line stock-producto-nombre-icono" aria-hidden="true"></i>
                                            </button>
                                        @else
                                            <span class="fw-medium text-truncate d-block">{{ $producto->nombre }}</span>
                                        @endcan
                                        <small class="d-block stock-text-muted">
                                            @if ($producto->modelo)
                                                {{ $producto->modelo }}
                                            @endif
                                        </small>
                                    </span>
                                </div>
                            </td>
                            <td>
                                @if ($producto->categoria)
                                    <span class="stock-text-muted">
                                        <i class="ri-folder-line align-middle me-1"></i>{{ $producto->categoria->nombre }}
                                    </span>
                                @else
                                    <span class="stock-text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <span class="stock-estado {{ $claseEstado }}"
                                    title="{{ $disponibles }} {{ $disponibles === 1 ? 'unidad en stock' : 'unidades en stock' }}@if ($producto->stock_minimo > 0) · mínimo {{ $producto->stock_minimo }}@endif">
                                    <i class="ri-archive-2-line align-middle me-1"></i>{{ $disponibles }}
                                </span>
                                <small class="d-block fs-11 fw-medium" style="color: {{ $tono === 'success' ? 'var(--marca-azul)' : ($tono === 'warning' ? '#c98500' : '#e34948') }};">{{ $etiqueta }}</small>
                            </td>
                                <td class="text-center stock-text-muted">{{ $producto->stock_minimo }}</td>
                            <td class="text-end">Bs {{ number_format((float) $producto->precio_venta, 2, ',', '.') }}</td>
                            <td class="text-end pe-3 fw-medium">
                                Bs {{ number_format((float) $producto->precio_venta * $disponibles, 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endunless
</section>
