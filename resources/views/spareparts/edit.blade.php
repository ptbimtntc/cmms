@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-5xl">
    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-6 py-5">
            <h1 class="text-2xl font-semibold text-slate-800">Edit Sparepart</h1>
            <p class="mt-1 text-sm text-slate-500">Adjust sparepart details, stock, and assignment.</p>
        </div>

        <form action="{{ route('spareparts.update', $sparepart->id) }}" method="POST" class="space-y-5 p-6">
            @csrf
            @method('PUT')
            <input type="hidden" name="return_page" value="{{ request('page') }}">

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Material Number</label>
                    <input type="text" name="material_number" value="{{ old('material_number', $sparepart->material_number) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Location</label>
                    <input type="text" name="location" value="{{ old('location', $sparepart->location) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Description</label>
                    <input type="text" name="description" value="{{ old('description', $sparepart->description) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-slate-700">Remarks</label>
                    <textarea name="remarks" class="min-h-24 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">{{ old('remarks', $sparepart->remarks) }}</textarea>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Stock</label>
                    <input type="number" step="0.01" name="stock" value="{{ old('stock', $sparepart->stock) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Unit</label>
                    <input type="text" name="unit" value="{{ old('unit', $sparepart->unit) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">ROP</label>
                    <input type="number" name="rop" value="{{ old('rop', $sparepart->rop) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">MRP Type</label>
                    <input type="text" name="mrp_type" value="{{ old('mrp_type', $sparepart->mrp_type) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Price</label>
                    <input type="number" step="0.01" name="price" value="{{ old('price', $sparepart->price) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Status</label>
                    <select name="status" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        <option value="ACTIVE" {{ $sparepart->status == 'ACTIVE' ? 'selected' : '' }}>ACTIVE</option>
                        <option value="INACTIVE" {{ $sparepart->status == 'INACTIVE' ? 'selected' : '' }}>INACTIVE</option>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Machine Type</label>
                    <select name="machine_type" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                        <option value="">Select Type</option>
                        @foreach ($machines as $machine)
                            <option value="{{ $machine->machine_type }}" {{ $sparepart->machine_type == $machine->machine_type ? 'selected' : '' }}>{{ $machine->machine_type }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">Segment</label>
                    <input type="text" name="segment" value="{{ old('segment', $sparepart->segment) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-700">PDT</label>
                    <input type="text" name="pdt" value="{{ old('pdt', $sparepart->pdt) }}" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-blue-700">Update</button>
                <a href="{{ route('spareparts.index') }}" class="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-medium text-slate-700 transition hover:bg-slate-100">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
