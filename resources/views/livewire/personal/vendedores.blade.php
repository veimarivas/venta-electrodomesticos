<div class="vendedores-modulo">

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <span class="badge text-white mb-3 crud-chip">
                    <i class="ri-team-line me-1"></i> Personal · Vendedores
                </span>
                <h4 class="text-white mb-1">Vendedores</h4>
                <p class="text-white-50 mb-0">
                    Cuenta de acceso de cada uno, reinicio para quien la olvidó y los días que le toca trabajar en el mes.
                </p>
            </div>
        </div>
    </div>

    {{-- ===================== Filtros ===================== --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap gap-3 align-items-center">
            <div class="gastos-dia">
                <button type="button" class="btn btn-light btn-icon" wire:click="mesAnterior" aria-label="Mes anterior">
                    <i class="ri-arrow-left-s-line"></i>
                </button>
                <input type="month" class="form-control gastos-dia-fecha" wire:model.live="mes" aria-label="Mes">
                <button type="button" class="btn btn-light btn-icon" wire:click="mesSiguiente" aria-label="Mes siguiente">
                    <i class="ri-arrow-right-s-line"></i>
                </button>
                <span class="gastos-dia-texto">{{ $inicio->translatedFormat('F Y') }}</span>
            </div>
            <div class="search-box flex-grow-1" style="max-width: 22rem;">
                <input type="text" class="form-control" placeholder="Buscar por nombre, código o cargo"
                    wire:model.live.debounce.300ms="buscar">
                <i class="ri-search-line search-icon"></i>
            </div>
            <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay></span>
        </div>
    </div>

    {{-- ===================== Lista ===================== --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if ($this->filas === [])
                <div class="text-center py-5 px-3">
                    <div class="crud-empty-icon mx-auto mb-3">
                        <span class="avatar-title fs-2"><i class="ri-team-line"></i></span>
                    </div>
                    <h6 class="mb-1">Sin trabajadores activos</h6>
                    <p class="text-muted mb-0 fs-13">Regístralos en <a href="{{ route('trabajadores.index') }}">Trabajadores</a>.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="text-uppercase fs-11 text-muted">
                                <th class="ps-4">Trabajador</th>
                                <th>Cuenta</th>
                                <th class="text-end">Días en {{ $inicio->translatedFormat('F') }}</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->filas as $f)
                                <tr wire:key="vendedor-{{ $f['trabajador_id'] }}">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="avatar-xs flex-shrink-0">
                                                <span class="avatar-title rounded-circle bg-primary-subtle text-primary fs-12">{{ $f['iniciales'] }}</span>
                                            </span>
                                            <div class="min-w-0">
                                                <div class="fw-semibold">{{ $f['nombre'] }}</div>
                                                <small class="text-muted">{{ $f['codigo'] }}{{ $f['cargo'] ? ' · '.$f['cargo'] : '' }}</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($f['cuenta'])
                                            <code>{{ $f['cuenta']['usuario'] }}</code>
                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                @foreach ($f['cuenta']['roles'] as $rol)
                                                    <span class="badge bg-light text-body">{{ $rol }}</span>
                                                @endforeach
                                                @unless ($f['cuenta']['activa'])
                                                    <span class="badge bg-danger-subtle text-danger">Desactivada</span>
                                                @endunless
                                                @if ($f['cuenta']['debe_cambiar_password'])
                                                    <span class="badge bg-warning-subtle text-warning">Contraseña temporal</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted">Sin cuenta</span>
                                        @endif
                                    </td>
                                    <td class="text-end tabular">
                                        @if ($f['cuenta'])
                                            <span class="fw-semibold">{{ $f['dias_programados'] }}</span>
                                            @if ($f['dias_programados'] === 0)
                                                <small class="d-block text-muted">sin programar</small>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex flex-wrap gap-2 justify-content-end">
                                            @if ($f['cuenta'] === null)
                                                @can('usuarios.crear')
                                                    <button type="button" class="btn btn-sm btn-primary"
                                                        wire:click="confirmarCrearCuenta({{ $f['trabajador_id'] }})">
                                                        <i class="ri-user-add-line align-bottom me-1"></i> Crear cuenta
                                                    </button>
                                                @endcan
                                            @else
                                                @can('asistencia.programar')
                                                    <button type="button" class="btn btn-sm btn-soft-primary"
                                                        wire:click="abrirDias({{ $f['cuenta']['id'] }})">
                                                        <i class="ri-calendar-2-line align-bottom me-1"></i> Días de trabajo
                                                    </button>
                                                @endcan
                                                @can('usuarios.editar')
                                                    @if ($f['cuenta']['id'] !== auth()->id())
                                                        <button type="button" class="btn btn-sm btn-soft-warning"
                                                            wire:click="$dispatch('reiniciar-acceso', { userId: {{ $f['cuenta']['id'] }} })">
                                                            <i class="ri-key-2-line align-bottom me-1"></i> Reiniciar acceso
                                                        </button>
                                                    @endif
                                                @endcan
                                            @endif
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

    {{-- ===================== Crear cuenta ===================== --}}
    <div class="modal fade zoomIn" id="modalCrearCuentaVendedor" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg modal-crud-content">
                <div class="modal-body p-4">
                    @if (! $cuentaCreada)
                        <h5 class="mb-1">Crear la cuenta de {{ $cuentaNombre }}</h5>
                        <p class="text-muted fs-13 mb-3">Con esta cuenta entra al panel y a la app.</p>

                        <ul class="list-unstyled reiniciar-acceso-datos mb-3">
                            <li><span>Usuario</span> <code>{{ $cuentaUsuario }}</code></li>
                            <li><span>Contraseña temporal</span> <strong>su carnet</strong></li>
                        </ul>

                        <label class="form-label fs-12" for="v-rol">Rol</label>
                        <select id="v-rol" class="form-select mb-1 @error('cuentaRol') is-invalid @enderror" wire:model="cuentaRol">
                            @foreach ($this->roles as $rol)
                                <option value="{{ $rol }}">{{ $rol }}</option>
                            @endforeach
                        </select>
                        @error('cuentaRol') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <p class="text-muted fs-12 mt-2 mb-4">Al entrar por primera vez, el sistema le pide una contraseña propia.</p>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light w-100" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary w-100" wire:click="crearCuenta"
                                wire:loading.attr="disabled" wire:target="crearCuenta">
                                <i class="ri-user-add-line align-bottom me-1"></i> Crear cuenta
                            </button>
                        </div>
                    @else
                        <div class="text-center">
                            <div class="avatar-md mx-auto mb-3">
                                <span class="avatar-title rounded-circle fs-2 bg-success-subtle text-success">
                                    <i class="ri-checkbox-circle-line"></i>
                                </span>
                            </div>
                            <h5 class="mb-1">Cuenta creada</h5>
                            <p class="text-muted fs-13 mb-3">Dale estos datos a {{ $cuentaNombre }}:</p>
                        </div>
                        <ul class="list-unstyled reiniciar-acceso-datos mb-4">
                            <li><span>Usuario</span> <code>{{ $cuentaUsuario }}</code></li>
                            <li><span>Contraseña temporal</span> <code>{{ $cuentaTemporal }}</code> <small class="text-muted">(su carnet)</small></li>
                        </ul>
                        <button type="button" class="btn btn-primary w-100" data-bs-dismiss="modal">Listo</button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Días de trabajo ===================== --}}
    <div class="modal fade" id="modalDiasDeTrabajo" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 modal-crud-content">
                <div class="modal-header p-4 pb-2">
                    <div>
                        <h5 class="modal-title mb-0">Días de trabajo · {{ $diasNombre }}</h5>
                        <small class="text-muted">{{ ucfirst($inicio->translatedFormat('F Y')) }} — toca un día para marcarlo o quitarlo.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-light" wire:click="aplicarPatron('lunes-sabado')">Lunes a sábado</button>
                        <button type="button" class="btn btn-sm btn-light" wire:click="aplicarPatron('lunes-viernes')">Lunes a viernes</button>
                        <button type="button" class="btn btn-sm btn-light" wire:click="aplicarPatron('todos')">Todo el mes</button>
                        @if ($comoMesAnterior !== [])
                            <button type="button" class="btn btn-sm btn-light" wire:click="aplicarPatron('mes-anterior')">
                                <i class="ri-history-line align-bottom"></i> Como el mes anterior
                            </button>
                        @endif
                        <button type="button" class="btn btn-sm btn-ghost-danger" wire:click="aplicarPatron('ninguno')">Ninguno</button>
                    </div>

                    <div class="dias-calendario" role="grid" aria-label="Calendario del mes">
                        @foreach (['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'] as $nombreDia)
                            <div class="dias-calendario-cabecera">{{ $nombreDia }}</div>
                        @endforeach
                        @foreach ($this->semanas() as $semana)
                            @foreach ($semana as $dia)
                                @if ($dia === null)
                                    <div class="dias-calendario-vacio"></div>
                                @else
                                    @php
                                        $clave = $dia->toDateString();
                                        $marcado = in_array($clave, $fechas, true);
                                    @endphp
                                    <button type="button" wire:key="dia-{{ $clave }}"
                                        class="dias-calendario-dia @if ($marcado) is-marcado @endif @if ($dia->isToday()) is-hoy @endif"
                                        wire:click="alternarDia('{{ $clave }}')"
                                        aria-pressed="{{ $marcado ? 'true' : 'false' }}"
                                        aria-label="{{ $dia->translatedFormat('l j') }}">
                                        {{ $dia->day }}
                                    </button>
                                @endif
                            @endforeach
                        @endforeach
                    </div>

                    @error('fechas') <div class="text-danger fs-12 mt-2">{{ $message }}</div> @enderror
                </div>
                <div class="modal-footer p-4 pt-0 d-flex justify-content-between">
                    <span class="text-muted fs-13">
                        <strong class="text-body">{{ count($fechas) }}</strong> {{ count($fechas) === 1 ? 'día' : 'días' }} marcados
                    </span>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" wire:click="guardarDias"
                            wire:loading.attr="disabled" wire:target="guardarDias">
                            <i class="ri-save-line align-bottom me-1"></i> Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <livewire:personal.reiniciar-acceso />
</div>
