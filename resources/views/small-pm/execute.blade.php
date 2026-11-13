@extends('layouts.app')

@php
    $fullWidth = true;
    $taskLabel = \App\Models\SmallPmRow::TASK_TYPES[$schedule->task_type] ?? $schedule->task_type;
@endphp

@section('content')
    <div class="mx-auto max-w-3xl space-y-5">
        <x-ui.page-header title="Execute Small PM" :description="$taskLabel . ' · ' . ($schedule->row->name ?? '-') . '. Fill in the action date and remarks.'">
            <x-slot:actions>
                <x-ui.button variant="outline" href="{{ route('small-pm.index', ['tab' => $tab]) }}">Kembali</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($next)
            <x-ui.alert tone="info">The next schedule is already created: plan date {{ $next->plan_date->format('d M Y') }} ({{ $next->status }}).</x-ui.alert>
        @endif

        <section class="card p-5" aria-labelledby="spm-summary-heading">
            <h2 id="spm-summary-heading" class="sr-only">Schedule</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
                <div class="col-span-2 sm:col-span-1">
                    <dt class="text-xs text-text-muted">Row</dt>
                    <dd class="font-signage text-2xl font-semibold text-text">{{ $schedule->row->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Order Number</dt>
                    <dd class="mt-1 font-medium text-text">{{ $schedule->order_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Cycle</dt>
                    <dd class="mt-1 font-medium text-text">{{ $schedule->cycle }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Plan Date</dt>
                    <dd class="mt-1 font-medium text-text">{{ $schedule->plan_date->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Due Date</dt>
                    <dd class="mt-1 font-medium text-text">{{ $schedule->due_date->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Current Status</dt>
                    <dd class="mt-1">@include('small-pm._status', ['status' => $schedule->status])</dd>
                </div>
            </dl>

            <div class="mt-4 border-t border-border pt-4">
                <p class="text-xs text-text-muted">Machines in this row ({{ $machines->count() }})</p>
                @if ($machines->isEmpty())
                    <p class="mt-1 text-sm text-text-muted">This row has no machines yet.</p>
                @else
                    <ul class="mt-2 flex flex-wrap gap-1.5">
                        @foreach ($machines as $m)
                            <li class="rounded-control bg-surface-muted px-2 py-1 text-xs font-medium text-text" title="{{ $m->machine_type }}">{{ $m->machine_number }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <form action="{{ route('small-pm.execute.store', $schedule) }}" method="POST" class="card space-y-5 p-5 sm:p-6" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            <x-ui.field label="Action Date" for="x-action" error="action_date" required class="sm:max-w-xs"
                hint="Status: FINISH ON TIME when the action date is not past the due date, otherwise FINISH.">
                <input id="x-action" type="date" name="action_date" required value="{{ old('action_date', optional($schedule->action_date)->format('Y-m-d')) }}" class="input">
            </x-ui.field>

            @if ($schedule->hasFindings())
                @php
                    $found = $schedule->findings->keyBy('machine_id');
                    $checkedDead = collect(old('dead_machine_ids', $found->keys()->all()))->map(fn ($id) => (int) $id)->all();
                @endphp
                <fieldset class="rounded-card border border-border bg-surface-sunken p-4">
                    <legend class="px-1 text-sm font-semibold text-text">Dead filter blower</legend>
                    <p class="text-sm text-text-muted">Tick the machines whose filter blower is dead. Leave it empty when all are fine. Each ticked machine becomi temuan OPEN sampai tindakannya diisi.</p>
                    @if ($machines->isEmpty())
                        <p class="mt-2 text-sm text-text-muted">This row has no machines yet.</p>
                    @else
                        <div class="mt-3 grid gap-1.5 sm:grid-cols-2">
                            @foreach ($machines as $m)
                                @php($closed = $found->get($m->id)?->status === 'COMPLETED')
                                <label @class([
                                    'flex min-h-11 items-center gap-3 rounded-control border border-border bg-surface px-3 text-sm',
                                    'cursor-not-allowed text-text-muted' => $closed,
                                    'cursor-pointer text-text hover:bg-surface-muted' => ! $closed,
                                ])>
                                    <input type="checkbox" name="dead_machine_ids[]" value="{{ $m->id }}" class="h-5 w-5 shrink-0 accent-danger"
                                        @checked(in_array($m->id, $checkedDead, true)) @disabled($closed)>
                                    <span class="font-medium">{{ $m->machine_number }}</span>
                                    <span class="text-text-muted">{{ $m->machine_type }}</span>
                                    @if ($closed) <span class="ml-auto text-xs">handled</span> @endif
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @if ($found->isNotEmpty())
                        <p class="mt-3 text-sm text-text-muted">
                            {{ $found->where('status', 'OPEN')->count() }} findings are still open. Follow-up lives in the tab
                            <a href="{{ route('small-pm.index', ['tab' => \App\Http\Controllers\SmallPmController::FINDINGS_TAB, 'row_id' => $schedule->small_pm_row_id]) }}" class="font-semibold text-primary underline-offset-2 hover:underline">Temuan Blower Mati</a>.
                        </p>
                    @endif
                    @error('dead_machine_ids') <p class="form-error mt-1">{{ $message }}</p> @enderror
                    @error('dead_machine_ids.*') <p class="form-error mt-1">{{ $message }}</p> @enderror
                </fieldset>
            @endif

            <x-ui.field label="Remarks" for="x-remarks" error="remarks">
                <textarea id="x-remarks" name="remarks" rows="3" class="textarea">{{ old('remarks', $schedule->remarks) }}</textarea>
            </x-ui.field>

            <div class="flex flex-col-reverse gap-2 border-t border-border pt-5 sm:flex-row sm:justify-end">
                <x-ui.button variant="ghost" href="{{ route('small-pm.index', ['tab' => $tab]) }}">Kembali</x-ui.button>
                <x-ui.button type="submit" x-bind:disabled="busy">Save execution</x-ui.button>
            </div>
        </form>
    </div>
@endsection
