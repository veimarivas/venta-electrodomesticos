<div class="compras-modulo verificar-modulo">

    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-3">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-clipboard-check-line me-1"></i> Compras · Verificar mercadería
                        </span>
                        <h4 class="text-white mb-1">
                            {{ $compra->proveedor?->nombre }}
                            <span class="font-monospace fs-15 text-white-50">{{ $compra->codigo }}</span>
                        </h4>
                        <p class="text-white-50 mb-0">
                            {{ $compra->fecha_compra?->format('d/m/Y') }}
                            @if ($compra->numero_factura) · Factura {{ $compra->numero_factura }} @endif
                            @if ($compra->verificador) · La verifica {{ $compra->verificador->name }} @endif
                        </p>
                    </div>
                    <div class="col-lg-4 d-flex justify-content-lg-end">
                        @if ($compra->esta_recepcionada)
                            <span class="verificar-estado verificar-estado--ok"><i class="ri-checkbox-circle-line"></i> Recepcionada</span>
                        @elseif ($compra->puede_recepcionarse)
                            <span class="verificar-estado"><i class="ri-time-line"></i> Faltan {{ $this->faltan }} por llegar</span>
                        @else
                            <span class="verificar-estado">{{ \App\Models\Compra::ESTADOS[$compra->estado] ?? $compra->estado }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($compra->notas)
        <div class="alert alert-info"><i class="ri-sticky-note-line me-1"></i>{{ $compra->notas }}</div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3">
            <h5 class="card-title mb-1">¿Qué llegó?</h5>
            <small class="text-muted">
                Escribe el serial de cada aparato que lo lleva y cuántos llegaron de los demás. Puede llegar
                por tandas: registra lo que tengas hoy y la compra sigue pendiente hasta completar.
            </small>
        </div>
        <div class="card-body">
            @foreach ($this->lineas as $linea)
                @php $faltan = max($linea->cantidad - $linea->unidades_count, 0); @endphp
                <div class="verificar-linea" wire:key="linea-{{ $linea->id }}">
                    <div class="d-flex align-items-start gap-3 flex-wrap">
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="mb-1">{{ $linea->producto?->nombre }}</h6>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="verificar-pill">Pedidos {{ $linea->cantidad }}</span>
                                <span class="verificar-pill verificar-pill--ok">Recibidos {{ $linea->unidades_count }}</span>
                                @if ($faltan > 0)
                                    <span class="verificar-pill verificar-pill--pendiente">Faltan {{ $faltan }}</span>
                                @endif
                                <span class="verificar-pill">{{ $linea->producto?->tiene_serial ? 'Lleva serial' : 'Sin serial' }}</span>
                            </div>
                        </div>
                    </div>

                    @if ($faltan > 0 && $compra->puede_recepcionarse)
                        @if ($linea->producto?->tiene_serial)
                            <div class="row g-2 mt-2">
                                @foreach ($seriales[$linea->id] ?? [] as $indice => $valor)
                                    <div class="col-sm-6 col-lg-4">
                                        <input type="text" class="form-control form-control-sm font-monospace"
                                            wire:model="seriales.{{ $linea->id }}.{{ $indice }}"
                                            aria-label="Serial {{ $indice + 1 }} de {{ $linea->producto?->nombre }}"
                                            placeholder="Serial {{ $indice + 1 }} (vacío si no llegó)">
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                                <label class="form-label mb-0" for="cant-{{ $linea->id }}">Llegaron</label>
                                <input type="number" min="0" max="{{ $faltan }}" id="cant-{{ $linea->id }}"
                                    class="form-control form-control-sm verificar-cantidad @error('cantidades.'.$linea->id) is-invalid @enderror"
                                    wire:model="cantidades.{{ $linea->id }}" placeholder="0">
                                <span class="text-muted fs-13">de {{ $faltan }}</span>
                                <button type="button" class="btn btn-sm btn-soft-primary" wire:click="llegoTodo({{ $linea->id }})">
                                    Llegó todo
                                </button>
                                @error('cantidades.'.$linea->id)
                                    <div class="text-danger fs-12 w-100">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif
                    @endif
                </div>
            @endforeach

            @if ($compra->puede_recepcionarse)
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                    <a href="{{ route('compras.verificaciones') }}" class="btn btn-light">
                        <i class="ri-arrow-left-line align-bottom me-1"></i> Volver
                    </a>
                    <button type="button" class="btn btn-success" wire:click="verificar"
                        wire:loading.attr="disabled" wire:target="verificar">
                        <span wire:loading.remove wire:target="verificar">
                            <i class="ri-archive-2-line align-bottom me-1"></i> Registrar lo que llegó
                        </span>
                        <span wire:loading wire:target="verificar">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span> Registrando...
                        </span>
                    </button>
                </div>
            @else
                <a href="{{ route('compras.verificaciones') }}" class="btn btn-light mt-3">
                    <i class="ri-arrow-left-line align-bottom me-1"></i> Volver a mis compras
                </a>
            @endif
        </div>
    </div>
</div>
