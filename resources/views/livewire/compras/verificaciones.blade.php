<div class="compras-modulo verificar-modulo">

    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <span class="badge text-white mb-3 crud-chip">
                    <i class="ri-clipboard-check-line me-1"></i> Compras · Por verificar
                </span>
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-md flex-shrink-0">
                        <span class="avatar-title crud-tile text-white rounded-3 fs-3">
                            <i class="ri-inbox-unarchive-line"></i>
                        </span>
                    </div>
                    <div class="min-w-0">
                        <h4 class="text-white mb-1">Compras por verificar</h4>
                        <p class="text-white-50 mb-0">
                            Las que te asignaron. Cuenta lo que llegó y regístralo: recién entonces entra al stock.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-transparent py-3">
            <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                Pendientes
                <span class="verificar-cuenta">{{ $this->pendientes->count() }}</span>
            </h5>
        </div>
        <div class="card-body p-0">
            @forelse ($this->pendientes as $compra)
                @php
                    $pedidas = (int) ($compra->detalles_sum_cantidad ?? 0);
                    $faltan = max($pedidas - $compra->unidades_count, 0);
                @endphp
                <a href="{{ route('compras.verificar', $compra) }}" class="verificar-fila" wire:key="pendiente-{{ $compra->id }}">
                    <span class="verificar-fila-icono" aria-hidden="true"><i class="ri-truck-line"></i></span>
                    <span class="flex-grow-1 min-w-0">
                        <span class="d-block fw-semibold text-truncate">{{ $compra->proveedor?->nombre }}</span>
                        <span class="d-block text-muted fs-12">
                            <span class="font-monospace">{{ $compra->codigo }}</span>
                            · {{ $compra->fecha_compra?->format('d/m/Y') }}
                            · {{ $compra->detalles_count }} {{ $compra->detalles_count === 1 ? 'producto' : 'productos' }}
                            @if ($compra->asignada_en) · asignada {{ $compra->asignada_en->diffForHumans() }} @endif
                        </span>
                    </span>
                    <span class="text-end flex-shrink-0">
                        <span class="d-block fw-semibold">{{ $faltan }} por llegar</span>
                        <span class="d-block text-muted fs-12">de {{ $pedidas }}</span>
                    </span>
                    <i class="ri-arrow-right-s-line fs-4 text-muted" aria-hidden="true"></i>
                </a>
            @empty
                <div class="text-center py-5 px-3">
                    <div class="crud-empty-icon mx-auto mb-3">
                        <span class="avatar-title fs-2"><i class="ri-checkbox-circle-line"></i></span>
                    </div>
                    <h6 class="mb-1">No tienes compras por verificar</h6>
                    <p class="text-muted mb-0 fs-13">Cuando el administrador te asigne una, aparece aquí y te llega un aviso.</p>
                </div>
            @endforelse
        </div>
    </div>

    @if ($this->verificadas->isNotEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent py-3">
                <h5 class="card-title mb-0">Verificadas hace poco</h5>
            </div>
            <div class="card-body p-0">
                @foreach ($this->verificadas as $compra)
                    <div class="verificar-fila verificar-fila--hecha" wire:key="hecha-{{ $compra->id }}">
                        <span class="verificar-fila-icono" aria-hidden="true"><i class="ri-check-double-line"></i></span>
                        <span class="flex-grow-1 min-w-0">
                            <span class="d-block fw-semibold text-truncate">{{ $compra->proveedor?->nombre }}</span>
                            <span class="d-block text-muted fs-12">
                                <span class="font-monospace">{{ $compra->codigo }}</span>
                                · recepcionada {{ $compra->recepcionada_en?->format('d/m/Y H:i') }}
                            </span>
                        </span>
                        <span class="text-muted fs-13 flex-shrink-0">{{ $compra->unidades_count }} aparatos</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
