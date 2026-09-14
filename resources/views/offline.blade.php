<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offline - {{ config('app.name', 'FreeDOMS') }}</title>
    <link rel="icon" href="{{ asset('FreeDOMS.ico') }}" sizes="any">

    {{--
        Deliberately self-contained (no @vite, no external CDN, no auth
        state) — this is the Service Worker's offline navigation fallback
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
            font-size: 40px;
            line-height: 1;
            margin-bottom: 16px;
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
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            background: #2563eb;
            color: #fff;
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="card">
        <div class="icon">&#128268;</div>
        <h1>Anda sedang offline</h1>
        <p>{{ config('app.name', 'FreeDOMS') }} tidak dapat memuat halaman ini karena tidak ada koneksi internet. Periksa koneksi Anda lalu coba lagi.</p>
        <button type="button" onclick="window.location.reload()">Coba Lagi</button>
    </div>
</body>

</html>
