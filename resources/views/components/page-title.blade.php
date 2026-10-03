@props([
    'title' => '',
    'breadcrumbs' => [],
])

@if ($title)
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h4 class="mb-0">{{ $title }}</h4>

                @if (count($breadcrumbs) || isset($actions))
                    <div class="page-title-right d-flex flex-wrap align-items-center gap-3">
                        @if (count($breadcrumbs))
                            <ol class="breadcrumb m-0">
                                @foreach ($breadcrumbs as $label => $url)
                                    @if ($loop->last || $url === null)
                                        <li class="breadcrumb-item active">{{ $label }}</li>
                                    @else
                                        <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                                    @endif
                                @endforeach
                            </ol>
                        @endif

                        @isset($actions)
                            <div class="page-title-actions d-flex flex-wrap align-items-center gap-2">
                                {{ $actions }}
                            </div>
                        @endisset
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
