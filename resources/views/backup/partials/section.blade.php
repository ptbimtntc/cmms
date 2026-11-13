@php
    [$statusText, $statusTone] = match ($section['last_run']?->status) {
        \App\Models\BackupRun::STATUS_SUCCEEDED => ['Succeeded', 'success'],
        \App\Models\BackupRun::STATUS_FAILED => ['Failed', 'danger'],
        \App\Models\BackupRun::STATUS_RUNNING => ['Running', 'info'],
        default => ['Never run', 'neutral'],
    };
    $formatTime = fn ($time) => $time?->timezone(config('app.timezone'))->format('d-m-Y H:i') ?? 'Never';
    $runTone = fn ($run) => match ($run->status) { \App\Models\BackupRun::STATUS_SUCCEEDED => 'success', \App\Models\BackupRun::STATUS_FAILED => 'danger', default => 'info' };
    $runText = fn ($run) => match ($run->status) { \App\Models\BackupRun::STATUS_SUCCEEDED => 'Succeeded', \App\Models\BackupRun::STATUS_FAILED => 'Failed', default => 'Running' };
@endphp

<section class="card overflow-hidden" aria-label="{{ $title }}">
    <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
        <div>
            <h2 class="text-lg font-semibold text-text">{{ $title }}</h2>
            <p class="mt-1 text-sm text-text-muted">{{ $description }}</p>
        </div>
        <form action="{{ $action }}" method="POST" class="shrink-0" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <x-ui.button type="submit" class="w-full sm:w-auto" x-bind:disabled="busy">{{ $buttonLabel }}</x-ui.button>
        </form>
    </div>

    <dl class="grid gap-px border-y border-border bg-border text-sm sm:grid-cols-3">
        <div class="bg-surface p-4">
            <dt class="text-xs text-text-muted">Last backup created</dt>
            <dd class="mt-1 font-semibold text-text">{{ $formatTime($section['last_success']?->finished_at) }}</dd>
        </div>
        <div class="bg-surface p-4">
            <dt class="text-xs text-text-muted">Last download started</dt>
            <dd class="mt-1 font-semibold text-text">{{ $formatTime($section['last_download']?->download_started_at) }}</dd>
            <dd class="mt-1 text-xs text-text-muted">When the server started sending the file. It does not confirm the file reached the device.</dd>
        </div>
        <div class="bg-surface p-4">
            <dt class="text-xs text-text-muted">Last status</dt>
            <dd class="mt-1"><x-ui.badge :tone="$statusTone">{{ $statusText }}</x-ui.badge></dd>
            @if ($section['last_run']?->status === \App\Models\BackupRun::STATUS_FAILED)
                <dd class="mt-1 text-xs text-danger">{{ $section['last_run']->error_message }}</dd>
            @elseif ($section['last_run']?->drive_error)
                <dd class="mt-1 text-xs text-danger">Google Drive copy failed: {{ $section['last_run']->drive_error }}</dd>
            @endif
        </div>
    </dl>

    <div class="space-y-4 p-5 sm:p-6">
        @if ($section['downloadable'])
            <div class="alert alert-success flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <span class="break-all">Latest file: <strong>{{ $section['downloadable']->file_name }}</strong> ({{ $section['downloadable']->sizeLabel() }})</span>
                <x-ui.button variant="outline" class="shrink-0" href="{{ route('backup.download', $section['downloadable']) }}">Download</x-ui.button>
            </div>
        @else
            <p class="rounded-control border border-dashed border-border-strong px-4 py-3 text-sm text-text-muted">
                No backup file is available. Create a new backup to download one.
            </p>
        @endif

        <div class="relative overflow-x-auto rounded-control border border-border">
            <table class="data-table min-w-full">
                <thead>
                    <tr>
                        <th scope="col">Time</th>
                        <th scope="col">Status</th>
                        <th scope="col">File name</th>
                        <th scope="col" class="text-right">Size</th>
                        <th scope="col">Created by</th>
                        <th scope="col">Google Drive</th>
                        <th scope="col">Last download</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section['history'] as $run)
                        <tr>
                            <td class="whitespace-nowrap">{{ $formatTime($run->finished_at ?? $run->started_at) }}</td>
                            <td><x-ui.badge :tone="$runTone($run)">{{ $runText($run) }}</x-ui.badge></td>
                            <td class="min-w-48 break-all text-text">{{ $run->file_name ?? '-' }}</td>
                            <td class="whitespace-nowrap text-right tabular-nums">{{ $run->sizeLabel() }}</td>
                            <td>{{ $run->isScheduled() ? 'Automatic' : ($run->creator?->name ?? '-') }}</td>
                            <td>
                                @if ($run->isOnDrive())
                                    <x-ui.badge tone="success">Stored</x-ui.badge>
                                @elseif ($run->drive_removed_at)
                                    <span class="text-xs text-text-muted">Removed from Drive</span>
                                @elseif ($run->drive_error)
                                    <x-ui.badge tone="danger">Failed</x-ui.badge>
                                    <span class="mt-1 block max-w-xs text-xs text-danger">{{ $run->drive_error }}</span>
                                @else
                                    <span class="text-xs text-text-muted">Not copied</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                {{ $run->download_started_at ? $formatTime($run->download_started_at).' by '.($run->downloader?->name ?? '-') : '-' }}
                            </td>
                            <td class="text-right">
                                @if ($run->isDownloadable())
                                    <x-ui.button size="sm" variant="ghost" href="{{ route('backup.download', $run) }}">Download</x-ui.button>
                                @elseif ($run->status === \App\Models\BackupRun::STATUS_SUCCEEDED)
                                    <span class="text-xs text-text-muted">File already deleted</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-6 text-center text-text-muted">No backup history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="form-hint">{{ $notes }}</p>
    </div>
</section>
