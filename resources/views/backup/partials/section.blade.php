@php
    $statusLabel = match ($section['last_run']?->status) {
        \App\Models\BackupRun::STATUS_SUCCEEDED => ['Berhasil', 'text-emerald-700'],
        \App\Models\BackupRun::STATUS_FAILED => ['Gagal', 'text-red-600'],
        \App\Models\BackupRun::STATUS_RUNNING => ['Sedang berjalan', 'text-blue-600'],
        default => ['Belum pernah dijalankan', 'text-slate-500'],
    };
    $formatTime = fn ($time) => $time?->timezone(config('app.timezone'))->format('d-m-Y H:i') ?? 'Belum pernah';
@endphp

<section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-lg font-semibold text-slate-800">{{ $title }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $description }}</p>
        </div>
        <form action="{{ $action }}" method="POST" class="shrink-0">
            @csrf
            <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">{{ $buttonLabel }}</button>
        </form>
    </div>

    <dl class="grid gap-4 text-sm sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Backup terakhir dibuat</dt>
            <dd class="mt-1 font-medium text-slate-800">{{ $formatTime($section['last_success']?->finished_at) }}</dd>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Download terakhir dimulai</dt>
            <dd class="mt-1 font-medium text-slate-800">{{ $formatTime($section['last_download']?->download_started_at) }}</dd>
            <dd class="mt-1 text-xs text-slate-500">Waktu server mulai mengirim file. Tidak memastikan file sudah tersimpan di perangkat.</dd>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status terakhir</dt>
            <dd class="mt-1 font-medium {{ $statusLabel[1] }}">{{ $statusLabel[0] }}</dd>
            @if ($section['last_run']?->status === \App\Models\BackupRun::STATUS_FAILED)
                <dd class="mt-1 text-xs text-red-600">{{ $section['last_run']->error_message }}</dd>
            @elseif ($section['last_run']?->drive_error)
                <dd class="mt-1 text-xs text-red-600">Salinan Google Drive gagal: {{ $section['last_run']->drive_error }}</dd>
            @endif
        </div>
    </dl>

    @if ($section['downloadable'])
        <div class="flex flex-col gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 sm:flex-row sm:items-center sm:justify-between">
            <span>File terbaru: <strong>{{ $section['downloadable']->file_name }}</strong> ({{ $section['downloadable']->sizeLabel() }})</span>
            <a href="{{ route('backup.download', $section['downloadable']) }}" class="rounded-xl border border-emerald-300 bg-white px-4 py-2 text-center font-medium text-emerald-800 transition hover:bg-emerald-100">Unduh</a>
        </div>
    @else
        <p class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            Tidak ada file backup yang tersedia. Buat backup baru untuk mengunduhnya.
        </p>
    @endif

    <div class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-3 py-2 font-medium">Waktu</th>
                    <th class="px-3 py-2 font-medium">Status</th>
                    <th class="px-3 py-2 font-medium">Nama file</th>
                    <th class="px-3 py-2 font-medium">Ukuran</th>
                    <th class="px-3 py-2 font-medium">Dibuat oleh</th>
                    <th class="px-3 py-2 font-medium">Google Drive</th>
                    <th class="px-3 py-2 font-medium">Download terakhir</th>
                    <th class="px-3 py-2 font-medium"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($section['history'] as $run)
                    <tr>
                        <td class="whitespace-nowrap px-3 py-2 text-slate-700">{{ $formatTime($run->finished_at ?? $run->started_at) }}</td>
                        <td class="px-3 py-2 {{ $run->status === \App\Models\BackupRun::STATUS_FAILED ? 'text-red-600' : ($run->status === \App\Models\BackupRun::STATUS_SUCCEEDED ? 'text-emerald-700' : 'text-blue-600') }}">
                            {{ match ($run->status) { \App\Models\BackupRun::STATUS_SUCCEEDED => 'Berhasil', \App\Models\BackupRun::STATUS_FAILED => 'Gagal', default => 'Berjalan' } }}
                        </td>
                        <td class="px-3 py-2 text-slate-700">{{ $run->file_name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-3 py-2 text-slate-700">{{ $run->sizeLabel() }}</td>
                        <td class="px-3 py-2 text-slate-700">{{ $run->isScheduled() ? 'Otomatis' : ($run->creator?->name ?? '—') }}</td>
                        <td class="px-3 py-2">
                            @if ($run->isOnDrive())
                                <span class="text-emerald-700">Tersimpan</span>
                            @elseif ($run->drive_removed_at)
                                <span class="text-xs text-slate-500">Dihapus dari Drive</span>
                            @elseif ($run->drive_error)
                                <span class="text-red-600">Gagal</span>
                                <span class="block max-w-xs text-xs text-red-600">{{ $run->drive_error }}</span>
                            @else
                                <span class="text-xs text-slate-500">Tidak disalin</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-3 py-2 text-slate-700">
                            {{ $run->download_started_at ? $formatTime($run->download_started_at).' oleh '.($run->downloader?->name ?? '—') : '—' }}
                        </td>
                        <td class="px-3 py-2 text-right">
                            @if ($run->isDownloadable())
                                <a href="{{ route('backup.download', $run) }}" class="text-blue-600 hover:underline">Unduh</a>
                            @elseif ($run->status === \App\Models\BackupRun::STATUS_SUCCEEDED)
                                <span class="text-xs text-slate-400">File sudah dihapus</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-3 py-4 text-center text-slate-500">Belum ada riwayat backup.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-slate-500">{{ $notes }}</p>
</section>
