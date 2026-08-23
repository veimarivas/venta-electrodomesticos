<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', $title ?? 'Panel') | {{ config('app.name') }}</title>
<meta content="Sistema de administración de ventas" name="description" />
<meta content="{{ config('app.name') }}" name="author" />

<!-- App favicon -->
<link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}">
