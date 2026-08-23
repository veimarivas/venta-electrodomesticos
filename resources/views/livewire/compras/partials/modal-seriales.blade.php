{{-- Seriales de las unidades que generó la compra.

     El código interno lo asigna el sistema al recepcionar; el serial es el del
     fabricante y viene en la caja, así que se teclea después, con los aparatos
     delante. Se guarda todo de una vez, no unidad por unidad. --}}
<div class="modal fade" id="modalSerialesCompra" tabindex="-1" aria-hidden="true" wire:ignore.self
    data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-crud-dialog">
        <div class="modal-content border-0 modal-crud-content">
            <div class="modal-header modal-crud-header p-4">
                <div class="modal-crud-header-glow" aria-hidden="true"></div>
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm flex-shrink-0">
                        <span class="avatar-title modal-crud-icon rounded-circle fs-4">
                            <i class="ri-fingerprint-line"></i>
                        </span>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0">Seriales de las unidades</h5>
                        <small class="text-muted">
                            {{ $this->compraEnDetalle?->codigo }} ·
                            {{ $this->unidadesDeLaCompra->count() }}
                            {{ $this->unidadesDeLaCompra->count() === 1 ? 'unidad generada' : 'unidades generadas' }}
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <form wire:submit="guardarSeriales" autocomplete="off">
                <div class="modal-body modal-crud-body p-4">

                    @error('seriales')
                        <div class="alert alert-danger fs-13 py-2 px-3">
                            <i class="ri-error-warning-line align-bottom me-1"></i> {{ $message }}
                        </div>
                    @enderror

                    <p class="text-muted fs-13">
                        El <strong>código interno</strong> lo generó el sistema. Escribe el
                        serial del fabricante de cada aparato; puedes dejar vacíos los que
                        no lo traigan y completarlos después.
                    </p>

                    @php $productoAnterior = null; @endphp

                    @forelse ($this->unidadesDeLaCompra as $unidad)
                        @if ($productoAnterior !== $unidad->producto_id)
                            @php $productoAnterior = $unidad->producto_id; @endphp
                            <h6 class="crud-section-title mb-2 mt-3">
                                <i class="ri-box-3-line"></i>
                                {{ $unidad->producto?->nombre ?? 'Producto' }}
                            </h6>
                        @endif

                        <div class="serial-fila" wire:key="serial-{{ $unidad->id }}">
                            <span class="serial-codigo font-monospace">{{ $unidad->codigo_interno }}</span>

                            <input type="text" class="form-control form-control-sm"
                                wire:model="seriales.{{ $unidad->id }}"
                                placeholder="Serial del fabricante (opcional)" maxlength="100"
                                aria-label="Serial de la unidad {{ $unidad->codigo_interno }}">

                            <span class="badge fs-11 flex-shrink-0
                                {{ $unidad->estado === 'en_stock' ? 'bg-success-subtle text-success' : 'bg-light text-muted' }}">
                                {{ \App\Models\Unidad::ESTADOS[$unidad->estado] ?? $unidad->estado }}
                            </span>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            <i class="ri-inbox-line fs-3 d-block mb-2"></i>
                            Esta compra todavía no generó unidades.
                        </div>
                    @endforelse
                </div>

                <div class="modal-footer modal-crud-footer p-4">
                    <div class="d-flex align-items-center justify-content-between w-100 gap-3 flex-wrap">
                        <small class="modal-pista-guardar">
                            <i class="ri-information-line align-bottom me-1"></i>
                            Los seriales no se repiten entre unidades.
                        </small>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success modal-guardar"
                                wire:loading.attr="disabled" wire:target="guardarSeriales"
                                @disabled($this->unidadesDeLaCompra->isEmpty())>
                                <span wire:loading.remove wire:target="guardarSeriales">
                                    <i class="ri-save-line align-bottom me-1"></i> Guardar seriales
                                </span>
                                <span wire:loading wire:target="guardarSeriales">
                                    <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                    Guardando...
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
