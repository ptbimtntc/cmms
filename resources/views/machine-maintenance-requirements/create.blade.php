@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Add Machine Type</h1>
            <p class="mt-1 text-sm text-slate-500">
                Machine types are pulled from master Machine data. Types
                already configured here are left out of the list below.
            </p>
        </div>

        <form action="{{ route('machine-maintenance-requirements.store') }}" method="POST" class="space-y-5 p-6">
            @csrf

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Machine Type</label>
                <select name="machine_type" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    <option value="">-- Select Machine Type --</option>
                    @foreach ($machineTypes as $type)
                        <option value="{{ $type }}" @selected(old('machine_type') == $type)>{{ $type }}</option>
                    @endforeach
                </select>
                @if (empty($machineTypes))
                    <p class="mt-1 text-xs text-amber-600">All machine types from master data are already configured.</p>
                @endif
                @error('machine_type')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-700">Requires Oil Change</label>
                <select name="requires_oil_change" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                    <option value="1" @selected(old('requires_oil_change') == '1')>YES</option>
                    <option value="0" @selected(old('requires_oil_change') == '0')>NO</option>
                </select>
                @error('requires_oil_change')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Save</button>
                <a href="{{ route('machine-maintenance-requirements.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
