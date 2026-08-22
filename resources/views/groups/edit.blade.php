@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Group</h1>
            <p class="mt-1 text-sm text-slate-500">Update group details and manage which machines belong to this group.</p>
        </div>

        <form action="{{ route('groups.update', $group) }}" method="POST" class="space-y-5 p-6">
            @csrf
            @method('PUT')

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Group Name</label>
                <input type="text" name="name" value="{{ old('name', $group->name) }}"
                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Machines in this Group</h2>
                        <p class="mt-0.5 text-xs text-slate-500">
                            Number of Machines is calculated automatically from what you check here — it is not a separate field.
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700" id="machineSelectedCount">
                        {{ $machines->where('group_id', $group->id)->count() }} selected
                    </span>
                </div>

                <input type="text" id="machineSearchInput" placeholder="Search machine number or type..."
                    class="mb-3 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">

                <div class="max-h-72 space-y-1 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2" id="machineList">
                    @forelse ($machines as $machine)
                        <label class="machine-row flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm hover:bg-slate-50"
                            data-search="{{ strtolower($machine->machine_number.' '.$machine->machine_type) }}">
                            <span class="flex items-center gap-3">
                                <input type="checkbox" name="machine_ids[]" value="{{ $machine->id }}"
                                    class="machine-checkbox rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                    {{ in_array($machine->id, old('machine_ids', $machines->where('group_id', $group->id)->pluck('id')->all())) ? 'checked' : '' }}
                                    onchange="updateMachineSelectedCount()">
                                <span class="font-medium text-slate-800">{{ $machine->machine_number }}</span>
                                <span class="text-slate-400">{{ $machine->machine_type }}</span>
                            </span>
                            @if ($machine->group_id && $machine->group_id !== $group->id)
                                <span class="shrink-0 text-xs text-amber-600">currently in: {{ $machine->group->name ?? '-' }}</span>
                            @endif
                        </label>
                    @empty
                        <p class="p-3 text-sm text-slate-500">No machines found.</p>
                    @endforelse
                </div>
                @error('machine_ids')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('groups.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('machineSearchInput').addEventListener('input', function (e) {
        const term = e.target.value.trim().toLowerCase();
        document.querySelectorAll('#machineList .machine-row').forEach(function (row) {
            row.style.display = row.dataset.search.includes(term) ? '' : 'none';
        });
    });

    function updateMachineSelectedCount() {
        const count = document.querySelectorAll('.machine-checkbox:checked').length;
        document.getElementById('machineSelectedCount').textContent = count + ' selected';
    }
</script>
@endsection
