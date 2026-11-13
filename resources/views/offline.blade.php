<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - {{ config('app.name', 'FreeDOMS') }}</title>
    <link rel="icon" href="{{ asset('FreeDOMS.ico') }}" sizes="any">

    {{--
        Deliberately self-contained (no @vite, no external CDN, no auth
        state), this is the Service Worker's offline navigation fallback
        (see public/sw.js and Task 3 section 3/20), so it must be able to
        render correctly precached, with no network and no build assets
        available. It carries no user/server data, so it is safe to
        precache and serve to any device/user.
    --}}
    <style>
        :root {
            color-scheme: light dark;
        }

        body {
            margin: 0;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: #0f172a;
                color: #e2e8f0;
            }
        }

        .card {
            max-width: 420px;
            text-align: center;
        }

        .icon {
            width: 44px;
            height: 44px;
            margin: 0 auto 16px;
            color: #0369a1;
        }

        button:focus-visible {
            outline: 2px solid #0369a1;
            outline-offset: 2px;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 8px;
        }

        p {
            margin: 0 0 20px;
            opacity: 0.8;
        }

        button {
            appearance: none;
            border: none;
            border-radius: 10px;
            min-height: 44px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            background: #0369a1;
            color: #fff;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="card">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 20h.01" /><path d="M8.5 16.43a5 5 0 0 1 7 0" /><path d="M5 12.86a10 10 0 0 1 5.17-2.69" /><path d="M19 12.86a10 10 0 0 0-2-1.46" /><path d="M2 8.82a15 15 0 0 1 4.18-2.65" /><path d="M10.66 5c4.01-.36 8.14.9 11.34 3.76" /><path d="m2 2 20 20" />
        </svg>
        <h1>You are offline</h1>
        <p>{{ config('app.name', 'FreeDOMS') }} cannot load this page without an internet connection. Check your connection, then try again.</p>
        <button type="button" onclick="window.location.reload()">Try again</button>
    </div>
</body>

</html>
