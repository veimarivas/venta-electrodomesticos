@php
    use App\Support\RegistroDeAsistencia as R;
    use Illuminate\Support\Carbon;

    $hora = fn (?string $iso): string => $iso ? Carbon::parse($iso)->format('H:i') : '—';
    $exportar = fn (string $formato): string => route('asistencia.exportar', array_filter([
        'formato' => $formato, 'mes' => $mes, 'trabajador' => $userId, 'tienda' => $tiendaId,
    ]));
@endphp

<div class="asistencia-modulo">

    {{-- ===================== Encabezado ===================== --}}
    <div class="card border-0 shadow-sm overflow-hidden mb-4 crud-encabezado">
        <div class="card-body p-0">
            <div class="p-4 crud-hero">
                <div class="crud-hero-glow" aria-hidden="true"></div>
                <div class="row align-items-center g-4">
                    <div class="col-lg-7">
                        <span class="badge text-white mb-3 crud-chip">
                            <i class="ri-calendar-check-line me-1"></i> Personal · Asistencia
                        </span>
                        <h4 class="text-white mb-1">Asistencia del personal</h4>
                        <p class="text-white-50 mb-0">
                            Entradas y salidas marcadas desde el teléfono dentro de cada tienda: días, horas y atrasos del mes.
                        </p>
                    </div>
                    <div class="col-lg-5 d-flex flex-wrap gap-2 justify-content-lg-end">
                        <a href="{{ $exportar('pdf') }}" class="btn btn-light" target="_blank">
                            <i class="ri-file-pdf-2-line me-1"></i> PDF
                        </a>
                        <a href="{{ $exportar('csv') }}" class="btn btn-light">
                            <i class="ri-file-excel-2-line me-1"></i> Excel (CSV)
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===================== Filtros ===================== --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap gap-3 align-items-end">
            <div class="gastos-dia">
                <button type="button" class="btn btn-light btn-icon" wire:click="mesAnterior" title="Mes anterior" aria-label="Mes anterior">
                    <i class="ri-arrow-left-s-line"></i>
                </button>
                <input type="month" class="form-control gastos-dia-fecha" wire:model.live="mes" max="{{ now()->format('Y-m') }}" aria-label="Mes">
                <button type="button" class="btn btn-light btn-icon" wire:click="mesSiguiente" @disabled($esMesActual)
                    title="Mes siguiente" aria-label="Mes siguiente">
                    <i class="ri-arrow-right-s-line"></i>
                </button>
                <span class="gastos-dia-texto">{{ $inicio->translatedFormat('F Y') }}</span>
            </div>
            <div style="min-width: 12rem;">
                <label class="form-label fs-12 mb-1" for="a-trabajador">Trabajador</label>
                <select id="a-trabajador" class="form-select form-select-sm" wire:model.live="userId">
                    <option value="">Todos</option>
                    @foreach ($this->trabajadores as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="min-width: 12rem;">
                <label class="form-label fs-12 mb-1" for="a-tienda">Tienda</label>
                <select id="a-tienda" class="form-select form-select-sm" wire:model.live="tiendaId">
                    <option value="">Todas</option>
                    @foreach ($this->tiendas as $t)
                        <option value="{{ $t->id }}">{{ $t->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <span class="spinner-border spinner-border-sm text-primary" role="status" wire:loading.delay>
                <span class="visually-hidden">Cargando...</span>
            </span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Personas" value="{{ $totales['personas'] }}" icon="bx-group" color="primary"
                caption="Con al menos un día marcado" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Días trabajados" value="{{ $totales['dias'] }}" icon="bx-calendar-check" color="success"
                caption="{{ R::horas($totales['minutos']) }} en total" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Atrasos" value="{{ $totales['atrasos'] }}" icon="bx-time-five" color="warning"
                caption="{{ R::horas($totales['minutos_atraso']) }} acumulados" />
        </div>
        <div class="col-xl-3 col-sm-6">
            <x-stat-card label="Sin salida" value="{{ $totales['sin_salida'] }}" icon="bx-log-out" color="danger"
                caption="Días que alguien olvidó marcar" />
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if ($filas->isEmpty())
                <div class="text-center py-5 px-3">
                    <div class="crud-empty-icon mx-auto mb-3">
                        <span class="avatar-title fs-2"><i class="ri-calendar-check-line"></i></span>
                    </div>
                    <h6 class="mb-1">Sin asistencia en {{ $inicio->translatedFormat('F') }}</h6>
                    <p class="text-muted mb-0 fs-13">Nadie marcó entrada este mes con esos filtros.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0 asistencia-tabla">
                        <thead>
                            <tr class="text-uppercase fs-11 text-muted">
                                <th class="ps-4">Trabajador</th>
                                <th class="text-end">Días</th>
                                <th class="text-end">Horas</th>
                                <th class="text-end">Atrasos</th>
                                <th class="text-end pe-4">Sin salida</th>
                            </tr>
                        </thead>
                        @foreach ($filas as $f)
                            <tbody wire:key="asistencia-{{ $f['user_id'] }}">
                                <tr class="asistencia-fila" wire:click="alternar({{ $f['user_id'] }})" role="button"
                                    aria-expanded="{{ $abierto === $f['user_id'] ? 'true' : 'false' }}">
                                    <td class="ps-4">
                                        <i class="ri-arrow-{{ $abierto === $f['user_id'] ? 'down' : 'right' }}-s-line text-muted"></i>
                                        <span class="fw-semibold">{{ $f['trabajador'] }}</span>
                                    </td>
                                    <td class="text-end tabular fw-semibold">{{ $f['dias'] }}</td>
                                    <td class="text-end tabular">{{ R::horas($f['minutos']) }}</td>
                                    <td class="text-end tabular">
                                        @if ($f['atrasos'] > 0)
                                            <span class="asistencia-atraso">{{ $f['atrasos'] }}</span>
                                            <small class="d-block text-muted">{{ R::horas($f['minutos_atraso']) }}</small>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td class="text-end tabular pe-4">
                                        @if ($f['sin_salida'] > 0)
                                            <span class="asistencia-sin-salida">{{ $f['sin_salida'] }}</span>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                </tr>

                                @if ($abierto === $f['user_id'])
                                    <tr class="asistencia-detalle">
                                        <td colspan="5" class="px-4 pb-4">
                                            <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                    <thead>
                                                        <tr class="fs-11 text-muted">
                                                            <th>Día</th>
                                                            <th>Tienda</th>
                                                            <th>Entrada</th>
                                                            <th>Salida</th>
                                                            <th class="text-end">Horas</th>
                                                            <th class="text-end">Atraso</th>
                                                            <th></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($f['detalle'] as $dia)
                                                            @foreach ($dia['turnos'] as $turno)
                                                                <tr>
                                                                    <td class="text-nowrap">
                                                                        @if ($loop->first)
                                                                            {{ Carbon::parse($dia['fecha'])->translatedFormat('D d') }}
                                                                        @endif
                                                                    </td>
                                                                    <td>{{ $turno['tienda'] }}</td>
                                                                    <td class="tabular">
                                                                        {{ $hora($turno['entrada_en']) }}
                                                                        <small class="text-muted">· {{ $turno['entrada_distancia'] }} m</small>
                                                                    </td>
                                                                    <td class="tabular">
                                                                        @if ($turno['salida_en'])
                                                                            {{ $hora($turno['salida_en']) }}
                                                                            @if ($turno['corregida'])
                                                                                <span class="badge bg-info-subtle text-info" title="{{ $turno['notas'] }}">corregida</span>
                                                                            @elseif ($turno['salida_distancia'] !== null)
                                                                                <small class="text-muted">· {{ $turno['salida_distancia'] }} m</small>
                                                                            @endif
                                                                        @elseif ($turno['sin_salida'])
                                                                            <span class="asistencia-sin-salida">Sin salida</span>
                                                                        @else
                                                                            <span class="asistencia-en-turno">En turno</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-end tabular">{{ R::horas($turno['minutos']) }}</td>
                                                                    <td class="text-end tabular">
                                                                        @if (($turno['minutos_atraso'] ?? 0) > 0)
                                                                            <span class="asistencia-atraso">{{ $turno['minutos_atraso'] }} min</span>
                                                                        @else
                                                                            <span class="text-muted">—</span>
                                                                        @endif
                                                                    </td>
                                                                    <td class="text-end">
                                                                        @if (! $turno['salida_en'] || $turno['corregida'])
                                                                            <button type="button" class="btn btn-sm btn-soft-secondary"
                                                                                wire:click.stop="abrirCorreccion({{ $turno['id'] }})">
                                                                                {{ $turno['salida_en'] ? 'Cambiar salida' : 'Poner salida' }}
                                                                            </button>
                                                                        @endif
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- ===================== Modal corregir salida ===================== --}}
    <div class="modal fade" id="modalCorregirAsistencia" tabindex="-1" aria-hidden="true" wire:ignore.self>
        <div class="modal-dialog modal-dialog-centered modal-crud-dialog">
            <div class="modal-content border-0 modal-crud-content">
                <div class="modal-header modal-crud-header p-4">
                    <div class="modal-crud-header-glow" aria-hidden="true"></div>
                    <div>
                        <h5 class="modal-title mb-0">Poner la salida</h5>
                        @if ($corregir)
                            <small class="text-muted">
                                {{ $corregir->user?->name }} · {{ $corregir->fecha->translatedFormat('l d \d\e F') }}
                                · entró {{ $corregir->entrada_en->format('H:i') }} en {{ $corregir->tienda?->nombre }}
                            </small>
                        @endif
                    </div>
                    <button type="button" class="btn-close modal-crud-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form wire:submit="corregir">
                    <div class="modal-body modal-crud-body p-4">
                        <div class="mb-3">
                            <label for="a-hora" class="form-label">Hora de salida</label>
                            <input type="time" id="a-hora" wire:model="horaSalida"
                                class="form-control @error('horaSalida') is-invalid @enderror">
                            @error('horaSalida') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label for="a-motivo" class="form-label">Motivo</label>
                            <input type="text" id="a-motivo" maxlength="255" wire:model="motivo"
                                class="form-control @error('motivo') is-invalid @enderror"
                                placeholder="Ej. Olvidó marcar; confirmado por el supervisor">
                            @error('motivo') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <small class="text-muted d-block mt-2">Queda anotado que la salida la puso {{ auth()->user()->name }}.</small>
                    </div>
                    <div class="modal-footer modal-crud-footer p-4">
                        <div class="d-flex gap-2 ms-auto">
                            <button type="button" class="btn btn-light modal-cancelar" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-success modal-guardar">Guardar salida</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
