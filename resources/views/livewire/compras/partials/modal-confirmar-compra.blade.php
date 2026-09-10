{{-- Confirmación previa al registro: se ve el detalle completo de la compra
     antes de guardarla. El total y las líneas ya están calculados en el
     formulario; aquí solo se confirma en el último paso. --}}
<div class="modal fade" id="modalConfirmarCompra" tabindex="-1" aria-hidden="true" wire:ignore.self
    data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-crud-dialog modal-confirmar-compra">
        <div class="modal-content border-0 modal-crud-content">
            <div class="modal-header modal-crud-header p-4">
                <div class="modal-crud-header-glow" aria-hidden="true"></div>
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title modal-crud-icon rounded-circle fs-4">
                            <i class="ri-checkbox-circle-line"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0">Confirmar compra</h5>
                        <small>Revisa el detalle antes de registrarla.</small>
                    </div>
                </div>
                <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body modal-crud-body p-4">

                {{-- Resumen de la cabecera --}}
                <div class="confirmar-resumen mb-3">
                    <div class="confirmar-resumen-item">
                        <span class="confirmar-resumen-icono"><i class="ri-truck-line"></i></span>
                        <div class="min-w-0">
                            <span class="confirmar-resumen-etiqueta">Proveedor</span>
                            <strong class="confirmar-resumen-valor text-truncate">
                                {{ $this->proveedores->firstWhere('id', (int) $proveedor_id)?->nombre ?? '—' }}
                            </strong>
                        </div>
                    </div>
                    <div class="confirmar-resumen-item">
                        <span class="confirmar-resumen-icono"><i class="ri-calendar-line"></i></span>
                        <div class="min-w-0">
                            <span class="confirmar-resumen-etiqueta">Fecha</span>
                            <strong class="confirmar-resumen-valor">
                                {{ $fecha_compra !== '' ? \Carbon\Carbon::parse($fecha_compra)->format('d/m/Y') : '—' }}
                            </strong>
                        </div>
                    </div>
                    <div class="confirmar-resumen-item">
                        <span class="confirmar-resumen-icono"><i class="ri-file-list-3-line"></i></span>
                        <div class="min-w-0">
                            <span class="confirmar-resumen-etiqueta">Factura</span>
                            <strong class="confirmar-resumen-valor text-truncate">
                                {{ $numero_factura !== '' ? $numero_factura : '—' }}
                            </strong>
                        </div>
                    </div>
                </div>

                {{-- Líneas capturadas --}}
                <div class="confirmar-lineas">
                    <div class="confirmar-lineas-head">
                        <span class="confirmar-lineas-title">Productos de la compra</span>
                        <span class="confirmar-lineas-count">
                            {{ count($lineas) }} {{ count($lineas) === 1 ? 'producto' : 'productos' }} ·
                            {{ collect($lineas)->sum(fn ($l) => (int) ($l['cantidad'] ?? 0)) }} unidades
                        </span>
                    </div>

                    <div class="confirmar-tabla-wrap">
                        <table class="table align-middle mb-0 confirmar-tabla">
                            <thead>
                                <tr>
                                    <th scope="col" class="confirmar-col-producto">Producto</th>
                                    <th scope="col" class="confirmar-col-unidades text-center">Unds.</th>
                                    <th scope="col" class="confirmar-col-costo text-end">Costo unitario</th>
                                    <th scope="col" class="confirmar-col-total text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($lineas as $linea)
                                    @php
                                        $p = $this->productosDeLineas[$linea['producto_id']] ?? null;
                                        $cant = (int) ($linea['cantidad'] ?? 0);
                                        $costoUnitario = is_numeric($linea['costo_unitario'] ?? '') ? (float) $linea['costo_unitario'] : 0;
                                    @endphp
                                    <tr wire:key="confirmar-{{ $linea['producto_id'] }}">
                                        <td class="confirmar-col-producto">
                                            <div class="confirmar-producto">
                                                <div class="confirmar-producto-thumb">
                                                    @if ($p?->imagen)
                                                        <img src="{{ asset('storage/'.$p->imagen) }}" alt="">
                                                    @else
                                                        <i class="ri-image-line"></i>
                                                    @endif
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="fw-semibold text-truncate">{{ $p?->nombre ?? 'Producto' }}</div>
                                                    @if ($p?->marca)
                                                        <small class="text-muted">{{ $p->marca->nombre }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="confirmar-col-unidades text-center">
                                            <span class="badge confirmar-unidades-badge">{{ $cant }}</span>
                                        </td>
                                        <td class="confirmar-col-costo text-end font-monospace">
                                            Bs {{ number_format($costoUnitario, 2, ',', '.') }}
                                        </td>
                                        <td class="confirmar-col-total text-end font-monospace fw-semibold">
                                            Bs {{ number_format($costoUnitario * $cant, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="confirmar-total">
                        <div>
                            <span class="confirmar-total-etiqueta">Total pagado</span>
                            <small class="text-muted d-block">Se calculó según los productos</small>
                        </div>
                        <strong class="confirmar-total-monto">
                            Bs {{ number_format($this->pagadoEnCentavos / 100, 2, ',', '.') }}
                        </strong>
                    </div>
                </div>

                @if ($notas !== '')
                    <div class="confirmar-notas mt-3">
                        <span class="confirmar-notas-title"><i class="ri-quote-text-line align-bottom me-1"></i>Notas</span>
                        <p class="mb-0">{{ $notas }}</p>
                    </div>
                @endif
            </div>

            <div class="modal-footer modal-crud-footer p-4">
                <div class="d-flex align-items-center justify-content-between w-100 gap-3 flex-wrap">
                    <small class="modal-pista-guardar">
                        <i class="ri-information-line align-bottom me-1"></i>
                        Se generarán {{ collect($lineas)->sum(fn ($l) => (int) ($l['cantidad'] ?? 0)) }}
                        unidades al recepcionar la mercadería.
                    </small>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light modal-cancelar" data-bs-dismiss="modal">Volver</button>
                        <button type="button" class="btn btn-success modal-guardar" wire:click="guardar"
                            wire:loading.attr="disabled" wire:target="guardar">
                            <span wire:loading.remove wire:target="guardar">
                                <i class="ri-check-double-line align-bottom me-1"></i> Confirmar registro
                            </span>
                            <span wire:loading wire:target="guardar">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Registrando...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>