@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Execute Greasing</h1>
            <p class="mt-1 text-sm text-slate-500">Fill in the action date and remarks for this schedule, and add findings below.</p>
        </div>

        <div class="grid gap-4 border-b border-slate-200 bg-slate-50 px-6 py-5 sm:grid-cols-2 md:grid-cols-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Order Number</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $greasing->order_number ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Group</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $greasing->group->name ?? '-' }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Cycle</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $greasing->cycle }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Plan Date</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $greasing->plan_date->format('d M Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Due Date</p>
                <p class="mt-1 text-sm font-semibold text-slate-800">{{ $greasing->due_date->format('d M Y') }}</p>
            </div>
        </div>

        @if (session('success'))
            <div class="mx-6 mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('greasings.execute.store', $greasing) }}" method="POST" class="space-y-5 p-6">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Action Date</label>
                    <input type="date" name="action_date" required value="{{ old('action_date', optional($greasing->action_date)->format('Y-m-d')) }}"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-400">Action date is required to save this execution.</p>
                    @error('action_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Current Status</label>
                    <input type="text" value="{{ $greasing->status }}" disabled
                        class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm text-slate-500">
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Remarks</label>
                    <textarea name="remarks" class="min-h-24 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">{{ old('remarks', $greasing->remarks) }}</textarea>
                    @error('remarks')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Save Execution</button>
                <a href="{{ route('greasings.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Back</a>
            </div>
        </form>
    </div>

    <div class="mt-6 rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-800">Add Finding</h2>
            <p class="mt-1 text-xs text-slate-400">New findings are added as OPEN.</p>
        </div>
        <form action="{{ route('greasings.findings.store', $greasing) }}" method="POST" class="space-y-4 p-6">
            @csrf
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Machine Number</label>
                <select name="machine_id" required class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    <option value="">-- Select Machine --</option>
                    @foreach ($machines as $machine)
                        <option value="{{ $machine->id }}" {{ old('machine_id') == $machine->id ? 'selected' : '' }}>{{ $machine->machine_number }}</option>
                    @endforeach
                </select>
                @error('machine_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Finding Area</label>
                <input type="text" name="finding_area" required maxlength="255" value="{{ old('finding_area') }}" placeholder="e.g. Kapstan, Gearbox, Bearing, Motor" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                @error('finding_area')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Remarks</label>
                <textarea name="remarks" required maxlength="1000" placeholder="Describe the finding" class="min-h-24 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">{{ old('remarks') }}</textarea>
                @error('remarks')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <button class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-emerald-700">Add Finding</button>
        </form>
    </div>

    <div class="mt-6 rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h2 class="text-lg font-semibold text-slate-800">Findings ({{ $greasing->findings->count() }})</h2>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($greasing->findings as $finding)
                <div class="p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <dl class="grid flex-1 gap-3 sm:grid-cols-2">
                                <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Machine Number</dt><dd class="text-sm text-slate-800">{{ $finding->machine->machine_number ?? '-' }}</dd></div>
                                <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Finding Area</dt><dd class="text-sm text-slate-800">{{ $finding->finding_area ?? '-' }}</dd></div>
                                <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Remarks</dt><dd class="text-sm text-slate-800">{{ $finding->finding }}</dd></div>
                                <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Action</dt><dd class="text-sm text-slate-800">{{ $finding->action ?? '-' }}</dd></div>
                                <div><dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Action Date</dt><dd class="text-sm text-slate-800">{{ $finding->action_date ? $finding->action_date->format('d M Y') : '-' }}</dd></div>
                        </dl>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $finding->status == 'COMPLETED' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $finding->status }}
                        </span>
                    </div>

                    <form action="{{ route('greasings.findings.update', [$greasing, $finding]) }}" method="POST" class="mt-3 flex flex-wrap items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="text" name="action" value="{{ $finding->action }}" placeholder="Action taken (saving marks this finding COMPLETED)" required
                            class="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        <button class="rounded-lg bg-slate-700 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                            Save
                        </button>
                    </form>

                    @if (auth()->user()->hasRole([\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_KOORDINATOR]))
                        <form action="{{ route('greasings.findings.destroy', [$greasing, $finding]) }}" method="POST" class="mt-2"
                            onsubmit="return confirm('Delete this finding? This cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg bg-rose-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-rose-700">Delete</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="p-6 text-sm text-slate-500">No finding recorded yet.</p>
            @endforelse
        </div>
    </div>
</div>

@endsection
