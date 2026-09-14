<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'FreeDOMS') : config('app.name', 'FreeDOMS') }}
</title>



<link rel="icon" href="{{ asset('FreeDOMS.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('FreeDOMS.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

{{-- FreeDOMS offline-first PWA/app-shell foundation (Phase 1, Task 3). --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#1565c0">
<meta name="app-user-id" content="{{ auth()->id() }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
