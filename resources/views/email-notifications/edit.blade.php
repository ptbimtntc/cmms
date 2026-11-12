@extends('layouts.app')

@section('title', 'Notifikasi Email')

@section('content')
    @php
        $fullWidth = true;
        $contentMaxWidth = 'max-w-2xl';
        $statusText = fn ($s) => match ($s) { 'sent' => 'Terkirim', 'failed' => 'Gagal (akan dicoba ulang)', 'skipped' => 'Dilewati', 'sending' => 'Mengirim', default => 'Antre' };
        $statusTone = fn ($s) => match ($s) { 'sent' => 'success', 'failed' => 'danger', 'skipped' => 'neutral', default => 'info' };
    @endphp

    <div class="space-y-5" x-data="{ recipients: @js(array_values($recipients)) }">
        <x-ui.page-header title="Notifikasi Email" description="Kirim email otomatis ke daftar penerima saat Oil Audit tersimpan dengan kondisi KRITIS (input online maupun hasil sinkronisasi offline)." />

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
        @endif

        <section class="card overflow-hidden">
            <form action="{{ route('email-notifications.update') }}" method="POST" class="space-y-5 p-5 sm:p-6">
                @csrf
                @method('PUT')

                <label class="flex min-h-11 items-center gap-3 text-sm font-semibold text-text">
                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $setting->enabled)) class="h-5 w-5 rounded border-border-strong text-primary">
                    Aktifkan notifikasi email Oil Audit KRITIS
                </label>

                <x-ui.field label="Alamat Gmail pengirim" for="en-sender" error="sender_email">
                    <input id="en-sender" type="email" name="sender_email" value="{{ old('sender_email', $setting->sender_email) }}" placeholder="nama@gmail.com" autocomplete="off" @class(['input', 'is-invalid' => $errors->has('sender_email')])>
                </x-ui.field>

                <x-ui.field label="Gmail App Password" for="en-password" error="app_password"
                    hint="Bukan password Gmail biasa. Buat di akun Google (verifikasi 2 langkah wajib aktif); kebijakan Google Workspace dapat membatasinya. Disimpan terenkripsi dan tidak pernah ditampilkan kembali.">
                    <input id="en-password" type="password" name="app_password" autocomplete="new-password"
                        placeholder="{{ $hasPassword ? '•••••••••••••••• (tersimpan, kosongkan jika tidak diubah)' : '16 huruf dari Google' }}"
                        @class(['input', 'is-invalid' => $errors->has('app_password')])>
                </x-ui.field>

                <fieldset class="grid gap-2">
                    <legend class="form-label mb-1.5">Email penerima</legend>
                    <template x-for="(r, i) in recipients" :key="i">
                        <div class="flex gap-2">
                            <input type="email" name="recipients[]" x-model="recipients[i]" placeholder="penerima@perusahaan.com" :aria-label="'Email penerima ' + (i + 1)" class="input">
                            <button type="button" @click="recipients.splice(i, 1); if (!recipients.length) recipients.push('')"
                                class="btn btn-ghost min-h-11 shrink-0 text-danger hover:bg-danger-light hover:text-danger-hover" aria-label="Hapus penerima">Hapus</button>
                        </div>
                    </template>
                    <x-ui.button variant="outline" size="sm" class="justify-self-start" x-on:click="recipients.push('')">Tambah penerima</x-ui.button>
                    @error('recipients') <p class="form-error">{{ $message }}</p> @enderror
                    @foreach ($errors->get('recipients.*') as $messages)
                        <p class="form-error">{{ $messages[0] }}</p>
                    @endforeach
                </fieldset>

                <div class="flex justify-end border-t border-border pt-5">
                    <x-ui.button type="submit">Simpan</x-ui.button>
                </div>
            </form>

            <form action="{{ route('email-notifications.test') }}" method="POST" class="flex flex-col gap-2 border-t border-border bg-surface-sunken px-5 py-4 sm:flex-row sm:items-center sm:px-6">
                @csrf
                <x-ui.button type="submit" variant="outline" class="shrink-0">Kirim Email Tes</x-ui.button>
                <span class="text-xs text-text-muted">Mengirim ke penerima yang sudah tersimpan; hasilnya adalah hasil SMTP sebenarnya.</span>
            </form>
        </section>

        <section class="card space-y-3 p-5 sm:p-6" aria-labelledby="en-status">
            <h2 id="en-status" class="font-semibold text-text">Status pengiriman</h2>
            <div class="alert {{ $worker['active'] ? 'alert-success' : 'alert-danger' }}" role="status">
                <strong>Queue worker: {{ $worker['active'] ? 'AKTIF' : 'TIDAK AKTIF' }}</strong>.
                {{ $worker['message'] }}
                @if ($worker['pending'] > 0) Antrean menunggu: {{ $worker['pending'] }} job. @endif
            </div>
            @if ($setting->last_test_at)
                <p @class(['text-sm', 'text-success-hover' => $setting->last_test_ok, 'text-danger' => ! $setting->last_test_ok])>
                    Tes terakhir ({{ $setting->last_test_at->timezone(config('app.timezone'))->format('d-m-Y H:i') }}): {{ $setting->last_test_message }}
                </p>
            @else
                <p class="text-sm text-text-muted">Belum ada email tes untuk konfigurasi ini.</p>
            @endif

            @if ($recent->isEmpty())
                <p class="text-sm text-text-muted">Belum ada notifikasi KRITIS.</p>
            @else
                <ul class="divide-y divide-border rounded-control border border-border text-sm">
                    @foreach ($recent as $n)
                        <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                            <span class="font-semibold text-text">{{ $n->oilAudit?->machine_number ?? '-' }}</span>
                            <x-ui.badge :tone="$statusTone($n->status)">{{ $statusText($n->status) }}</x-ui.badge>
                            @if ($n->last_error)
                                <span class="w-full text-xs text-danger">{{ $n->last_error }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            <p class="form-hint">Pengiriman otomatis berjalan lewat queue; jalankan <code>php artisan queue:work</code>. Status worker dihitung dari sinyal terakhir worker (maks. 2 menit lalu).</p>
        </section>
    </div>
@endsection
