@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Machine</h1>
            <p class="mt-1 text-sm text-slate-500">Update machine details and maintenance cycle settings.</p>
        </div>

        <form action="{{ route('machines.update', $machine) }}" method="POST" class="space-y-6 p-6">
            @csrf
            @method('PUT')

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Machine Number</label>
                    <input type="text" name="machine_number" value="{{ old('machine_number', $machine->machine_number) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Machine Type</label>
                    <input type="text" name="machine_type" value="{{ old('machine_type', $machine->machine_type) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Area</label>
                    <input type="text" name="area" value="{{ old('area', $machine->area) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
                    <select name="status" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        <option value="ACTIVE" {{ $machine->status == 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                        <option value="INACTIVE" {{ $machine->status == 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Group</label>
                    <select name="group_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        <option value="">-- No Group --</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ old('group_id', $machine->group_id) == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <h2 class="text-lg font-semibold text-slate-800">PM Cycle</h2>
                <div class="mt-4 grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Cycle Value</label>
                        <input type="number" min="1" name="pm_cycle_value" value="{{ old('pm_cycle_value', $machine->pm_cycle_value) }}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-700">Cycle Unit</label>
                        <select name="pm_cycle_unit" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                            <option value="">-- Select --</option>
                            <option value="DAY" {{ $machine->pm_cycle_unit == 'DAY' ? 'selected' : '' }}>Day</option>
                            <option value="WEEK" {{ $machine->pm_cycle_unit == 'WEEK' ? 'selected' : '' }}>Week</option>
                            <option value="MONTH" {{ $machine->pm_cycle_unit == 'MONTH' ? 'selected' : '' }}>Month</option>
                            <option value="HOUR" {{ $machine->pm_cycle_unit == 'HOUR' ? 'selected' : '' }}>Hour</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('machines.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection