{{--
    Un grupo de marca del Stock Actual. Recibe $grupo (array) con marca|null,
    productos y resumen; $clave (string) y $colapsadas (array) para el colapso.
--}}
<section class="stock-grupo stock-grupo--marca {{ ($colapsadas[$clave] ?? false) ? 'is-collapsed' : '' }}">
    <header class="stock-grupo-cabecera">
        <div class="stock-grupo-fila">
            <button type="button" class="stock-grupo-toggle"
                wire:click="toggleGrupo('{{ $clave }}')"
                title="{{ ($colapsadas[$clave] ?? false) ? 'Expandir grupo' : 'Contraer grupo' }}"
                aria-expanded="{{ ($colapsadas[$clave] ?? false) ? 'false' : 'true' }}"
                aria-label="{{ ($colapsadas[$clave] ?? false) ? 'Expandir' : 'Contraer' }} {{ $grupo['marca']?->nombre ?? 'Sin marca' }}">
                <i class="ri-arrow-down-s-line" aria-hidden="true"></i>
            </button>

            <span class="stock-marca-logo">
                @if ($grupo['marca'] && $grupo['marca']->logo_ruta)
                    <img src="{{ asset('storage/'.$grupo['marca']->logo_ruta) }}" alt="Logo de {{ $grupo['marca']->nombre }}"
                        class="img-fluid object-fit-contain" loading="lazy">
                @else
                    <i class="ri-trademark-line"></i>
                @endif
            </span>

            <div class="stock-grupo-nombre">
                <h6 class="stock-grupo-titulo">{{ $grupo['marca']?->nombre ?? 'Sin marca' }}</h6>
            </div>

            <div class="stock-grupo-metricas">
                <span class="stock-chip" title="Productos de esta marca">
                    <i class="ri-price-tag-3-line"></i>{{ $grupo['resumen']['productos'] }}
                </span>
                <span class="stock-chip stock-chip-unidades" title="Unidades disponibles">
                    <i class="ri-archive-2-line"></i>{{ number_format($grupo['resumen']['unidades'], 0, ',', '.') }}
                </span>
                <span class="stock-chip stock-chip-valor" title="Valor en stock (precio de venta)">
                    <i class="ri-wallet-2-line"></i>Bs {{ number_format($grupo['resumen']['valor'], 2, ',', '.') }}
                </span>
            </div>
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
                <div class="stock-salud-top">
                    <span class="stock-salud-etiqueta">Salud del stock</span>
                    <span class="stock-salud-pct">{{ $pctSanos }}% en óptimo</span>
                </div>
                <div class="stock-salud-barra" role="img"
                    aria-label="{{ $sanos }} sanos, {{ $grupo['resumen']['bajoMinimo'] }} bajo mínimo, {{ $grupo['resumen']['agotados'] }} agotados">
                    <span class="stock-salud-sanos" style="width: {{ $pctSanos }}%"></span>
                    <span class="stock-salud-bajo" style="width: {{ $pctBajo }}%"></span>
                    <span class="stock-salud-agotado" style="width: {{ $pctAgotados }}%"></span>
                </div>
                <div class="stock-salud-leyenda">
                    <span class="stock-text-ok"><i class="ri-checkbox-blank-circle-fill"></i>{{ $sanos }} sanos</span>
                    <span class="stock-text-warn"><i class="ri-checkbox-blank-circle-fill"></i>{{ $grupo['resumen']['bajoMinimo'] }} bajo mínimo</span>
                    <span class="stock-text-danger"><i class="ri-checkbox-blank-circle-fill"></i>{{ $grupo['resumen']['agotados'] }} agotados</span>
                </div>
            </div>
        @endif
    </header>

    @unless ($colapsadas[$clave] ?? false)
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 stock-tabla">
                <thead>
                    <tr>
                        <th scope="col" class="stock-col-producto">Producto</th>
                        <th scope="col" class="stock-col-secundaria">Categoría</th>
                        <th scope="col" class="text-center stock-col-estado">Estado</th>
                        <th scope="col" class="text-center stock-col-stock">Stock</th>
                        <th scope="col" class="text-end stock-col-valor">Valor en stock</th>
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
                            $icono = match ($tono) {
                                'danger' => 'ri-close-circle-line',
                                'warning' => 'ri-alert-line',
                                default => 'ri-checkbox-circle-line',
                            };
                        @endphp
                        <tr class="stock-producto-fila">
                            <td>
                                <div class="stock-producto">
                                    <span class="stock-miniatura">
                                        @if ($producto->imagen)
                                            <img src="{{ asset('storage/'.$producto->imagen) }}" alt="" loading="lazy">
                                        @else
                                            <i class="ri-image-line"></i>
                                        @endif
                                    </span>
                                    <span class="stock-producto-datos">
                                        @can('unidades.ver')
                                            <button type="button" class="stock-producto-nombre"
                                                wire:click="verUnidades({{ $producto->id }})"
                                                title="Ver unidades de {{ $producto->nombre }} en inventario"
                                                aria-label="Ver unidades de {{ $producto->nombre }} en inventario">
                                                <span class="stock-producto-nombre-texto">{{ $producto->nombre }}</span>
                                                <i class="ri-arrow-right-line stock-producto-nombre-icono" aria-hidden="true"></i>
                                            </button>
                                        @else
                                            <span class="stock-producto-nombre-texto">{{ $producto->nombre }}</span>
                                        @endcan
                                        @if ($producto->modelo)
                                            <small class="stock-producto-modelo">{{ $producto->modelo }}</small>
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td data-label="Categoría">
                                <span class="stock-celda-texto">{{ $producto->categoria?->nombre ?? '—' }}</span>
                            </td>
                            <td class="text-center" data-label="Estado">
                                <span class="stock-estado stock-estado--{{ $tono }}">
                                    <i class="{{ $icono }}"></i>{{ $etiqueta }}
                                </span>
                            </td>
                            <td class="text-center" data-label="Stock">
                                <span class="stock-celda-valor">
                                    <span class="stock-disponibles stock-disponibles--{{ $tono }}"
                                        title="{{ $disponibles }} {{ $disponibles === 1 ? 'unidad en stock' : 'unidades en stock' }}">{{ $disponibles }}</span>
                                    @if ($producto->stock_minimo > 0)
                                        <small class="stock-minimo">mín {{ $producto->stock_minimo }}</small>
                                    @endif
                                </span>
                            </td>
                            <td class="text-end" data-label="Valor en stock">
                                <span class="stock-valor-grupo">
                                    <span class="stock-valor-total">Bs {{ number_format((float) $producto->precio_venta * $disponibles, 2, ',', '.') }}</span>
                                    @if ($disponibles > 0)
                                        <small class="stock-precio-unitario">Bs {{ number_format((float) $producto->precio_venta, 2, ',', '.') }} c/u</small>
                                    @endif
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endunless
</section>
