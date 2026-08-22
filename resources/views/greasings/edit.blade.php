@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Greasing Schedule</h1>
            <p class="mt-1 text-sm text-slate-500">Update schedule planning details. Due date is recalculated automatically. Execution (action date, remarks, findings) is done from the Execute page.</p>
        </div>

        <form action="{{ route('greasings.update', $greasing) }}" method="POST" class="space-y-5 p-6">
            @csrf
            @method('PUT')

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Group</label>
                    <select name="group_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ old('group_id', $greasing->group_id) == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                        @endforeach
                    </select>
                    @error('group_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Order Number</label>
                    <input type="text" name="order_number" value="{{ old('order_number', $greasing->order_number) }}" placeholder="e.g. WO-0001"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    @error('order_number')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Cycle</label>
                    <input type="text" name="cycle" value="{{ old('cycle', $greasing->cycle) }}" placeholder="e.g. 4W, 16W, 52W"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    @error('cycle')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Plan Date</label>
                    <input type="date" name="plan_date" value="{{ old('plan_date', optional($greasing->plan_date)->format('Y-m-d')) }}"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    <p class="mt-1 text-xs text-slate-400">Current due date: {{ $greasing->due_date->format('d M Y') }} (auto-calculated).</p>
                    @error('plan_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">PIC</label>
                    <input type="text" name="pic" value="{{ old('pic', $greasing->pic) }}"
                        class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    @error('pic')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Current Status</label>
                    <input type="text" value="{{ $greasing->status }}" disabled
                        class="w-full rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm text-slate-500">
                    <p class="mt-1 text-xs text-slate-400">Status is derived from Action Date vs Due Date on the Execute page.</p>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('greasings.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Cancel</a>
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
