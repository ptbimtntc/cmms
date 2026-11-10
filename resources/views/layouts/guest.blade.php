<!DOCTYPE html>
<html lang="@yield('lang', 'en')">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'FreeDOMS')</title>

    <link rel="icon" href="{{ asset('FreeDOMS.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('FreeDOMS.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

    {{-- FreeDOMS offline-first PWA/app-shell foundation (Phase 1, Task 3). --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#0369a1">
    <meta name="app-user-id" content="{{ auth()->id() }}">
    <link rel="shortcut icon" href="{{ asset('FreeDOMS.ico') }}">

    @stack('head')

    {{-- Self-hosted via the Vite fonts plugin; Barlow is the DIN-style signage face used on the landing and login pages. --}}
    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

</head>

<body class="bg-slate-50 text-slate-800">

    @yield('content')

</body>

</html>
