{{--
    Renderiza un ítem del menú lateral de forma recursiva.
    Recibe: $item (array normalizado por MenuBuilder) y $level (profundidad).
--}}

@if (($item['type'] ?? 'link') === 'title')
    <li class="menu-title"><span>{{ $item['label'] }}</span></li>
@elseif (!empty($item['children']))
    @php $collapseId = $item['id']; @endphp
    <li class="nav-item">
        <a class="nav-link menu-link {{ $item['active'] ? '' : 'collapsed' }}" href="#{{ $collapseId }}"
            data-bs-toggle="collapse" role="button" aria-expanded="{{ $item['active'] ? 'true' : 'false' }}"
            aria-controls="{{ $collapseId }}">
            @if (!empty($item['icon']))
                <i class="{{ $item['icon'] }}"></i>
            @endif
            <span>{{ $item['label'] }}</span>
            @if (!empty($item['badge']))
                <span class="badge {{ $item['badge']['class'] ?? 'bg-primary' }} ms-auto">{{ $item['badge']['text'] }}</span>
            @endif
        </a>
        <div class="collapse menu-dropdown {{ $item['active'] ? 'show' : '' }}" id="{{ $collapseId }}">
            <ul class="nav nav-sm flex-column">
                @foreach ($item['children'] as $child)
                    @include('backend.layouts.partials.menu-item', ['item' => $child, 'level' => $level + 1])
                @endforeach
            </ul>
        </div>
    </li>
@else
    <li class="nav-item">
        <a href="{{ $item['url'] }}" class="nav-link {{ $level === 0 ? 'menu-link' : '' }} {{ $item['active'] ? 'active' : '' }}">
            @if (!empty($item['icon']))
                <i class="{{ $item['icon'] }}"></i>
            @endif
            <span>{{ $item['label'] }}</span>
            @if (!empty($item['badge']))
                <span class="badge {{ $item['badge']['class'] ?? 'bg-primary' }} ms-auto">{{ $item['badge']['text'] }}</span>
            @endif
        </a>
    </li>
@endif
