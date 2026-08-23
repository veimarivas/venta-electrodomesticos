{{--
    "personas-modulo" aporta los estilos compartidos de este tipo de módulo
    (encabezado, tabla, modales, paginación), definidos en _personas.scss.
    "clientes-modulo" queda para los ajustes propios de esta pantalla.
--}}
<div class="personas-modulo clientes-modulo">

    {{-- ===================== Encabezado del módulo ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 personas-encabezado">
        <div class="card-body p-0">
            <div class="p-4 personas-hero">
                <div class="row align-items-center g-4">
                    <div class="col-lg-8">
                        <span class="badge text-white mb-3 personas-chip">
                            <i class="ri-user-heart-line me-1"></i> Ventas · Clientes
                        </span>
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-md flex-shrink-0">
                                <span class="avatar-title bg-white bg-opacity-25 text-white rounded-3 fs-3">
                                    <i class="ri-user-heart-line"></i>
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-white mb-1">Clientes</h4>
                                <p class="text-white-50 mb-0">
                                    Quiénes compran en la tienda. Sus datos personales viven en Personas.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="d-flex flex-wrap justify-content-lg-end">
                            @can('clientes.crear')
                                <button type="button" class="btn btn-light personas-nueva-hero" wire:click="abrirCrear">
                                    <i class="ri-user-add-line align-bottom me-1"></i> Nuevo cliente
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
            <x-stat-card label="Clientes activos" value="{{ $totalActivos }}" icon="bx-user-check"
                color="primary" caption="En el listado" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Archivados" value="{{ $totalArchivados }}" icon="bx-archive"
                color="secondary" caption="Historial conservado" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Con correo" value="{{ $conCorreo }}" icon="bx-envelope"
                color="info" caption="Contactables por correo" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Altas este mes" value="{{ $altasDelMes }}" icon="bx-calendar-plus"
                color="success" caption="{{ ucfirst(now()->translatedFormat('F Y')) }}" />
        </div>
    </div>

    {{-- ===================== Listado ===================== --}}
    <div class="card border-0 shadow-sm personas-listado">
        <div class="card-header bg-transparent py-3 personas-toolbar">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Listado de clientes
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando...</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">
                        {{ $clientes->total() }}
                        {{ $clientes->total() === 1 ? 'cliente encontrado' : 'clientes encontrados' }}
                    </small>
                </div>

                <div class="col-md-5">
                    <div class="search-box">
                        <input type="text" class="form-control personas-busqueda"
                            placeholder="Buscar por código, nombre, carnet o correo..."
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

                {{-- Filtro de estado: los archivados siguen consultables --}}
                <div class="col-md-3">
                    <div class="btn-group w-100 filtro-estado" role="group" aria-label="Filtrar por estado">
                        @foreach (['activos' => 'Activos', 'archivados' => 'Archivados', 'todos' => 'Todos'] as $valor => $etiqueta)
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
                    <thead>
                        <tr class="text-uppercase fs-11 text-muted">
                            <th scope="col" class="ps-4" role="button" wire:click="ordenar('codigo')">
                                Cliente <x-sort-icon :field="'codigo'" :current="$ordenarPor" :direction="$direccionOrden" />
                            </th>
                            <th scope="col">Contacto</th>
                            <th scope="col" role="button" wire:click="ordenar('created_at')">
                                Alta <x-sort-icon :field="'created_at'" :current="$ordenarPor" :direction="$direccionOrden" />
                            </th>
                            <th scope="col">Estado</th>
                            <th scope="col" class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($clientes as $cliente)
                            <tr wire:key="cliente-{{ $cliente->id }}"
                                class="{{ $cliente->trashed() ? 'fila-dado-de-baja' : '' }}">
                                <td class="ps-4 col-trabajador">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="avatar-xs flex-shrink-0">
                                            <span
                                                class="avatar-title rounded-circle bg-{{ $cliente->persona->color_avatar }}-subtle text-{{ $cliente->persona->color_avatar }} fw-semibold">
                                                {{ $cliente->persona->iniciales }}
                                            </span>
                                        </div>
                                        <div class="min-w-0">
                                            <h6 class="mb-0 text-truncate">{{ $cliente->persona->nombre_completo }}</h6>
                                            <small class="text-muted d-flex align-items-center gap-2">
                                                <span class="badge fs-11 col-codigo {{ $cliente->trashed() ? 'bg-secondary-subtle text-secondary' : 'bg-primary-subtle text-primary' }}">
                                                    {{ $cliente->codigo }}
                                                </span>
                                                <span class="text-truncate">CI {{ $cliente->persona->carnet }}</span>
                                            </small>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    @if ($cliente->persona->celular)
                                        <div class="text-truncate">
                                            <i class="ri-phone-line align-bottom me-1 text-muted"></i>
                                            {{ $cliente->persona->celular }}
                                        </div>
                                    @endif
                                    @if ($cliente->persona->correo)
                                        <small class="text-muted text-truncate d-block">
                                            <i class="ri-mail-line align-bottom me-1"></i>
                                            {{ $cliente->persona->correo }}
                                        </small>
                                    @endif
                                    @if (! $cliente->persona->celular && ! $cliente->persona->correo)
                                        <span class="text-muted">Sin datos de contacto</span>
                                    @endif
                                </td>

                                <td>
                                    <div>{{ $cliente->created_at->format('d/m/Y') }}</div>
                                    <small class="text-muted">{{ $cliente->created_at->diffForHumans() }}</small>
                                </td>

                                <td>
                                    @if ($cliente->trashed())
                                        <span class="badge bg-secondary-subtle text-secondary fs-12">
                                            <i class="ri-archive-line align-bottom me-1"></i> Archivado
                                        </span>
                                        <div class="fs-11 text-muted mt-1">
                                            {{ $cliente->deleted_at->format('d/m/Y') }}
                                        </div>
                                    @else
                                        <span class="badge bg-success-subtle text-success fs-12">
                                            <i class="ri-checkbox-circle-line align-bottom me-1"></i> Activo
                                        </span>
                                    @endif
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        @if ($cliente->trashed())
                                            @can('clientes.editar')
                                                <button type="button" class="btn btn-sm btn-soft-success"
                                                    wire:click="restaurar({{ $cliente->id }})"
                                                    aria-label="Restaurar a {{ $cliente->persona->nombres }}">
                                                    <i class="ri-inbox-unarchive-line align-bottom me-1"></i> Restaurar
                                                </button>
                                            @endcan
                                        @else
                                            @can('clientes.eliminar')
                                                <button type="button"
                                                    class="btn btn-sm btn-ghost-danger btn-icon rounded-circle persona-accion-eliminar"
                                                    wire:click="confirmarArchivar({{ $cliente->id }})"
                                                    title="Archivar cliente"
                                                    aria-label="Archivar a {{ $cliente->persona->nombres }}">
                                                    <i class="ri-archive-line fs-16"></i>
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
                                                <i class="{{ $buscar !== '' ? 'ri-search-eye-line' : 'ri-user-heart-line' }}"></i>
                                            </span>
                                        </div>

                                        @if ($buscar !== '')
                                            <h5 class="mb-1">Sin resultados para «{{ $buscar }}»</h5>
                                            <p class="text-muted mb-3">Revisa la ortografía o prueba con menos palabras.</p>
                                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                                wire:click="$set('buscar', '')">
                                                <i class="ri-close-line align-bottom me-1"></i> Limpiar búsqueda
                                            </button>
                                        @elseif ($filtroEstado === 'activos' && $totalArchivados > 0)
                                            {{-- Con fichas archivadas, decir "todavía no hay clientes"
                                                 sería falso y haría pensar que se perdieron. --}}
                                            <h5 class="mb-1">Ningún cliente activo</h5>
                                            <p class="text-muted mb-3">
                                                {{ $totalArchivados }}
                                                {{ $totalArchivados === 1 ? 'ficha está archivada' : 'fichas están archivadas' }}
                                                y {{ $totalArchivados === 1 ? 'se conserva' : 'se conservan' }} con su historial.
                                            </p>
                                            <button type="button" class="btn btn-soft-secondary btn-sm"
                                                wire:click="$set('filtroEstado', 'archivados')">
                                                <i class="ri-eye-line align-bottom me-1"></i> Ver los archivados
                                            </button>
                                        @else
                                            <h5 class="mb-1">Todavía no hay clientes registrados</h5>
                                            <p class="text-muted mb-3">
                                                Busca a la persona y regístrala como cliente.
                                            </p>
                                            @can('clientes.crear')
                                                <button type="button" class="btn btn-success btn-sm" wire:click="abrirCrear">
                                                    <i class="ri-user-add-line align-bottom me-1"></i> Registrar cliente
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

        @if ($clientes->total() > 0)
            <div class="card-footer bg-transparent border-top-dashed">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <p class="text-muted mb-0 fs-13">
                        Mostrando <span class="fw-semibold">{{ $clientes->firstItem() }}</span>–<span
                            class="fw-semibold">{{ $clientes->lastItem() }}</span>
                        de <span class="fw-semibold">{{ $clientes->total() }}</span> clientes
                    </p>

                    @if ($clientes->hasPages())
                        <div class="paginacion-compacta">
                            {{ $clientes->onEachSide(1)->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- ===================== Modal de alta (2 pasos) ===================== --}}
    <div class="modal fade" id="modalCliente" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-persona-dialog">
            <div class="modal-content border-0 modal-persona-content">
                <div class="modal-header modal-persona-header p-4">
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
                                    Registrar como cliente
                                @else
                                    Registrar persona y hacerla cliente
                                @endif
                            </h5>
                            <small>
                                @if ($paso === 'buscar')
                                    Primero verifica si ya está registrada en el sistema.
                                @elseif ($paso === 'asignar')
                                    La persona ya existe: solo falta confirmar.
                                @else
                                    No estaba registrada: completa sus datos.
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
                            {{ $paso === 'buscar' ? 'Paso 1 de 2 · Localizar a la persona' : 'Paso 2 de 2 · Confirmar el alta' }}
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
                                <p class="text-muted mb-3">Puedes darla de alta y registrarla como cliente en un solo paso.</p>
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
                                            @if ($persona->cliente)
                                                {{-- Ya tiene ficha: no se puede registrar dos veces --}}
                                                <span class="badge bg-secondary-subtle text-secondary">
                                                    <i class="ri-check-line align-bottom me-1"></i>
                                                    Ya es cliente
                                                </span>
                                                <div class="text-muted fs-11 mt-1 text-end">
                                                    {{ $persona->cliente->codigo }}
                                                </div>
                                            @else
                                                <button type="button" class="btn btn-sm btn-success"
                                                    wire:click="seleccionarPersona({{ $persona->id }})">
                                                    <i class="ri-user-follow-line align-bottom me-1"></i> Registrar
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

                            {{-- La ficha de cliente solo tiene el código, y lo genera el
                                 sistema: no hay nada más que completar. --}}
                            <h6 class="modal-section-title mb-3"><i class="ri-price-tag-3-line"></i> Ficha de cliente</h6>

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

                            <p class="text-muted fs-13 mt-3 mb-0">
                                <i class="ri-information-line align-bottom me-1"></i>
                                Los datos personales se corrigen desde el módulo de Personas: aquí
                                se comparten, no se duplican.
                            </p>
                        </div>

                        <div class="modal-footer modal-persona-footer p-4">
                            @include('livewire.clientes.partials.pie-formulario', [
                                'textoBoton' => 'Registrar como cliente',
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

                            @include('livewire.clientes.partials.campos-persona')

                            <h6 class="modal-section-title mt-4 mb-3"><i class="ri-price-tag-3-line"></i> Ficha de cliente</h6>

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

                        <div class="modal-footer modal-persona-footer p-4">
                            @include('livewire.clientes.partials.pie-formulario', [
                                'textoBoton' => 'Registrar cliente',
                                'iconoBoton' => 'ri-user-add-line',
                                'metodo' => 'registrarPersonaYAsignar',
                            ])
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- ===================== Modal de archivado ===================== --}}
    <div class="modal fade zoomIn" id="modalArchivarCliente" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-eliminar-dialog">
            <div class="modal-content border-0 modal-eliminar-content">
                <div class="modal-body modal-eliminar-body p-4 text-center">
                    <div class="modal-baja-icon modal-eliminar-icon mx-auto mb-4">
                        <span class="avatar-title rounded-circle fs-1"><i class="ri-archive-line"></i></span>
                    </div>

                    <h5 class="mb-2">¿Archivar este cliente?</h5>
                    <p class="text-muted mb-4">
                        <strong class="modal-eliminar-nombre">{{ $archivarNombre }}</strong>
                        ({{ $archivarCodigo }}) sale del listado activo. Su ficha y sus datos
                        se conservan, y puedes restaurarla cuando quieras.
                    </p>

                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light modal-cancelar w-100" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-danger modal-eliminar-btn w-100" wire:click="archivar"
                            wire:loading.attr="disabled" wire:target="archivar">
                            <span wire:loading.remove wire:target="archivar">Sí, archivar</span>
                            <span wire:loading wire:target="archivar">
                                <span class="spinner-border spinner-border-sm me-1" role="status"></span>
                                Archivando...
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
