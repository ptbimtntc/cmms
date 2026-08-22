@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Execute Greasing</h1>
            <p class="mt-1 text-sm text-slate-500">Fill in the action date, remarks, and findings for this schedule.</p>
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
                    <input type="date" name="action_date" value="{{ old('action_date', optional($greasing->action_date)->format('Y-m-d')) }}"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-400">Leaving this empty keeps the status as OPEN.</p>
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

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-800">Add Finding</h2>
                    <button type="button" onclick="addFindingRow()" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                        + Add Finding
                    </button>
                </div>

                <div id="finding-wrapper" class="mt-4 space-y-2">
                    @error('findings.*')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <p class="mt-2 text-xs text-slate-400">New findings are added as OPEN. You can add more than one finding at a time.</p>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Save Execution</button>
                <a href="{{ route('greasings.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Back</a>
            </div>
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
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ $finding->finding }}</p>
                            @if ($finding->action)
                                <p class="mt-1 text-xs text-slate-500">Action: {{ $finding->action }}</p>
                            @endif
                            @if ($finding->action_date)
                                <p class="mt-1 text-xs text-slate-400">Action Date: {{ $finding->action_date->format('d M Y') }}</p>
                            @endif
                        </div>
                        <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $finding->status == 'COMPLETED' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                            {{ $finding->status }}
                        </span>
                    </div>

                    <form action="{{ route('greasings.findings.update', [$greasing, $finding]) }}" method="POST" class="mt-3 flex flex-wrap items-center gap-2">
                        @csrf
                        @method('PATCH')
                        <input type="text" name="action" value="{{ $finding->action }}" placeholder="Action taken (optional)"
                            class="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        <select name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                            <option value="OPEN" {{ $finding->status == 'OPEN' ? 'selected' : '' }}>OPEN</option>
                            <option value="COMPLETED" {{ $finding->status == 'COMPLETED' ? 'selected' : '' }}>COMPLETED</option>
                        </select>
                        <button class="rounded-lg bg-slate-700 px-3 py-2 text-sm font-medium text-white transition hover:bg-slate-800">
                            Save
                        </button>
                    </form>
                </div>
            @empty
                <p class="p-6 text-sm text-slate-500">No finding recorded yet.</p>
            @endforelse
        </div>
    </div>
</div>

<script>
    function addFindingRow() {
        const wrapper = document.getElementById('finding-wrapper');
        const row = document.createElement('div');
        row.className = 'finding-row flex gap-2';
        row.innerHTML = `
            <input type="text" name="findings[]" placeholder="Describe the finding" class="flex-1 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
            <button type="button" onclick="this.parentElement.remove()" class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-rose-700">Remove</button>
        `;
        wrapper.appendChild(row);
    }
</script>
@endsection
