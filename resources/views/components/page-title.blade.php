@props([
    'title' => '',
    'breadcrumbs' => [],
])

@if ($title)
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                <h4 class="mb-sm-0">{{ $title }}</h4>

                @if (count($breadcrumbs))
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            @foreach ($breadcrumbs as $label => $url)
                                @if ($loop->last || $url === null)
                                    <li class="breadcrumb-item active">{{ $label }}</li>
                                @else
                                    <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                                @endif
                            @endforeach
                        </ol>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
