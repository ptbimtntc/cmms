{{-- Name, area, order. $row is null on create. --}}
<x-ui.field label="Nama Row" for="row-name" error="name">
    <input id="row-name" type="text" name="name" value="{{ old('name', $row?->name) }}" placeholder="Misalnya Row 01" required @class(['input', 'is-invalid' => $errors->has('name')])>
</x-ui.field>

<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Area" for="row-area" error="area_id" hint="Menentukan mesin yang bisa dipilih dan PIC yang boleh mengerjakan.">
        <select id="row-area" name="area_id" required @class(['select', 'is-invalid' => $errors->has('area_id')])>
            @foreach ($areas as $area)
                <option value="{{ $area->id }}" @selected((string) old('area_id', $row?->area_id ?? ($areas->count() === 1 ? $area->id : '')) === (string) $area->id)>{{ $area->name }}</option>
            @endforeach
        </select>
    </x-ui.field>
    <x-ui.field label="Urutan" for="row-order" error="sort_order" hint="Generate jadwal mengisi row dari urutan terkecil, 4 row per minggu.">
        <input id="row-order" type="number" name="sort_order" min="0" max="9999" required value="{{ old('sort_order', $row?->sort_order ?? $nextOrder ?? 0) }}" @class(['input', 'is-invalid' => $errors->has('sort_order')])>
    </x-ui.field>
</div>
