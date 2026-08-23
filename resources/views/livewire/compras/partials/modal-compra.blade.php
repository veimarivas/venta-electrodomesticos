{{-- Registro completo de la compra en una sola pantalla: proveedor, total
     pagado y el detalle por producto. Al guardar se crea la compra y se
     generan sus unidades físicas en el mismo movimiento. --}}
<div class="modal fade" id="modalCompra" tabindex="-1" aria-hidden="true" wire:ignore.self data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl modal-crud-dialog">
        <div class="modal-content border-0 modal-crud-content">
            <div class="modal-header modal-crud-header p-4">
                <div class="modal-crud-header-glow" aria-hidden="true"></div>
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title modal-crud-icon rounded-circle fs-4">
                            <i class="ri-shopping-bag-3-line"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0">Nueva compra</h5>
                        <small class="text-muted">
                            Al registrarla se crean solas las unidades de cada producto en el inventario.
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form wire:submit="guardar" autocomplete="off">
                <div class="modal-body modal-crud-body p-4">

                    {{-- ---------- 1. Datos de la compra ---------- --}}
                    <h6 class="crud-section-title mb-3"><i class="ri-truck-line"></i> Datos de la compra</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="c-supplier" class="form-label">Proveedor <span class="text-danger">*</span></label>
                            @if ($this->proveedorForzado)
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-truck-line"></i></span>
                                    <input type="text" id="c-supplier"
                                        value="{{ $this->proveedores->firstWhere('id', $this->proveedorForzado)?->nombre ?? '' }}"
                                        class="form-control border-start-0 bg-light" disabled>
                                    <input type="hidden" wire:model.live="proveedor_id">
                                </div>
                                <small class="text-muted"><i class="ri-lock-line align-bottom"></i> Viene de la ficha del proveedor.</small>
                            @else
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i
                                            class="ri-truck-line"></i></span>
                                    <select id="c-supplier" wire:model.live="proveedor_id"
                                        class="form-select border-start-0 @error('proveedor_id') is-invalid @elseif ($proveedor_id !== '') is-valid @enderror">
                                        <option value="">Selecciona un proveedor</option>
                                        @foreach ($this->proveedores as $proveedor)
                                            <option value="{{ $proveedor->id }}">{{ $proveedor->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('proveedor_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror

                                @if ($this->proveedores->isEmpty())
                                    <small class="text-danger d-block mt-1">
                                        <i class="ri-error-warning-line align-bottom"></i>
                                        No hay proveedores activos.
                                        <a href="{{ route('proveedores.index') }}" class="fw-semibold">Registra uno primero</a>.
                                    </small>
                                @endif
                            @endif
                        </div>

                        <div class="col-md-2">
                            <label for="c-fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i
                                        class="ri-calendar-line"></i></span>
                                <input type="date" id="c-fecha" wire:model.live="fecha_compra"
                                    max="{{ now()->format('Y-m-d') }}"
                                    class="form-control border-start-0 @error('fecha_compra') is-invalid @enderror">
                            </div>
                            @error('fecha_compra')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="c-factura" class="form-label">
                                Factura <span class="text-muted fw-normal fs-12">(opcional)</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i
                                        class="ri-file-list-3-line"></i></span>
                                <input type="text" id="c-factura" wire:model.live.debounce.400ms="numero_factura"
                                    class="form-control border-start-0 ps-0 @error('numero_factura') is-invalid @enderror"
                                    placeholder="F-00123" maxlength="60">
                            </div>
                            @error('numero_factura')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label for="c-total" class="form-label">
                                Total pagado <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">Bs</span>
                                <input type="number" step="0.01" min="0.01" id="c-total"
                                    wire:model.live.debounce.500ms="total_pagado"
                                    class="form-control border-start-0 ps-0 @error('total_pagado') is-invalid @enderror"
                                    placeholder="0.00">
                                @error('total_pagado')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-text">Lo que se pagó al proveedor por todo.</div>
                        </div>
                    </div>

                    {{-- ---------- 2. Productos ---------- --}}
                    <h6 class="crud-section-title mb-3 mt-4"><i class="ri-box-3-line"></i> Productos comprados</h6>

                    {{-- Selector en cascada: categoría → marca → producto. --}}
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <select class="form-select form-select-sm" wire:model.live="categoriaLinea">
                                <option value="">Todas las categorías</option>
                                @foreach ($this->categoriasLinea as $opcion)
                                    <option value="{{ $opcion['id'] }}">
                                        {{ str_repeat('— ', $opcion['nivel']) }}{{ $opcion['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <select class="form-select form-select-sm" wire:model.live="marcaLinea"
                                @disabled($this->marcasLinea->isEmpty())>
                                <option value="">
                                    {{ $this->marcasLinea->isEmpty() ? 'Sin marcas' : 'Todas las marcas' }}
                                </option>
                                @foreach ($this->marcasLinea as $marca)
                                    <option value="{{ $marca->id }}">{{ $marca->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <div class="search-box">
                                <input type="text" class="form-control form-control-sm crud-busqueda"
                                    placeholder="Nombre, SKU o modelo..."
                                    wire:model.live.debounce.350ms="buscarProducto">
                                <i class="ri-search-line search-icon"></i>
                            </div>
                        </div>
                    </div>

                    <div class="linea-productos-lista mb-3" wire:loading.class="opacity-50"
                        wire:target="categoriaLinea,marcaLinea,buscarProducto">
                        @forelse ($this->productosDisponibles as $producto)
                            <button type="button" class="linea-producto-opcion"
                                wire:key="opcion-producto-{{ $producto->id }}"
                                wire:click="agregarLinea({{ $producto->id }})">
                                <span class="linea-producto-imagen flex-shrink-0">
                                    @if ($producto->imagen)
                                        <img src="{{ asset('storage/'.$producto->imagen) }}" alt="">
                                    @else
                                        <span class="avatar-title"><i class="ri-image-line"></i></span>
                                    @endif
                                </span>

                                <span class="min-w-0 flex-grow-1 text-start">
                                    <span class="d-block fw-semibold text-truncate">{{ $producto->nombre }}</span>
                                    <span class="d-block text-muted fs-12 text-truncate">
                                        {{ $producto->sku }}
                                        @if ($producto->marca) · {{ $producto->marca->nombre }} @endif
                                        @if ($producto->categoria) · {{ $producto->categoria->nombre }} @endif
                                    </span>
                                </span>

                                <span class="text-end flex-shrink-0">
                                    <span class="badge fs-11 {{ $producto->unidades_disponibles > 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        Stock: {{ $producto->unidades_disponibles }}
                                    </span>
                                </span>

                                <span class="text-primary flex-shrink-0"><i class="ri-add-circle-line fs-18"></i></span>
                            </button>
                        @empty
                            <div class="text-center text-muted py-4">
                                <i class="ri-search-eye-line fs-3 d-block mb-2"></i>
                                @if ($buscarProducto !== '' || $categoriaLinea || $marcaLinea)
                                    Ningún producto coincide con los filtros.
                                @else
                                    No quedan productos por agregar.
                                @endif
                            </div>
                        @endforelse
                    </div>

                    {{-- ---------- 3. Detalle capturado ---------- --}}
                    @if ($lineas === [])
                        <div class="compra-lineas-vacio">
                            <i class="ri-shopping-cart-line fs-3 d-block mb-2"></i>
                            Elige arriba los productos de la factura.
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 tabla-lineas-compra">
                                <thead>
                                    <tr class="text-uppercase fs-11 text-muted">
                                        <th scope="col">Producto</th>
                                        <th scope="col" style="width: 7rem">Unidades</th>
                                        <th scope="col" style="width: 10rem">Pagado</th>
                                        <th scope="col" class="text-end" style="width: 9rem">Costo unitario</th>
                                        <th scope="col" class="text-end" style="width: 9rem">Precio venta</th>
                                        <th scope="col" style="width: 3rem"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lineas as $indice => $linea)
                                        @php
                                            $p = $this->productosDeLineas[$linea['producto_id']] ?? null;
                                            $cant = (int) ($linea['cantidad'] ?? 0);
                                            $pagado = is_numeric($linea['costo_total'] ?? '') ? (float) $linea['costo_total'] : 0;
                                        @endphp
                                        <tr wire:key="linea-{{ $indice }}-{{ $linea['producto_id'] }}">
                                            <td>
                                                <div class="fw-semibold text-truncate">{{ $p?->nombre ?? 'Producto' }}</div>
                                                <small class="text-muted">
                                                    {{ $p?->sku }}
                                                    @if ($p?->marca) · {{ $p->marca->nombre }} @endif
                                                </small>
                                            </td>

                                            <td>
                                                <input type="number" min="1" step="1"
                                                    wire:model.live.debounce.400ms="lineas.{{ $indice }}.cantidad"
                                                    class="form-control form-control-sm @error('lineas.'.$indice.'.cantidad') is-invalid @enderror"
                                                    aria-label="Unidades de {{ $p?->nombre }}">
                                            </td>

                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-light">Bs</span>
                                                    <input type="number" step="0.01" min="0.01"
                                                        wire:model.live.debounce.500ms="lineas.{{ $indice }}.costo_total"
                                                        class="form-control @error('lineas.'.$indice.'.costo_total') is-invalid @enderror"
                                                        placeholder="0.00"
                                                        aria-label="Total pagado por {{ $p?->nombre }}">
                                                </div>
                                            </td>

                                            <td class="text-end font-monospace fs-13">
                                                {{-- Calculado: lo pagado ÷ unidades. No se teclea. --}}
                                                @if ($cant > 0 && $pagado > 0)
                                                    Bs {{ number_format($pagado / $cant, 2, ',', '.') }}
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>

                                            <td class="text-end font-monospace fs-13 text-muted">
                                                {{-- Del catálogo (productos.precio_venta). --}}
                                                Bs {{ number_format((float) ($p?->precio_venta ?? 0), 2, ',', '.') }}
                                            </td>

                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-ghost-danger btn-icon"
                                                    wire:click="quitarLinea({{ $indice }})"
                                                    title="Quitar de la compra"
                                                    aria-label="Quitar {{ $p?->nombre }}">
                                                    <i class="ri-close-line"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- ---------- 4. Cuadre en vivo ---------- --}}
                    @php
                        $asignado = $this->asignadoEnCentavos / 100;
                        $pagadoTotal = $this->pagadoEnCentavos / 100;
                        $saldo = $this->saldoEnCentavos / 100;
                    @endphp

                    <div class="compra-cuadre mt-3 {{ $this->cuadra ? 'compra-cuadre-ok' : ($this->saldoEnCentavos < 0 ? 'compra-cuadre-error' : '') }}">
                        <div class="compra-cuadre-dato">
                            <span>Total pagado</span>
                            <strong>Bs {{ number_format($pagadoTotal, 2, ',', '.') }}</strong>
                        </div>
                        <div class="compra-cuadre-dato">
                            <span>Asignado a productos</span>
                            <strong>Bs {{ number_format($asignado, 2, ',', '.') }}</strong>
                        </div>
                        <div class="compra-cuadre-dato compra-cuadre-saldo">
                            <span>
                                @if ($this->saldoEnCentavos < 0)
                                    Excedido en
                                @else
                                    Falta por asignar
                                @endif
                            </span>
                            <strong>Bs {{ number_format(abs($saldo), 2, ',', '.') }}</strong>
                        </div>
                        <div class="compra-cuadre-estado">
                            @if ($this->cuadra)
                                <i class="ri-checkbox-circle-fill"></i> El detalle cuadra
                            @elseif ($this->saldoEnCentavos < 0)
                                <i class="ri-error-warning-fill"></i> Lo asignado supera el total pagado
                            @else
                                <i class="ri-information-line"></i> El detalle debe sumar el total pagado
                            @endif
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-12">
                            <label for="c-notas" class="form-label">
                                Notas <span class="text-muted fw-normal fs-12">(opcional)</span>
                            </label>
                            <textarea id="c-notas" rows="2" wire:model.live.debounce.400ms="notas"
                                class="form-control @error('notas') is-invalid @enderror"
                                placeholder="Condiciones, plazo de entrega, observaciones..."></textarea>
                            @error('notas')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <div class="crud-codigo-preview d-flex align-items-center gap-3 p-3">
                                <div class="avatar-sm flex-shrink-0">
                                    <span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-4">
                                        <i class="ri-hashtag"></i>
                                    </span>
                                </div>
                                <div>
                                    <small class="text-muted d-block">Código asignado automáticamente</small>
                                    <h5 class="mb-0 font-monospace">{{ $this->codigoPrevisto }}</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer modal-crud-footer p-4">
                    <div class="d-flex align-items-center justify-content-between w-100 gap-3 flex-wrap">
                        <small class="{{ $this->compraValida ? 'modal-pista-ok' : 'modal-pista-guardar' }}">
                            @if ($this->compraValida)
                                <i class="ri-checkbox-circle-fill align-bottom me-1"></i>
                                Se generarán
                                {{ collect($lineas)->sum(fn ($l) => (int) ($l['cantidad'] ?? 0)) }}
                                unidades en el inventario
                            @else
                                <i class="ri-information-line align-bottom me-1"></i>
                                Completa los datos y cuadra el detalle con el total pagado
                            @endif
                        </small>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success modal-guardar" @disabled(! $this->compraValida)
                                wire:loading.attr="disabled" wire:target="guardar">
                                <span wire:loading.remove wire:target="guardar">
                                    <i class="ri-save-line align-bottom me-1"></i> Registrar compra
                                </span>
                                <span wire:loading wire:target="guardar">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Registrando y generando unidades...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
