<div class="entregas-modulo">

    {{-- Los indicadores contestan lo que se pregunta al abrir la puerta:
         qué sale hoy y qué se quedó atrás. --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Para hoy" icon="bx-calendar-check" color="primary" value="{{ $this->paraHoy }}"
                caption="Programadas para el día" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Atrasadas" icon="bx-error-circle" color="danger" value="{{ $this->atrasadas }}"
                caption="Pasó su fecha y siguen abiertas" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="En ruta" icon="bx-map-pin" color="info" value="{{ $this->enRuta }}"
                caption="Salieron y no han vuelto" />
        </div>
        <div class="col-xl-3 col-md-6">
            <x-stat-card label="Sin fecha" icon="bx-time" color="warning" value="{{ $this->sinFecha }}"
                caption="Pendientes de acordar el día" />
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent py-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <h5 class="card-title mb-0 d-flex align-items-center gap-2">
                        Entregas
                        <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                            <span class="visually-hidden">Cargando..</span>
                        </span>
                    </h5>
                    <small class="text-muted fs-13">{{ $entregas->total() }}
                        {{ $entregas->total() === 1 ? 'entrega' : 'entregas' }}</small>
                </div>

                <div class="col-md-8">
                    <div class="d-flex flex-wrap gap-2 justify-content-md-end">
                        <div class="search-box flex-grow-1" style="max-width: 20rem">
                            <input type="text" class="form-control" placeholder="Dirección, cliente o venta.."
                                wire:model.live.debounce.400ms="buscar">
                            <i class="ri-search-line search-icon"></i>
                        </div>

                        <select class="form-select" style="max-width: 13rem" wire:model.live="filtro">
                            <option value="abiertas">Abiertas</option>
                            <option value="hoy">Para hoy</option>
                            <option value="atrasadas">Atrasadas</option>
                            <option value="en_ruta">En ruta</option>
                            <option value="entregadas">Entregadas</option>
                            <option value="canceladas">Canceladas</option>
                            <option value="todas">Todas</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Destino</th>
                            <th>Aparatos</th>
                            <th>Cuándo</th>
                            <th>Estado</th>
                            <th class="text-end pe-4" style="width: 1%;">
                                <span class="visually-hidden">Acciones</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entregas as $entrega)
                            <tr wire:key="entrega-{{ $entrega->id }}">
                                <td class="ps-4">
                                    <span class="fw-semibold d-block text-truncate" style="max-width: 22rem">
                                        {{ $entrega->direccion }}
                                    </span>
                                    <small class="text-muted d-block">
                                        {{ $entrega->cliente?->persona?->nombre_completo ?? 'Público general' }}
                                        · <a
                                            href="{{ route('ventas.show', $entrega->venta_id) }}">{{ $entrega->venta?->codigo }}</a>
                                    </small>
                                    @if ($entrega->referencia)
                                        <small class="text-muted d-block fst-italic">{{ $entrega->referencia }}</small>
                                    @endif
                                    @if ($entrega->telefono_contacto)
                                        <small class="text-muted d-block">
                                            <i class="ri-phone-line align-bottom"></i>
                                            {{ $entrega->telefono_contacto }}
                                        </small>
                                    @endif
                                </td>

                                <td>
                                    <small class="d-block">
                                        {{ $entrega->detalles->map(fn($d) => $d->ventaDetalle?->producto?->nombre)->filter()->join(', ') ?: '—' }}
                                    </small>
                                    @if ($entrega->con_instalacion)
                                        <span class="badge bg-info-subtle text-info mt-1">
                                            <i class="ri-tools-line align-bottom"></i> Con instalación
                                        </span>
                                    @endif
                                </td>

                                <td>
                                    @if ($entrega->programada_para)
                                        <span class="d-block">{{ $entrega->programada_para->format('d/m/Y') }}</span>
                                    @else
                                        <span class="text-muted d-block">Sin fecha</span>
                                    @endif
                                    @if ($entrega->repartidor)
                                        <small class="text-muted">Lleva {{ $entrega->repartidor->name }}</small>
                                    @endif
                                </td>

                                <td>
                                    @if ($entrega->estado === 'entregada')
                                        <span class="badge bg-success-subtle text-success">Entregada</span>
                                        <small class="text-muted d-block">
                                            Recibió {{ $entrega->recibida_por }}
                                        </small>
                                    @elseif ($entrega->estado === 'cancelada')
                                        <span class="badge bg-secondary-subtle text-secondary">Cancelada</span>
                                    @elseif ($entrega->estado === 'fallida')
                                        <span class="badge bg-danger-subtle text-danger">No se pudo</span>
                                        <small class="text-muted d-block text-truncate" style="max-width: 14rem">
                                            {{ $entrega->motivo_fallo }}
                                        </small>
                                    @elseif ($entrega->esta_atrasada)
                                        <span class="badge bg-danger-subtle text-danger">Atrasada</span>
                                    @else
                                        <span
                                            class="badge bg-primary-subtle text-primary">{{ $estados[$entrega->estado] }}</span>
                                    @endif
                                </td>

                                <td class="text-end pe-4">
                                    @if ($puedeGestionar && $entrega->esta_abierta)
                                        <div class="d-flex gap-1 justify-content-end">
                                            @if ($entrega->estado !== 'en_ruta')
                                                <button type="button" class="btn btn-sm btn-light"
                                                    title="Despachar" wire:click="abrirDespacho({{ $entrega->id }})">
                                                    <i class="ri-truck-line"></i>
                                                </button>
                                            @endif

                                            <button type="button" class="btn btn-sm btn-light" title="Confirmar entrega"
                                                wire:click="abrirConfirmacion({{ $entrega->id }})">
                                                <i class="ri-check-double-line"></i>
                                            </button>

                                            <button type="button" class="btn btn-sm btn-light" title="No se pudo entregar"
                                                wire:click="abrirFallo({{ $entrega->id }})">
                                                <i class="ri-close-circle-line"></i>
                                            </button>

                                            <button type="button" class="btn btn-sm btn-light" title="Reprogramar"
                                                wire:click="abrirReprogramacion({{ $entrega->id }})">
                                                <i class="ri-calendar-event-line"></i>
                                            </button>

                                            <button type="button" class="btn btn-sm btn-light text-danger"
                                                title="Cancelar" wire:click="abrirCancelacion({{ $entrega->id }})">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="ri-truck-line display-6 d-block mb-2"></i>
                                    No hay entregas que mostrar con este filtro.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($entregas->hasPages())
            <div class="card-footer paginacion-compacta">
                {{ $entregas->links() }}
            </div>
        @endif
    </div>

    {{-- ===================== Despachar ===================== --}}
    <div class="modal fade" id="modalDespacharEntrega" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Despachar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="repartidor" class="form-label">¿Quién la lleva?</label>
                    <select id="repartidor" class="form-select" wire:model="repartidorId">
                        <option value="">Elige a quien reparte</option>
                        @foreach ($this->repartidores as $usuario)
                            <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                    {{-- Sin repartidor no hay a quién preguntarle dónde está el
                         aparato, que es justo lo que el cliente llama a preguntar. --}}
                    <small class="text-muted d-block mt-2">
                        Queda registrado quién salió con el aparato y a qué hora.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="despachar"
                        wire:loading.attr="disabled" wire:target="despachar">Sale ahora</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Confirmar ===================== --}}
    <div class="modal fade" id="modalConfirmarEntrega" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar entrega</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="recibida-por" class="form-label">¿Quién recibió?</label>
                        <input type="text" id="recibida-por"
                            class="form-control @error('recibidaPor') is-invalid @enderror" wire:model="recibidaPor"
                            placeholder="Nombre de quien firmó">
                        @error('recibidaPor')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        {{-- Se teclea, no se propone el nombre del cliente: casi
                             nunca recibe el titular, y un dato que solo hay que
                             aceptar deja de servir de constancia. --}}
                        <small class="text-muted d-block mt-2">
                            Es la constancia del día que el cliente diga que nunca le llegó.
                        </small>
                    </div>

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="quedo-instalado"
                            wire:model="instalada">
                        <label class="form-check-label" for="quedo-instalado">
                            Quedó instalado
                        </label>
                    </div>
                    <small class="text-muted">Solo cuenta si la entrega se pactó con instalación.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" wire:click="confirmar"
                        wire:loading.attr="disabled" wire:target="confirmar">Entregada</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== No se pudo ===================== --}}
    <div class="modal fade" id="modalFallarEntrega" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">No se pudo entregar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="motivo-fallo" class="form-label">¿Qué pasó?</label>
                    <textarea id="motivo-fallo" rows="3" class="form-control @error('motivo') is-invalid @enderror"
                        wire:model="motivo" placeholder="No había nadie, la dirección estaba mal, no cabía por la puerta..."></textarea>
                    @error('motivo')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block mt-2">
                        La entrega queda para reprogramar, con el aparato de vuelta en la tienda.
                    </small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-warning" wire:click="fallar" wire:loading.attr="disabled"
                        wire:target="fallar">Anotar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Reprogramar ===================== --}}
    <div class="modal fade" id="modalReprogramarEntrega" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Reprogramar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="nueva-fecha" class="form-label">¿Para qué día?</label>
                    <input type="date" id="nueva-fecha"
                        class="form-control @error('nuevaFecha') is-invalid @enderror" wire:model="nuevaFecha">
                    @error('nuevaFecha')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block mt-2">Déjalo en blanco para dejarla sin fecha.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" wire:click="reprogramar"
                        wire:loading.attr="disabled" wire:target="reprogramar">Reprogramar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Cancelar ===================== --}}
    <div class="modal fade" id="modalCancelarEntrega" tabindex="-1" aria-hidden="true" wire:ignore.self
        data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header">
                    <h5 class="modal-title">Cancelar la entrega</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">
                        Se cancela el envío. Los aparatos se pueden volver a programar en otra
                        entrega — la venta no se toca.
                    </p>
                    <label for="motivo-cancelacion" class="form-label">
                        Motivo <span class="text-muted">(opcional)</span>
                    </label>
                    <textarea id="motivo-cancelacion" rows="2" class="form-control" wire:model="motivo"
                        placeholder="El cliente pasa a recogerlo..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Volver</button>
                    <button type="button" class="btn btn-danger" wire:click="cancelar" wire:loading.attr="disabled"
                        wire:target="cancelar">Cancelar la entrega</button>
                </div>
            </div>
        </div>
    </div>
</div>
