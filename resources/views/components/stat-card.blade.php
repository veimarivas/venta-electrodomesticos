@props([
    'label' => '',
    'value' => '0',
    'icon' => 'bx-dollar-circle',
    'color' => 'success',
    'trend' => null,
    'caption' => null,
    'id' => null,
])

@php
    $trendUp = $trend !== null && $trend >= 0;

    $iconColorMap = [
        'primary' => ['color' => '#0f766e', 'bg' => 'rgba(15, 118, 110, .1)'],
        'success' => ['color' => '#1baf7a', 'bg' => 'rgba(27, 175, 122, .1)'],
        'danger'  => ['color' => '#e34948', 'bg' => 'rgba(227, 73, 72, .1)'],
        'info'    => ['color' => '#2a78d6', 'bg' => 'rgba(42, 120, 214, .1)'],
        'warning' => ['color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, .1)'],
    ];
    $iconStyle = $iconColorMap[$color] ?? $iconColorMap['primary'];
@endphp

<div {{ $attributes->merge(['class' => 'card dash-kpi h-100']) }}>
    <div class="card-body">
        <div class="d-flex align-items-start justify-content-between">
            <div class="min-w-0 flex-grow-1 overflow-hidden">
                <span class="dash-kpi-label">{{ $label }}</span>
                <span class="dash-kpi-valor" @if ($id) id="{{ $id }}" @endif>{{ $value }}</span>
                @if ($caption)
                    <span class="dash-kpi-nota">{{ $caption }}</span>
                @endif
            </div>
            @if ($trend !== null)
                <div class="flex-shrink-0 ms-2">
                    <span class="badge {{ $trendUp ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} fw-semibold fs-12">
                        <i class="ri-arrow-{{ $trendUp ? 'up' : 'down' }}-line align-middle"></i>
                        {{ $trendUp ? '+' : '' }}{{ number_format($trend, 1) }}%
                    </span>
                </div>
            @endif
            <span class="dash-kpi-icono flex-shrink-0 ms-3"
                  style="color: {{ $iconStyle['color'] }}; background: {{ $iconStyle['bg'] }};">
                <i class="bx {{ $icon }}"></i>
            </span>
        </div>
    </div>
</div>
