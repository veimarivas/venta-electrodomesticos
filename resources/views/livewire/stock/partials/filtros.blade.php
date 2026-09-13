{{--
    Cuerpo del panel de filtros del Stock Actual. Se usa tal cual en la barra
    lateral de escritorio y en el desplegable de móvil, por eso no lleva
    cabecera propia: cada contenedor pone el título y el botón de limpiar.

    Recibe $contexto (string) solo para no repetir los id de los campos entre
    las dos copias que pueden coincidir en el DOM.
--}}

{{-- Categorías --}}
<section class="stock-filtro-seccion">
    <h6 class="stock-filtro-titulo">
        <i class="ri-folder-2-line"></i> Categorías
    </h6>
    <ul class="stock-filtro-lista list-unstyled mb-0">
        @forelse ($categoriasFiltro as $opcion)
            <li>
                <button type="button"
                    class="stock-filtro-item {{ $opcion['activa'] ? 'is-activo' : '' }}"
                    style="padding-left: {{ 0.5 + $opcion['nivel'] * 0.9 }}rem;"
                    wire:click="cambiarCategoria({{ $opcion['id'] }})"
                    title="Ver el stock de {{ $opcion['nombre'] }}">
                    <i class="stock-filtro-item-icono ri-folder-{{ $opcion['activa'] ? 'open' : '3' }}-line"></i>
                    <span class="stock-filtro-item-texto">{{ $opcion['nombre'] }}</span>
                    <span class="stock-filtro-item-total">{{ $opcion['total'] }}</span>
                </button>
            </li>
        @empty
            <li class="stock-filtro-vacio">Sin categorías con productos.</li>
        @endforelse
    </ul>
</section>

{{-- Marcas --}}
<section class="stock-filtro-seccion">
    <h6 class="stock-filtro-titulo">
        <i class="ri-trademark-line"></i> Marcas
        @if (count($marcasFiltro) > 0)
            <span class="stock-filtro-contador">{{ count($marcasFiltro) }}</span>
        @endif
    </h6>

    <div class="search-box search-box-sm stock-buscador-filtro mb-2">
        <input type="text" class="form-control" placeholder="Buscar marca..."
            wire:model.live.debounce.300ms="buscarMarca">
        <i class="ri-search-line search-icon"></i>
    </div>

    <div class="stock-filtro-checks">
        @forelse ($marcasFiltroLista as $marca)
            <label class="stock-check" for="marca-{{ $contexto }}-{{ $marca['id'] }}">
                <input class="form-check-input" type="checkbox" value="{{ $marca['id'] }}"
                    id="marca-{{ $contexto }}-{{ $marca['id'] }}"
                    {{ $marca['activa'] ? 'checked' : '' }}
                    wire:change="toggleMarca({{ $marca['id'] }})">
                <span class="stock-check-texto">{{ $marca['nombre'] }}</span>
                <span class="stock-check-total">{{ $marca['total'] }}</span>
            </label>
        @empty
            <p class="stock-filtro-vacio mb-0">Sin marcas con ese nombre.</p>
        @endforelse
    </div>
</section>
