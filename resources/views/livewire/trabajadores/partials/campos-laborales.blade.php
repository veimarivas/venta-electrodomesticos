{{--
    Cargo, fecha de ingreso y previsualización del código.
    Se comparte entre el paso "asignar" y el paso "registrar persona nueva".
--}}
<div class="row g-3">
    <div class="col-md-6">
        <label for="cargo_id" class="form-label">Cargo <span class="text-danger">*</span></label>
        <select id="cargo_id" wire:model.live="cargo_id"
            class="form-select @error('cargo_id') is-invalid @elseif ($cargo_id !== '') is-valid @enderror">
            <option value="">Selecciona un cargo</option>
            @foreach ($this->cargos as $cargo)
                <option value="{{ $cargo->id }}">{{ $cargo->nombre }}</option>
            @endforeach
        </select>
        @error('cargo_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror

        @if ($this->cargos->isEmpty())
            {{-- Sin cargos no se puede registrar a nadie: se avisa y se enlaza. --}}
            <small class="text-danger d-block mt-1">
                <i class="ri-error-warning-line align-bottom"></i>
                No hay cargos registrados.
                <a href="{{ route('cargos.index') }}" class="fw-semibold">Crear uno primero</a>.
            </small>
        @endif
    </div>

    <div class="col-md-6">
        <label for="fecha_ingreso" class="form-label">Fecha de ingreso <span class="text-danger">*</span></label>
        <input type="date" id="fecha_ingreso" wire:model.live="fecha_ingreso" max="{{ now()->format('Y-m-d') }}"
            class="form-control @error('fecha_ingreso') is-invalid @elseif ($fecha_ingreso !== '') is-valid @enderror">
        @error('fecha_ingreso')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="trabajador-codigo d-flex align-items-center gap-3 p-3">
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
