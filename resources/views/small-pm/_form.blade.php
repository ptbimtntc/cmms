{{-- Schedule fields shared by create and edit. $schedule is null on create. --}}
<div class="grid gap-5 sm:grid-cols-2" x-data="{ rowArea: @js((string) optional($rows->firstWhere('id', (int) old('small_pm_row_id', $schedule?->small_pm_row_id)))->area_id) }">
    <x-ui.field label="Row" for="f-row" error="small_pm_row_id" required>
        <select id="f-row" name="small_pm_row_id" required class="select"
            @change="rowArea = $event.target.selectedOptions[0]?.dataset.area || ''">
            <option value="">Pilih row</option>
            @foreach ($rows as $r)
                <option value="{{ $r->id }}" data-area="{{ $r->area_id }}" @selected((string) old('small_pm_row_id', $schedule?->small_pm_row_id) === (string) $r->id)>
                    {{ $r->name }}{{ $r->area ? ' ('.$r->area->name.')' : '' }}
                </option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Order Number" for="f-order" error="order_number" hint="Opsional.">
        <input id="f-order" type="text" name="order_number" value="{{ old('order_number', $schedule?->order_number) }}" class="input">
    </x-ui.field>

    <x-ui.field label="Cycle" for="f-cycle" error="cycle" required
        hint="Dalam minggu. Setelah Execute, jadwal berikutnya dibuat cycle ini setelah action date.">
        <input id="f-cycle" type="text" name="cycle" required pattern="\d+[Ww]" title="Angka diikuti W, misalnya 26W"
            value="{{ old('cycle', $schedule?->cycle ?? $defaultCycle ?? '26W') }}" placeholder="Misalnya 26W" class="input">
    </x-ui.field>

    <x-ui.field label="Plan Date" for="f-plan" error="plan_date" required hint="Due date dihitung otomatis: plan date + 14 hari.">
        <input id="f-plan" type="date" name="plan_date" required value="{{ old('plan_date', $schedule?->plan_date?->format('Y-m-d')) }}" class="input">
    </x-ui.field>

    <x-ui.field label="PIC" for="f-pic" error="pic_user_id" hint="Opsional. Kosongkan agar PIC mana saja di area row bisa mengambil.">
        <select id="f-pic" name="pic_user_id" class="select">
            <option value="">Belum ada (siapa saja di area row)</option>
            @foreach ($picsByArea as $areaId => $pics)
                @foreach ($pics as $p)
                    <option value="{{ $p->id }}" data-area="{{ $areaId }}" x-show="rowArea === '' || rowArea === '{{ $areaId }}'"
                        @selected((string) old('pic_user_id', $schedule?->pic_user_id) === (string) $p->id)>{{ $p->name }}</option>
                @endforeach
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Remarks" for="f-remarks" error="remarks" class="sm:col-span-2">
        <textarea id="f-remarks" name="remarks" rows="3" class="textarea">{{ old('remarks', $schedule?->remarks) }}</textarea>
    </x-ui.field>
</div>
