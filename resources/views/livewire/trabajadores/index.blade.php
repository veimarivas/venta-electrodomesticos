{{--
    "personas-modulo" aporta los estilos compartidos de este tipo de módulo
    (encabezado, tabla, modales, paginación), definidos en _personas.scss.
    "trabajadores-modulo" queda para los ajustes propios de esta pantalla.
--}}
<div class="personas-modulo trabajadores-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 personas-encabezado">
        <div class="card-body p-0">
            <div class="p-4 personas-hero">
                <div class="personas-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 personas-chip">
                            <i class="ri-user-star-line me-1"></i> Personal de la tienda
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title personas-tile text-white rounded-3 fs-3">
                                    <i class="ri-user-star-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Trabajadores</h4>
                                <p class="text-white-50 mb-0">
                                    Quiénes trabajan en la tienda, con qué cargo y desde cuándo.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('trabajadores.crear')
                                <button type="button" class="btn btn-light personas-nueva-hero" wire:click="abrirCrear">
                                    <i class="ri-user-add-line align-bottom me-1"></i> Nuevo trabajador
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Indicadores ===================== --}}
    <div class="row g-3 mb-4 personas-kpis">
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Trabajadores activos" value="{{ $totalActivos }}" icon="bx-user-check"
                color="primary" caption="Personal vigente" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Dados de baja" value="{{ $totalBajas }}" icon="bx-user-x"
                color="secondary" caption="Historial conservado" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Cargos disponibles" value="{{ $totalCargos }}" icon="bx-briefcase"
                color="info" caption="Puestos configurados" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Ingresos este mes" value="{{ $ingresosDelMes }}" icon="bx-calendar-plus"
                color="success" caption="{{ ucfirst(now()->translatedFormat('F Y')) }}" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm personas-listado">
        <div class="card-header bg-transparent py-3 personas-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Listado de trabajadores
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $trabajadores->total() }}
                        {{ $trabajadores->total() === 1 ? 'trabajador encontrado' : 'trabajadores encontrados' }}
                    </small>
                </div>

                <div class="col-md-5">
                    <div class="search-box">
                        <input type="text" class="form-control personas-busqueda"
                            placeholder="Buscar por código, nombre, carnet o cargo..."
                            wire:model.live.debounce.400ms="buscar">
                        <i class="ri-search-line search-icon"></i>
                        @if ($buscar !== '')
                            <button type="button"
                                class="btn btn-sm btn-link text-muted position-absolute end-0 top-50 translate-middle-y me-1 p-1"
                                wire:click="$set('buscar', '')" title="Limpiar búsqueda">
                                <i class="ri-close-circle-fill fs-16"></i>
                            </button>
                        @endif
                    </div>
                </div>

                {{-- Filtro de estado: los dados de baja siguen consultables --}}
                <div class="col-md-3">
                    <div class="btn-group w-100 filtro-estado" role="group" aria-label="Filtrar por estado">
                        @foreach (['activos' => 'Activos', 'baja' => 'Bajas', 'todos' => 'Todos'] as $valor => $etiqueta)
                            <button type="button"
                                class="btn btn-sm {{ $filtroEstado === $valor ? 'btn-primary' : 'btn-soft-secondary' }}"
                                wire:click="$set('filtroEstado', '{{ $valor }}')">
                                {{ $etiqueta }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-personas"
                    wire:loading.class="opacity-50" wire:target="buscar, ordenar, gotoPage, previousPage, nextPage">
                    {{-- Cinco columnas, no siete: el código va bajo el nombre y la
                         fecha de ingreso junto al cargo. Con las siete anteriores la
                         tabla no cabía y aparecía scroll horizontal. --}}
                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4" role="button" wire:click="ordenar('codigo')">
                                Trabajador <x-sort-icon :field="'codigo'" :current="$ordenarPor" :direction="$direccionOrden" />
                            </th>
                            <th scope="col" role="button" wire:click="ordenar('fecha_ingreso')">
                                Cargo e ingreso <x-sort-icon :field="'fecha_ingreso'" :current="$ordenarPor" :direction="$direccionOrden" />
                            </th>
                            <th scope="col">Cuenta</th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($trabajadores as $trabajador)
                            @php
                                // La cuenta cuelga de la persona (users.persona_id),
                                // no del trabajador: es la relación que ya existía.
                                $cuenta = $trabajador->persona->user;
                            @endphp
                            <tr wire:key="trabajador-{{ $trabajador->id }}"
                                class="{{ $trabajador->esta_activo ? '' : 'fila-dado-de-baja' }}">
                                <td class="ps-4 col-trabajador">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-xs flex-shrink-0">
                                            <span
                                                class="avatar-title rounded-circle bg-{{ $trabajador->persona->color_avatar }}-subtle text-{{ $trabajador->persona->color_avatar }} fw-semibold">
                                                {{ $trabajador->persona->iniciales }}
                                            </span>
                                        </div>
                                        <div class="min-w-0">
                                            <h6 class="mb-0 text-truncate">{{ $trabajador->persona->nombre_completo }}</h6>
                                            <small class="text-muted d-flex align-items-center gap-2">
                                                <span class="badge fs-11 col-codigo {{ $trabajador->esta_activo ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }}">
                                                    {{ $trabajador->codigo }}
                                                </span>
                                                <span class="text-truncate">CI {{ $trabajador->persona->carnet }}</span>
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td class="col-cargo">
                                    <div class="text-truncate">
                                        <i class="ri-briefcase-line align-bottom me-1 text-muted"></i>
                                        {{ $trabajador->cargo->nombre }}
                                    </div>
                                    <small class="text-muted">
                                        Desde {{ $trabajador->fecha_ingreso->format('d/m/Y') }}
                                        · {{ $trabajador->antiguedad }}
                                    </small>
                                </td>

                                <td class="col-cuenta">
                                    @if ($cuenta)
                                        <div class="text-truncate trabajador-cuenta-usuario"
                                            title="Inicia sesión con {{ $cuenta->name }} o {{ $cuenta->email }}">
                                            <i class="ri-shield-user-line align-bottom me-1 text-info"></i>
                                            {{ $cuenta->name }}
                                        </div>
                                        <small class="{{ $cuenta->is_active ? 'text-muted' : 'text-danger' }}">
                                            {{ $cuenta->is_active ? 'Activa' : 'Bloqueada' }}
                                        </small>
                                    @else
                                        <span class="text-muted">
                                            <i class="ri-user-forbid-line align-bottom me-1"></i> Sin cuenta
                                        </span>
                                    @endif
                                </td>

                                <td class="col-estado">
                                    @if ($trabajador->esta_activo)
                                        <span class="badge bg-success-subtle text-success fs-12">
                                            <i class="ri-checkbox-circle-line align-bottom me-1"></i> Activo
                                        </span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary fs-12"
                                            title="{{ $trabajador->motivo_baja ?: 'Sin motivo registrado' }}">
                                            <i class="ri-user-unfollow-line align-bottom me-1"></i> Dado de baja
                                        </span>
                                        <div class="fs-11 text-muted mt-1 text-truncate">
                                            {{ $trabajador->fecha_baja->format('d/m/Y') }}
                                            @if ($trabajador->motivo_baja)
                                                · {{ Str::limit($trabajador->motivo_baja, 18) }}
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @if ($trabajador->esta_activo)
                                            @if ($cuenta === null)
                                                @can('usuarios.crear')
                                                    <button type="button"
                                                        class="btn btn-sm btn-ghost-success btn-icon rounded-circle trabajador-accion-cuenta"
                                                        wire:click="confirmarCrearCuenta({{ $trabajador->id }})"
                                                        title="Crear cuenta de usuario"
                                                        aria-label="Crear cuenta de usuario para {{ $trabajador->persona->nombres }}">
                                                        <i class="ri-user-add-line fs-16"></i>
                                                    </button>
                                                @endcan
                                            @else
                                                @can('usuarios.editar')
                                                    <button type="button"
                                                        class="btn btn-sm btn-ghost-warning btn-icon rounded-circle trabajador-accion-password"
                                                        wire:click="confirmarReiniciarPassword({{ $trabajador->id }})"
                                                        title="Reiniciar contraseña al carnet"
                                                        aria-label="Reiniciar la contraseña de {{ $trabajador->persona->nombres }}">
                                                        <i class="ri-lock-password-line fs-16"></i>
                                                    </button>
                                                @endcan
                                            @endif
                                            @can('trabajadores.editar')
                                                <button type="button"
                                                    class="btn btn-sm btn-ghost-primary btn-icon rounded-circle persona-accion-editar"
                                                    wire:click="abrirEditar({{ $trabajador->id }})"
                                                    title="Editar cargo y fecha"
                                                    aria-label="Editar la ficha de {{ $trabajador->persona->nombres }}">
                                                    <i class="ri-pencil-line fs-16"></i>
                                                </button>
                                            @endcan
                                            @can('trabajadores.eliminar')
                                                <button type="button"
                                                    class="btn btn-sm btn-ghost-danger btn-icon rounded-circle persona-accion-eliminar"
                                                    wire:click="confirmarBaja({{ $trabajador->id }})"
                                                    title="Dar de baja"
                                                    aria-label="Dar de baja a {{ $trabajador->persona->nombres }}">
                                                    <i class="ri-user-unfollow-line fs-16"></i>
                                                </button>
                                            @endcan
                                        @else
                                            @can('trabajadores.editar')
                                                <button type="button" class="btn btn-sm btn-soft-success"
                                                    wire:click="reactivar({{ $trabajador->id }})"
                                                    aria-label="Reincorporar a {{ $trabajador->persona->nombres }}">
                                                    <i class="ri-user-follow-line align-bottom me-1"></i> Reincorporar
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="text-center py-5">
                                        <div class="personas-empty-icon mx-auto mb-4">
                                            <span class="avatar-title rounded-circle fs-1">
                                                <i class="{{ $buscar !== '' ? 'ri-search-eye-line' : 'ri-user-star-line' }}"></i>
                                            </span>
                                        </div>
                                        @if ($filtroEstado === 'baja' && $buscar === '')
                                            <h5 class="mb-1">No hay trabajadores dados de baja</h5>
                                            <p class="text-muted mb-0">Todo el personal registrado sigue activo.</p>
                                        @elseif ($buscar !== '')
                                            <h5 class="mb-1">Sin resultados para «{{ $buscar }}»</h5>
                                            <p class="text-muted mb-3">Revisa la ortografía o prueba con menos palabras.</p>
                                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                                wire:click="$set('buscar', '')">
                                                <i class="ri-close-line align-bottom me-1"></i> Limpiar búsqueda
                                            </button>
                                        @elseif ($filtroEstado === 'activos' && $totalBajas > 0)
                                            {{-- Hay fichas, pero todas dadas de baja: decir "no hay
                                                 trabajadores registrados" sería falso y haría pensar
                                                 que se perdieron. --}}
                                            <h5 class="mb-1">Ningún trabajador activo</h5>
                                            <p class="text-muted mb-3">
                                                {{ $totalBajas }} {{ $totalBajas === 1 ? 'ficha está dada de baja' : 'fichas están dadas de baja' }}
                                                y {{ $totalBajas === 1 ? 'se conserva' : 'se conservan' }} con su historial.
                                            </p>
                                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                                wire:click="$set('filtroEstado', 'baja')">
                                                <i class="ri-eye-line align-bottom me-1"></i> Ver las bajas
                                            </button>
                                        @else
                                            <h5 class="mb-1">Todavía no hay trabajadores registrados</h5>
                                            <p class="text-muted mb-3">
                                                Busca a la persona y asígnale un cargo para darla de alta.
                                            </p>
                                            @can('trabajadores.crear')
                                                <button type="button" class="btn btn-success btn-sm" wire:click="abrirCrear">
                                                    <i class="ri-user-add-line align-bottom me-1"></i> Registrar trabajador
                                                </button>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($trabajadores->total() > 0)
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando <span class="fw-semibold">{{ $trabajadores->firstItem() }}</span>–<span
                            class="fw-semibold">{{ $trabajadores->lastItem() }}</span>
                        de <span class="fw-semibold">{{ $trabajadores->total() }}</span> trabajadores
                    </p>

                    @if ($trabajadores->hasPages())
                        <div class="paginacion-compacta">
                            {{ $trabajadores->onEachSide(1)->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal de alta (3 pasos) ===================== --}}
    <div class="modal fade" id="modalTrabajador" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-persona-dialog">
            <div class="modal-content border-0 modal-persona-content">
                <div class="modal-header modal-persona-header p-4">
                    <div class="modal-persona-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-persona-icon rounded-circle fs-4">
                                <i class="{{ $paso === 'buscar' ? 'ri-search-line' : ($paso === 'asignar' ? 'ri-user-follow-line' : 'ri-user-add-line') }}"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">
                                @if ($paso === 'buscar')
                                    Buscar a la persona
                                @elseif ($paso === 'asignar')
                                    Asignar como trabajador
                                @else
                                    Registrar persona y asignarla
                                @endif
                            </h5>
                            <small>
                                @if ($paso === 'buscar')
                                    Primero verifica si ya está registrada en el sistema.
                                @elseif ($paso === 'asignar')
                                    La persona ya existe: solo falta su cargo y fecha de ingreso.
                                @else
                                    No estaba registrada: completa sus datos y su ficha laboral.
                                @endif
                            </small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-persona-close" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>

                {{-- Indicador de progreso de los pasos --}}
                <div class="trabajador-pasos px-4 pt-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="paso-punto {{ $paso === 'buscar' ? 'activo' : 'completado' }}">1</span>
                        <span class="paso-linea {{ $paso !== 'buscar' ? 'completado' : '' }}"></span>
                        <span class="paso-punto {{ $paso !== 'buscar' ? 'activo' : '' }}">2</span>
                        <small class="text-muted ms-2">
                            {{ $paso === 'buscar' ? 'Paso 1 de 2 · Localizar a la persona' : 'Paso 2 de 2 · Ficha laboral' }}
                        </small>
                    </div>
                </div>

                {{-- ---------- PASO 1: buscar ---------- --}}
                @if ($paso === 'buscar')
                    <div class="modal-body modal-persona-body p-4">
                        <h6 class="modal-section-title mb-3"><i class="ri-search-line"></i> Buscar persona</h6>

                        <div class="search-box mb-3">
                            <input type="text" class="form-control personas-busqueda"
                                placeholder="Escribe el carnet, nombre o apellido..." autofocus
                                wire:model.live.debounce.350ms="buscarPersona">
                            <i class="ri-search-line search-icon"></i>
                            <span class="position-absolute end-0 top-50 translate-middle-y me-2"
                                wire:loading.delay wire:target="buscarPersona">
                                <span class="spinner-border spinner-border-sm text-primary" role="status"></span>
                            </span>
                        </div>

                        @if (mb_strlen(trim($buscarPersona)) < 2)
                            <div class="text-center py-4 text-muted">
                                <i class="ri-user-search-line fs-1 d-block mb-2 opacity-50"></i>
                                Escribe al menos 2 caracteres para buscar.
                            </div>
                        @elseif ($this->sinResultados)
                            {{-- No existe: se ofrece registrarla --}}
                            <div class="trabajador-sin-resultados text-center p-4">
                                <div class="personas-empty-icon mx-auto mb-3">
                                    <span class="avatar-title rounded-circle fs-1"><i class="ri-user-add-line"></i></span>
                                </div>
                                <h6 class="mb-1">«{{ $buscarPersona }}» no está registrada</h6>
                                <p class="text-muted mb-3">Puedes darla de alta y asignarle un cargo en un solo paso.</p>
                                <button type="button" class="btn btn-success" wire:click="irARegistrarPersona">
                                    <i class="ri-user-add-line align-bottom me-1"></i> Registrar esta persona
                                </button>
                            </div>
                        @else
                            <div class="list-group trabajador-resultados">
                                @foreach ($this->resultadosPersonas as $persona)
                                    <div class="list-group-item d-flex align-items-center gap-3"
                                        wire:key="resultado-{{ $persona->id }}">
                                        <div class="avatar-sm flex-shrink-0">
                                            <span
                                                class="avatar-title rounded-circle bg-{{ $persona->color_avatar }}-subtle text-{{ $persona->color_avatar }} fw-semibold">
                                                {{ $persona->iniciales }}
                                            </span>
                                        </div>

                                        <div class="flex-grow-1 min-w-0">
                                            <h6 class="mb-0 text-truncate">{{ $persona->nombre_completo }}</h6>
                                            <small class="text-muted">
                                                CI {{ $persona->carnet }}
                                                @if ($persona->celular)
                                                    · {{ $persona->celular }}
                                                @endif
                                            </small>
                                        </div>

                                        <div class="flex-shrink-0">
                                            @if ($persona->trabajador && $persona->trabajador->esta_activo)
                                                {{-- Ya tiene ficha vigente: no se puede asignar dos veces --}}
                                                <span class="badge bg-secondary-subtle text-secondary">
                                                    <i class="ri-check-line align-bottom me-1"></i>
                                                    Ya es trabajador
                                                </span>
                                                <div class="text-muted fs-11 mt-1 text-end">
                                                    {{ $persona->trabajador->codigo }} ·
                                                    {{ $persona->trabajador->cargo->nombre }}
                                                </div>
                                            @elseif ($persona->trabajador)
                                                {{-- Tuvo ficha y fue dado de baja: se reincorpora, no se
                                                     crea otra (el índice único lo impide y perdería el código). --}}
                                                <button type="button" class="btn btn-sm btn-soft-success"
                                                    wire:click="reactivar({{ $persona->trabajador->id }})">
                                                    <i class="ri-user-follow-line align-bottom me-1"></i> Reincorporar
                                                </button>
                                                <div class="text-muted fs-11 mt-1 text-end">
                                                    Dado de baja · {{ $persona->trabajador->codigo }}
                                                </div>
                                            @else
                                                <button type="button" class="btn btn-sm btn-success"
                                                    wire:click="seleccionarPersona({{ $persona->id }})">
                                                    <i class="ri-user-follow-line align-bottom me-1"></i> Asignar
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="text-center mt-3">
                                <small class="text-muted">¿No es ninguna de estas?</small>
                                <button type="button" class="btn btn-link btn-sm p-0 ms-1"
                                    wire:click="irARegistrarPersona">Registrar una persona nueva</button>
                            </div>
                        @endif
                    </div>

                    <div class="modal-footer modal-persona-footer p-4">
                        <button type="button" class="btn btn-light modal-cancelar ms-auto" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                    </div>
                @endif

                {{-- ---------- PASO 2: asignar persona existente ---------- --}}
                @if ($paso === 'asignar')
                    <form wire:submit="asignar" autocomplete="off">
                        <div class="modal-body modal-persona-body p-4">
                            @if ($this->persona)
                                <div class="trabajador-persona-elegida d-flex align-items-center gap-3 p-3 mb-4">
                                    <div class="avatar-md flex-shrink-0">
                                        <span
                                            class="avatar-title rounded-circle bg-{{ $this->persona->color_avatar }}-subtle text-{{ $this->persona->color_avatar }} fs-4 fw-semibold">
                                            {{ $this->persona->iniciales }}
                                        </span>
                                    </div>
                                    <div class="min-w-0">
                                        <h6 class="mb-1">{{ $this->persona->nombre_completo }}</h6>
                                        <div class="text-muted fs-13">
                                            CI {{ $this->persona->carnet }}
                                            @if ($this->persona->celular)
                                                · {{ $this->persona->celular }}
                                            @endif
                                            @if ($this->persona->correo)
                                                · {{ $this->persona->correo }}
                                            @endif
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-light ms-auto flex-shrink-0"
                                        wire:click="volverABuscar">
                                        <i class="ri-arrow-left-line align-bottom me-1"></i> Cambiar
                                    </button>
                                </div>
                            @endif

                            <h6 class="modal-section-title mb-3"><i class="ri-briefcase-line"></i> Ficha laboral</h6>

                            @include('livewire.trabajadores.partials.campos-laborales')
                        </div>

                        <div class="modal-footer modal-persona-footer p-4">
                            @include('livewire.trabajadores.partials.pie-formulario', [
                                'textoBoton' => 'Asignar como trabajador',
                                'iconoBoton' => 'ri-user-follow-line',
                                'metodo' => 'asignar',
                            ])
                        </div>
                    </form>
                @endif

                {{-- ---------- PASO 2b: registrar persona nueva ---------- --}}
                @if ($paso === 'nueva')
                    <form wire:submit="registrarPersonaYAsignar" autocomplete="off">
                        <div class="modal-body modal-persona-body p-4">
                            <div class="d-flex justify-content-end mb-3">
                                <button type="button" class="btn btn-sm btn-light" wire:click="volverABuscar">
                                    <i class="ri-arrow-left-line align-bottom me-1"></i> Volver a buscar
                                </button>
                            </div>

                            <h6 class="modal-section-title mb-3"><i class="ri-profile-line"></i> Identificación</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="t-carnet" class="form-label">
                                        Carnet de identidad <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="ri-profile-line"></i></span>
                                        <input type="text" id="t-carnet" wire:model.live.debounce.400ms="carnet"
                                            class="form-control border-start-0 ps-0 @error('carnet') is-invalid @elseif ($carnet !== '') is-valid @enderror"
                                            placeholder="8123456" maxlength="11" inputmode="numeric">
                                        @error('carnet')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-8">
                                    <label for="t-nombres" class="form-label">Nombres <span class="text-danger">*</span></label>
                                    <input type="text" id="t-nombres" wire:model.live.debounce.400ms="nombres"
                                        class="form-control @error('nombres') is-invalid @elseif ($nombres !== '') is-valid @enderror"
                                        placeholder="Juan Carlos" maxlength="100">
                                    @error('nombres')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="t-apellido_paterno" class="form-label">
                                        Apellido paterno <span class="text-muted fw-normal fs-12">(al menos uno)</span>
                                    </label>
                                    <input type="text" id="t-apellido_paterno"
                                        wire:model.live.debounce.400ms="apellido_paterno"
                                        class="form-control @error('apellido_paterno') is-invalid @elseif ($apellido_paterno !== '') is-valid @enderror"
                                        placeholder="Rivas" maxlength="60">
                                    @error('apellido_paterno')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="t-apellido_materno" class="form-label">
                                        Apellido materno <span class="text-muted fw-normal fs-12">(al menos uno)</span>
                                    </label>
                                    <input type="text" id="t-apellido_materno"
                                        wire:model.live.debounce.400ms="apellido_materno"
                                        class="form-control @error('apellido_materno') is-invalid @enderror"
                                        placeholder="Quispe" maxlength="60">
                                    @error('apellido_materno')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-4">
                                    <label for="t-fecha_nacimiento" class="form-label">
                                        Fecha de nacimiento <span class="text-muted fw-normal fs-12">(opcional)</span>
                                    </label>
                                    <input type="date" id="t-fecha_nacimiento" wire:model.live="fecha_nacimiento"
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
                                    <label for="t-celular" class="form-label">
                                        Celular <span class="text-muted fw-normal fs-12">(opcional)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="ri-phone-line"></i></span>
                                        <input type="text" id="t-celular" wire:model.live.debounce.400ms="celular"
                                            class="form-control border-start-0 ps-0 @error('celular') is-invalid @enderror"
                                            placeholder="71234567" maxlength="8" inputmode="numeric">
                                        @error('celular')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="t-correo" class="form-label">
                                        Correo electrónico <span class="text-muted fw-normal fs-12">(opcional)</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0"><i class="ri-mail-line"></i></span>
                                        <input type="email" id="t-correo" wire:model.live.debounce.400ms="correo"
                                            class="form-control border-start-0 ps-0 @error('correo') is-invalid @enderror"
                                            placeholder="juan@correo.com">
                                        @error('correo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="t-direccion" class="form-label">
                                        Dirección <span class="text-muted fw-normal fs-12">(opcional)</span>
                                    </label>
                                    <textarea id="t-direccion" rows="2" wire:model.live.debounce.400ms="direccion"
                                        class="form-control @error('direccion') is-invalid @enderror"
                                        placeholder="Av. Siempre Viva #742, Zona Central"></textarea>
                                    @error('direccion')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <h6 class="modal-section-title mt-4 mb-3"><i class="ri-briefcase-line"></i> Ficha laboral</h6>

                            @include('livewire.trabajadores.partials.campos-laborales')
                        </div>

                        <div class="modal-footer modal-persona-footer p-4">
                            @include('livewire.trabajadores.partials.pie-formulario', [
                                'textoBoton' => 'Registrar trabajador',
                                'iconoBoton' => 'ri-user-add-line',
                                'metodo' => 'registrarPersonaYAsignar',
                            ])
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== Modal de edición ===================== --}}
    <div class="modal fade" id="modalEditarTrabajador" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-persona-dialog">
            <div class="modal-content border-0 modal-persona-content modal-editar-persona">
                {{--
                    El formulario solo se renderiza mientras se está editando.
                    Sus campos se enlazan a las mismas propiedades que el alta
                    (cargo_id, fecha_ingreso); si ambos formularios estuvieran a
                    la vez en el DOM, el que está vacío pisaría al otro en cada
                    re-render de Livewire.
                --}}
                <div class="modal-header modal-persona-header p-4">
                    <div class="modal-persona-header-glow" aria-hidden="true"></div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-sm flex-shrink-0">
                            <span class="avatar-title modal-persona-icon rounded-circle fs-4">
                                <i class="ri-pencil-line"></i>
                            </span>
                        </div>
                        <div>
                            <h5 class="modal-title mb-0">Editar ficha laboral</h5>
                            <small>El código y los datos personales no cambian desde aquí.</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close modal-persona-close" data-bs-dismiss="modal"
                        aria-label="Cerrar"></button>
                </div>

                @if ($paso === 'editar')
                <form wire:submit="guardarEdicion" autocomplete="off">
                    <div class="modal-body modal-persona-body p-4">
                        <h6 class="modal-section-title mb-3"><i class="ri-briefcase-line"></i> Cargo y antigüedad</h6>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="e-cargo" class="form-label">Cargo <span class="text-danger">*</span></label>
                                <select id="e-cargo" wire:model.live="cargo_id"
                                    class="form-select @error('cargo_id') is-invalid @enderror">
                                    <option value="">Selecciona un cargo</option>
                                    @foreach ($this->cargos as $cargo)
                                        <option value="{{ $cargo->id }}">{{ $cargo->nombre }}</option>
                                    @endforeach
                                </select>
                                @error('cargo_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-6">
                                <label for="e-fecha_ingreso" class="form-label">
                                    Fecha de ingreso <span class="text-danger">*</span>
                                </label>
                                <input type="date" id="e-fecha_ingreso" wire:model.live="fecha_ingreso"
                                    max="{{ now()->format('Y-m-d') }}"
                                    class="form-control @error('fecha_ingreso') is-invalid @enderror">
                                @error('fecha_ingreso')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer modal-persona-footer p-4">
                        @include('livewire.trabajadores.partials.pie-formulario', [
                            'textoBoton' => 'Guardar cambios',
                            'iconoBoton' => 'ri-save-line',
                            'metodo' => 'guardarEdicion',
                        ])
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== Modal de baja ===================== --}}
    <div class="modal fade zoomIn" id="modalBajaTrabajador" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-eliminar-icon modal-baja-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1">
                            <i class="ri-user-unfollow-line"></i>
                        </span>
                    </div>

                    <span class="modal-eliminar-etiqueta">Confirmación</span>
                    <h5 class="mb-2">¿Dar de baja a este trabajador?</h5>
                    <p class="text-muted mb-3">
                        <strong class="modal-eliminar-nombre">{{ $bajaNombre }}</strong>
                        ({{ $bajaCodigo }}) dejará de figurar como personal activo.
                    </p>

                    {{-- La ficha no se borra: el histórico de ventas y compras
                         que se implemente después seguirá apuntando a ella. --}}
                    <div class="alert alert-info alert-borderless text-start fs-13 mb-3">
                        <i class="ri-archive-line align-bottom me-1"></i>
                        <strong>No se elimina nada.</strong> La ficha se conserva con su código y su
                        historial, y podrás reincorporarlo cuando quieras.
                    </div>

                    <div class="text-start mb-4">
                        <label for="motivo_baja" class="form-label">
                            Motivo de la baja <span class="text-muted fw-normal fs-12">(opcional)</span>
                        </label>
                        <input type="text" id="motivo_baja" wire:model="motivo_baja" class="form-control"
                            placeholder="Ej. Renuncia voluntaria" maxlength="255">
                    </div>

                    <div class="alert alert-warning fs-13 py-2 px-3 text-start">
                        <i class="ri-shield-cross-line align-bottom me-1"></i>
                        Si tiene cuenta de usuario, quedará <strong>bloqueada</strong>: al intentar
                        entrar verá «Cuenta bloqueada, comunícate con el administrador». La cuenta
                        no se borra, y al reincorporarlo vuelve a habilitarse.
                    </div>

                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger modal-eliminar-btn w-100" wire:click="darDeBaja"
                            wire:loading.attr="disabled" wire:target="darDeBaja">
                            <span wire:loading.remove wire:target="darDeBaja">Sí, dar de baja</span>
                            <span wire:loading wire:target="darDeBaja">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Procesando...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Modal: crear cuenta de usuario ===================== --}}
    {{-- La cuenta se crea sobre la persona del trabajador (users.persona_id).
         El usuario y la contraseña no se escriben: salen de la convención, y el
         modal los muestra para que quede claro qué credenciales se entregan. --}}
    <div class="modal fade zoomIn" id="modalCuentaTrabajador" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-cuenta-dialog">
            <div class="modal-content border-0 modal-cuenta-content">
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="modal-cuenta-icon mx-auto mb-3">
                            <span class="avatar-title rounded-circle fs-1"><i class="ri-shield-user-line"></i></span>
                        </div>
                        <h5 class="mb-1">Crear cuenta de usuario</h5>
                        <p class="text-muted mb-0">
                            Para <strong>{{ $cuentaNombre }}</strong>
                        </p>
                    </div>

                    <div class="cuenta-credenciales mb-3">
                        <div class="cuenta-credencial">
                            <span class="cuenta-credencial-etiqueta">
                                <i class="ri-user-line"></i> Usuario
                            </span>
                            <code>{{ $cuentaUsuario }}</code>
                        </div>
                        <div class="cuenta-credencial">
                            <span class="cuenta-credencial-etiqueta">
                                <i class="ri-mail-line"></i> Correo de acceso
                            </span>
                            <code>{{ $cuentaCorreo }}</code>
                        </div>
                        <div class="cuenta-credencial">
                            <span class="cuenta-credencial-etiqueta">
                                <i class="ri-key-2-line"></i> Contraseña
                            </span>
                            <code>{{ $cuentaPassword }}</code>
                        </div>
                    </div>

                    @unless ($cuentaCorreoPropio)
                        {{-- El inicio de sesión va por correo y la persona no tiene
                             uno registrado, así que se arma uno interno. --}}
                        <div class="alert alert-warning fs-13 py-2 px-3">
                            <i class="ri-information-line align-bottom me-1"></i>
                            Esta persona no tiene correo registrado, así que se generó uno
                            interno solo para iniciar sesión. No recibe mensajes.
                        </div>
                    @endunless

                    <div class="mb-3">
                        <label for="cuentaRol" class="form-label">
                            Rol de la cuenta <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="ri-shield-keyhole-line"></i></span>
                            <select id="cuentaRol" wire:model="cuentaRol"
                                class="form-select border-start-0 @error('cuentaRol') is-invalid @enderror">
                                @foreach ($this->rolesDisponibles as $rol)
                                    <option value="{{ $rol->name }}">{{ Str::ucfirst($rol->name) }}</option>
                                @endforeach
                            </select>
                            @error('cuentaRol')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-text">Define a qué pantallas entra. Se puede cambiar luego en Usuarios.</div>
                    </div>

                    <p class="text-muted fs-12 mb-4">
                        <i class="ri-lock-line align-bottom me-1"></i>
                        La contraseña es el carnet. Pídele al trabajador que la cambie
                        en su perfil la primera vez que entre.
                    </p>

                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success w-100" wire:click="crearCuenta"
                            wire:loading.attr="disabled" wire:target="crearCuenta">
                            <span wire:loading.remove wire:target="crearCuenta">
                                <i class="ri-user-add-line align-bottom me-1"></i> Crear cuenta
                            </span>
                            <span wire:loading wire:target="crearCuenta">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Creando...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Modal: reiniciar contraseña ===================== --}}
    <div class="modal fade zoomIn" id="modalReiniciarPassword" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-cuenta-dialog">
            <div class="modal-content border-0 modal-cuenta-content">
                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <div class="modal-cuenta-icon modal-cuenta-icon-aviso mx-auto mb-3">
                            <span class="avatar-title rounded-circle fs-1"><i class="ri-lock-password-line"></i></span>
                        </div>
                        <h5 class="mb-1">¿Reiniciar la contraseña?</h5>
                        <p class="text-muted mb-0">
                            De la cuenta de <strong>{{ $cuentaNombre }}</strong>
                        </p>
                    </div>

                    <div class="cuenta-credenciales mb-3">
                        <div class="cuenta-credencial">
                            <span class="cuenta-credencial-etiqueta">
                                <i class="ri-user-line"></i> Usuario
                            </span>
                            <code>{{ $cuentaUsuario }}</code>
                        </div>
                        <div class="cuenta-credencial">
                            <span class="cuenta-credencial-etiqueta">
                                <i class="ri-mail-line"></i> Correo de acceso
                            </span>
                            <code>{{ $cuentaCorreo }}</code>
                        </div>
                        <div class="cuenta-credencial">
                            <span class="cuenta-credencial-etiqueta">
                                <i class="ri-key-2-line"></i> Nueva contraseña
                            </span>
                            <code>{{ $cuentaPassword }}</code>
                        </div>
                    </div>

                    <p class="text-muted fs-12 mb-4">
                        La contraseña vuelve a ser el carnet. La anterior deja de servir
                        de inmediato; el correo de acceso no cambia.
                    </p>

                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-warning w-100" wire:click="reiniciarPassword"
                            wire:loading.attr="disabled" wire:target="reiniciarPassword">
                            <span wire:loading.remove wire:target="reiniciarPassword">
                                <i class="ri-refresh-line align-bottom me-1"></i> Sí, reiniciar
                            </span>
                            <span wire:loading wire:target="reiniciarPassword">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Reiniciando...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
