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
                                    Fija el precio de venta de hoy. El último registrado es el que ofrece el punto de venta.
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

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="card-title mb-0">Productos con stock</h5>
                    <small class="text-muted fs-13">
                        {{ $filas->count() }} {{ $filas->count() === 1 ? 'producto' : 'productos' }}.
                        Se propone el precio de la jornada anterior; si no hay, el inicial.
                    </small>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            @if ($filas->isEmpty())
                <p class="text-muted text-center py-5 mb-0">
                    No hay productos con stock para fijar precios.
                </p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="text-end">Stock</th>
                                <th class="text-end">Costo</th>
                                <th class="text-end">Precio anterior</th>
                                <th class="text-end" style="width: 12rem;">Precio de hoy</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($filas as $fila)
                                <tr wire:key="precio-{{ $fila->producto->id }}">
                                    <td>
                                        <div class="fw-semibold">{{ $fila->producto->nombre }}</div>
                                        <small class="text-muted">
                                            {{ $fila->producto->categoria?->nombre ?? 'Sin categoría' }}
                                            @if ($fila->precio_hoy !== null)
                                                · <span class="text-success">ya tiene precio de hoy</span>
                                            @endif
                                        </small>
                                    </td>
                                    <td class="text-end">{{ $fila->disponibles }}</td>
                                    <td class="text-end text-muted">
                                        Bs {{ number_format($fila->costo, 2, ',', '.') }}
                                    </td>
                                    <td class="text-end text-muted">
                                        Bs {{ number_format($fila->precio_anterior, 2, ',', '.') }}
                                    </td>
                                    <td class="text-end">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">Bs</span>
                                            <input type="number" step="0.01" min="0.01"
                                                class="form-control text-end @error('precios.'.$fila->producto->id) is-invalid @enderror"
                                                wire:model="precios.{{ $fila->producto->id }}">
                                            @error('precios.'.$fila->producto->id)
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
