@props([
    'title' => null,
    'subtitle' => null,
    'bodyClass' => '',
])

<div {{ $attributes->merge(['class' => 'card']) }}>
    @if ($title || isset($actions))
        <div class="card-header align-items-center d-flex">
            <div class="flex-grow-1">
                @if ($title)
                    <h4 class="card-title mb-0">{{ $title }}</h4>
                @endif
                @if ($subtitle)
                    <p class="text-muted mb-0 fs-13">{{ $subtitle }}</p>
                @endif
            </div>

            @isset($actions)
                <div class="flex-shrink-0">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    <div class="card-body {{ $bodyClass }}">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="card-footer">
            {{ $footer }}
        </div>
    @endisset
</div>
