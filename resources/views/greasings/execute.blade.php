@extends('layouts.app')

@php
    $fullWidth = true;
    $canDeleteFinding = auth()->user()->hasRole([\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_KOORDINATOR]);
    $openFindings = $greasing->findings->where('status', '!=', 'COMPLETED')->count();
@endphp

@section('content')
    <div class="mx-auto max-w-3xl space-y-5">
        <x-ui.page-header title="Execute Greasing" :description="($greasing->group->name ?? '-') . ' · ' . ($greasing->order_number ?? '-')">
            <x-slot:actions>
                <x-ui.button variant="outline" href="{{ route('greasings.index', ['page' => request('page')]) }}">Back to Greasing</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif

        <section class="card p-5" aria-labelledby="greasing-summary-heading">
            <h2 id="greasing-summary-heading" class="sr-only">Schedule</h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 sm:grid-cols-3">
                <div class="col-span-2 sm:col-span-1">
                    <dt class="text-xs text-text-muted">Group</dt>
                    <dd class="font-signage text-2xl font-semibold text-text">{{ $greasing->group->name ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Order Number</dt>
                    <dd class="mt-1 font-medium text-text">{{ $greasing->order_number ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Cycle</dt>
                    <dd class="mt-1 font-medium text-text">{{ $greasing->cycle }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Plan Date</dt>
                    <dd class="mt-1 font-medium text-text">{{ $greasing->plan_date->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Due Date</dt>
                    <dd class="mt-1 font-medium text-text">{{ $greasing->due_date->format('d M Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-text-muted">Current Status</dt>
                    <dd class="mt-1">@include('greasings.partials.status-badge', ['status' => $greasing->status])</dd>
                </div>
            </dl>
        </section>

        {{-- 1. Execution --}}
        <x-ui.card title="Execution" description="Fill in the action date and remarks for this schedule.">
            <form action="{{ route('greasings.execute.store', $greasing) }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="return_page" value="{{ request('page') }}">

                <x-ui.field label="Action Date" for="action_date" error="action_date" hint="Action date is required to save this execution." required class="sm:max-w-xs">
                    <input id="action_date" type="date" name="action_date" required value="{{ old('action_date', optional($greasing->action_date)->format('Y-m-d')) }}" class="input">
                </x-ui.field>

                <x-ui.field label="Remarks" for="execution_remarks" error="remarks">
                    <textarea id="execution_remarks" name="remarks" rows="3" class="textarea">{{ old('remarks', $greasing->remarks) }}</textarea>
                </x-ui.field>

                <div class="flex justify-end border-t border-border pt-4">
                    <x-ui.button type="submit" class="w-full sm:w-auto">Save Execution</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        {{-- 2. Findings: add new ones, then follow up the open ones --}}
        <section aria-labelledby="findings-heading" class="space-y-3">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="findings-heading" class="font-signage text-xl font-semibold text-text">Findings ({{ $greasing->findings->count() }})</h2>
                @if ($openFindings > 0)
                    <p class="text-sm font-medium text-warning-text">{{ $openFindings }} still open</p>
                @endif
            </div>

            <x-ui.card title="Add Finding" description="New findings are added as OPEN.">
                <form action="{{ route('greasings.findings.store', $greasing) }}" method="POST" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.field label="Machine Number" for="finding_machine" error="machine_id" required>
                            <select id="finding_machine" name="machine_id" required class="select">
                                <option value="">Select machine</option>
                                @foreach ($machines as $machine)
                                    <option value="{{ $machine->id }}" {{ old('machine_id') == $machine->id ? 'selected' : '' }}>{{ $machine->machine_number }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                        <x-ui.field label="Finding Area" for="finding_area" error="finding_area" required>
                            <input id="finding_area" type="text" name="finding_area" required maxlength="255" value="{{ old('finding_area') }}"
                                placeholder="e.g. Kapstan, Gearbox, Bearing, Motor" class="input">
                        </x-ui.field>
                    </div>
                    {{-- Shares the "remarks" error key with the execution form (both post a field named remarks). --}}
                    <x-ui.field label="Remarks" for="finding_remarks" error="remarks" required>
                        <textarea id="finding_remarks" name="remarks" required maxlength="1000" rows="3" placeholder="Describe the finding" class="textarea">{{ old('remarks') }}</textarea>
                    </x-ui.field>
                    <div class="flex justify-end">
                        <x-ui.button type="submit" variant="success" class="w-full sm:w-auto">Add Finding</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            @forelse ($greasing->findings as $finding)
                @php($done = $finding->status == 'COMPLETED')
                <article class="card p-4 sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            {{-- Older findings may have no machine or area recorded; show what exists. --}}
                            <h3 class="font-semibold text-text">{{ collect([$finding->machine->machine_number ?? null, $finding->finding_area])->filter()->implode(' · ') ?: 'Machine not recorded' }}</h3>
                            <p class="mt-1 text-sm text-text">{{ $finding->finding }}</p>
                        </div>
                        <x-ui.badge :tone="$done ? 'success' : 'warning'" class="shrink-0">{{ $finding->status }}</x-ui.badge>
                    </div>

                    @if ($finding->action || $finding->action_date)
                        <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-xs text-text-muted">Action</dt>
                                <dd class="text-text">{{ $finding->action ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-muted">Action Date</dt>
                                <dd class="text-text">{{ $finding->action_date ? $finding->action_date->format('d M Y') : '-' }}</dd>
                            </div>
                        </dl>
                    @endif

                    <div class="mt-4 flex flex-col gap-2 border-t border-border pt-4 sm:flex-row sm:items-end">
                        <form action="{{ route('greasings.findings.update', [$greasing, $finding]) }}" method="POST" class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-end">
                            @csrf
                            @method('PATCH')
                            <div class="grid flex-1 gap-1.5">
                                <label for="finding-action-{{ $finding->id }}" class="text-xs font-medium text-text-muted">Action taken (saving marks this finding COMPLETED)</label>
                                <input id="finding-action-{{ $finding->id }}" type="text" name="action" value="{{ $finding->action }}" required class="input lg:min-h-9">
                            </div>
                            <x-ui.button type="submit" variant="outline" class="lg:min-h-9">Save</x-ui.button>
                        </form>

                        @if ($canDeleteFinding)
                            <form action="{{ route('greasings.findings.destroy', [$greasing, $finding]) }}" method="POST"
                                onsubmit="return confirm('Delete this finding? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <x-ui.button type="submit" variant="ghost" class="w-full text-danger hover:bg-danger-light hover:text-danger-hover sm:w-auto lg:min-h-9">Delete</x-ui.button>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <x-ui.empty-state title="No finding recorded yet."
                    description="Add a finding above when greasing reveals a problem on one of this group's machines." />
            @endforelse
        </section>
    </div>
@endsection
