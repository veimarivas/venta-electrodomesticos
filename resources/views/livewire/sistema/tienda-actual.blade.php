<div @class(['dropdown ms-1 header-item tienda-topbar', 'd-none' => $tiendas === []])>
    @if ($tiendas !== [])
        <button type="button" class="tienda-chip" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
            aria-label="Tienda donde trabajas: {{ $tienda['nombre'] ?? 'sin elegir' }}">
            <i class="ri-store-2-line"></i>
            <span class="tienda-chip-dato">
                <small>{{ $fuente === 'asistencia' ? 'En turno' : 'Tienda' }}</small>
                <strong>{{ $tienda['nombre'] ?? 'Elegir' }}</strong>
            </span>
            @if ($puede_cambiar)
                <i class="ri-arrow-down-s-line d-none d-md-inline"></i>
            @endif
        </button>

        <div class="dropdown-menu dropdown-menu-end p-2 tienda-panel">
            <div class="px-2 pt-1 pb-2">
                <h6 class="mb-0">¿Dónde estás trabajando?</h6>
                <small class="text-muted">
                    @if ($fuente === 'asistencia')
                        Marcaste entrada en {{ $tienda['nombre'] }}: mientras dure el turno, las ventas y la caja son de ahí.
                    @else
                        Las ventas, la caja y los cobros se anotan en esta tienda.
                    @endif
                </small>
            </div>
            @foreach ($tiendas as $t)
                <button type="button" @class(['dropdown-item tienda-opcion', 'active' => ($tienda['id'] ?? null) === $t['id']])
                    wire:click="elegir({{ $t['id'] }})" @disabled(! $puede_cambiar && ($tienda['id'] ?? null) !== $t['id'])>
                    <i class="ri-store-2-line"></i> {{ $t['nombre'] }}
                    @if (($tienda['id'] ?? null) === $t['id'])
                        <i class="ri-check-line ms-auto"></i>
                    @endif
                </button>
            @endforeach
        </div>
    @endif
</div>
