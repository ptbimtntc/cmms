@extends('layouts.guest')

@section('title', 'Scan Machine QR · FreeDOMS')

@section('content')
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <div class="min-h-dvh bg-bg text-text">
        <div class="mx-auto max-w-xl px-4 py-8 sm:px-6 sm:py-12">
            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('home') }}" class="btn btn-ghost -ml-2 min-h-11">Kembali ke Beranda</a>
                <x-brand-logo :framed="false" class="h-6 w-auto" aria-hidden="true" />
            </div>

            <div class="mt-8 text-center">
                <h1 class="font-signage text-4xl font-semibold text-text">Scan Machine QR</h1>
                <p class="mt-2 text-text-muted">Point the camera at the QR code on the machine.</p>
            </div>

            <div id="reader" class="mx-auto mt-8 aspect-square w-full overflow-hidden rounded-card bg-neutral-900 shadow-popover"></div>

            <div class="mt-6 grid gap-3">
                <button id="startScanner" type="button" class="btn btn-primary btn-lg min-h-12 w-full">Aktifkan Kamera</button>
                <p id="scannerStatus" class="text-center text-sm text-text-muted" role="status" aria-live="polite"></p>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('startScanner').addEventListener('click', function () {
            const button = this;
            const status = document.getElementById('scannerStatus');
            const scanner = new Html5Qrcode('reader');

            button.disabled = true;
            status.textContent = 'Membuka kamera…';

            scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: 250 },
                function (decodedText) {
                    scanner.stop();
                    window.location.href = '/m/' + decodedText;
                },
                function () {
                    // ignore per-frame scan misses
                }
            ).then(function () {
                status.textContent = 'Camera on. Point it at the machine QR code.';
            }).catch(function () {
                // Camera refused or unavailable: say so and let the user try again.
                button.disabled = false;
                status.textContent = 'The camera could not open. Allow camera access in the browser, then try again.';
                status.className = 'text-center text-sm font-medium text-danger';
            });
        });
    </script>
@endsection
