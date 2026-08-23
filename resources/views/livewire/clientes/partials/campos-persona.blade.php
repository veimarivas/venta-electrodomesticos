{{-- Datos personales del cliente nuevo. Mismos campos y mismas reglas que el
     módulo de personas: si aquí fueran más laxas se colarían datos que el otro
     formulario rechaza. --}}
<h6 class="modal-section-title mb-3"><i class="ri-profile-line"></i> Identificación</h6>
<div class="row g-3">
    <div class="col-md-4">
        <label for="c-carnet" class="form-label">
            Carnet de identidad <span class="text-danger">*</span>
        </label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="ri-profile-line"></i></span>
            <input type="text" id="c-carnet" wire:model.live.debounce.400ms="carnet"
                class="form-control border-start-0 ps-0 @error('carnet') is-invalid @elseif ($carnet !== '') is-valid @enderror"
                placeholder="8123456" maxlength="11" inputmode="numeric">
            @error('carnet')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-md-8">
        <label for="c-nombres" class="form-label">Nombres <span class="text-danger">*</span></label>
        <input type="text" id="c-nombres" wire:model.live.debounce.400ms="nombres"
            class="form-control @error('nombres') is-invalid @elseif ($nombres !== '') is-valid @enderror"
            placeholder="Juan Carlos" maxlength="100">
        @error('nombres')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="c-apellido_paterno" class="form-label">
            Apellido paterno <span class="text-muted fw-normal fs-12">(al menos uno)</span>
        </label>
        <input type="text" id="c-apellido_paterno" wire:model.live.debounce.400ms="apellido_paterno"
            class="form-control @error('apellido_paterno') is-invalid @elseif ($apellido_paterno !== '') is-valid @enderror"
            placeholder="Rivas" maxlength="60">
        @error('apellido_paterno')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="c-apellido_materno" class="form-label">
            Apellido materno <span class="text-muted fw-normal fs-12">(al menos uno)</span>
        </label>
        <input type="text" id="c-apellido_materno" wire:model.live.debounce.400ms="apellido_materno"
            class="form-control @error('apellido_materno') is-invalid @enderror"
            placeholder="Quispe" maxlength="60">
        @error('apellido_materno')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label for="c-fecha_nacimiento" class="form-label">
            Fecha de nacimiento <span class="text-muted fw-normal fs-12">(opcional)</span>
        </label>
        <input type="date" id="c-fecha_nacimiento" wire:model.live="fecha_nacimiento"
            max="{{ now()->subDay()->format('Y-m-d') }}"
            class="form-control @error('fecha_nacimiento') is-invalid @enderror">
        @error('fecha_nacimiento')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<h6 class="modal-section-title mt-4 mb-3"><i class="ri-contacts-line"></i> Información de contacto</h6>
<div class="row g-3">
    <div class="col-md-6">
        <label for="c-celular" class="form-label">
            Celular <span class="text-muted fw-normal fs-12">(opcional)</span>
        </label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="ri-phone-line"></i></span>
            <input type="text" id="c-celular" wire:model.live.debounce.400ms="celular"
                class="form-control border-start-0 ps-0 @error('celular') is-invalid @enderror"
                placeholder="71234567" maxlength="8" inputmode="numeric">
            @error('celular')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-md-6">
        <label for="c-correo" class="form-label">
            Correo electrónico <span class="text-muted fw-normal fs-12">(opcional)</span>
        </label>
        <div class="input-group">
            <span class="input-group-text bg-light border-end-0"><i class="ri-mail-line"></i></span>
            <input type="email" id="c-correo" wire:model.live.debounce.400ms="correo"
                class="form-control border-start-0 ps-0 @error('correo') is-invalid @enderror"
                placeholder="juan@correo.com">
            @error('correo')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="col-12">
        <label for="c-direccion" class="form-label">
            Dirección <span class="text-muted fw-normal fs-12">(opcional)</span>
        </label>
        <textarea id="c-direccion" rows="2" wire:model.live.debounce.400ms="direccion"
            class="form-control @error('direccion') is-invalid @enderror"
            placeholder="Av. Siempre Viva #742, Zona Central"></textarea>
        @error('direccion')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>
