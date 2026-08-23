{{--
    Pie común de los formularios del módulo.
    Espera: $textoBoton, $iconoBoton y $metodo (para el wire:target del spinner).
--}}
<div class="d-flex align-items-center justify-content-between w-100 gap-3 flex-wrap">
    <span class="modal-pista-guardar {{ $this->formularioValido ? 'modal-pista-ok' : '' }}">
        @if ($this->formularioValido)
            <i class="ri-checkbox-circle-fill"></i> Listo para guardar
        @else
            <i class="ri-information-line"></i>
            Completa los campos con <span class="text-danger">*</span>
        @endif
    </span>

    <div class="d-flex gap-2">
        <button type="button" class="btn btn-light modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-success modal-guardar" @disabled(! $this->formularioValido)
            wire:loading.attr="disabled" wire:target="{{ $metodo }}">
            <span wire:loading.remove wire:target="{{ $metodo }}">
                <i class="{{ $iconoBoton }} align-bottom me-1"></i> {{ $textoBoton }}
            </span>
            <span wire:loading wire:target="{{ $metodo }}">
                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                Guardando...
            </span>
        </button>
    </div>
</div>
