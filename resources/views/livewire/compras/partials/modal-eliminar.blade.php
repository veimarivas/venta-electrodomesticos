{{-- Solo se puede eliminar una compra en borrador --}}
<div class="modal fade zoomIn" id="modalEliminarCompra" tabindex="-1" aria-hidden="true" wire:ignore.self>
    <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
        <div class="modal-content border-0 shadow-lg modal-eliminar-content">
            <div class="modal-body modal-eliminar-body p-4 text-center">
                <div class="modal-eliminar-icon mx-auto mb-4">
                    <span class="avatar-title rounded-circle fs-1"><i class="ri-delete-bin-line"></i></span>
                </div>

                <h5 class="mb-2">¿Eliminar esta compra?</h5>
                <p class="text-muted mb-4">
                    Se eliminará el borrador
                    <strong class="modal-eliminar-nombre font-monospace">{{ $eliminarCodigo }}</strong>
                    con todas sus líneas. No afecta al inventario porque todavía no generó unidades.
                </p>

                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger modal-eliminar-btn w-100" wire:click="eliminar"
                        wire:loading.attr="disabled" wire:target="eliminar">
                        <span wire:loading.remove wire:target="eliminar">Sí, eliminar</span>
                        <span wire:loading wire:target="eliminar">
                            <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                            Eliminando...
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
