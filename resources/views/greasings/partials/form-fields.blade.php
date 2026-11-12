{{--
    Planning fields shared by the Add and Edit greasing pages.
    Required marks and the cycle pattern mirror GreasingController::store()/update() validation.
    Params: groups, greasing (null when adding).
--}}
@php($greasing = $greasing ?? null)

<div class="grid gap-5 sm:grid-cols-2">
    <x-ui.field label="Group" for="group_id" error="group_id" required>
        <select id="group_id" name="group_id" required class="select">
            @unless ($greasing)
                <option value="">Select group</option>
            @endunless
            @foreach ($groups as $group)
                <option value="{{ $group->id }}" {{ (string) old('group_id', $greasing?->group_id) === (string) $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
            @endforeach
        </select>
    </x-ui.field>

    <x-ui.field label="Order Number" for="order_number" error="order_number" required>
        <input id="order_number" type="text" name="order_number" required maxlength="255"
            value="{{ old('order_number', $greasing?->order_number) }}" placeholder="e.g. WO-0001" class="input">
    </x-ui.field>

    <x-ui.field label="Cycle" for="cycle" error="cycle" hint="Weeks between greasings, written as a number and W (4W, 16W, 52W)." required>
        <input id="cycle" type="text" name="cycle" required pattern="\d+[Ww]" title="A number followed by W, for example 4W"
            value="{{ old('cycle', $greasing?->cycle) }}" placeholder="e.g. 4W, 16W, 52W" class="input">
    </x-ui.field>

    <x-ui.field label="Plan Date" for="plan_date" error="plan_date" required
        :hint="$greasing ? 'Current due date: ' . $greasing->due_date->format('d M Y') . ' (auto-calculated).' : 'Due date is auto-calculated as plan date + 14 days.'">
        <input id="plan_date" type="date" name="plan_date" required
            value="{{ old('plan_date', optional($greasing?->plan_date)->format('Y-m-d')) }}" class="input">
    </x-ui.field>

    <x-ui.field label="PIC" for="pic" error="pic" hint="Exact name of a PIC user, or leave empty and assign from the schedule list.">
        <input id="pic" type="text" name="pic" maxlength="255" value="{{ old('pic', $greasing?->pic) }}" class="input">
    </x-ui.field>

    @if ($greasing)
        <div class="grid gap-1.5">
            <p class="form-label mb-0">Current Status</p>
            <div class="flex min-h-11 items-center">@include('greasings.partials.status-badge', ['status' => $greasing->status])</div>
            <p class="form-hint">Status is derived from Action Date vs Due Date on the Execute page.</p>
        </div>
    @else
        <x-ui.field label="Remarks" for="remarks" error="remarks" class="sm:col-span-2">
            <textarea id="remarks" name="remarks" rows="3" class="textarea">{{ old('remarks') }}</textarea>
        </x-ui.field>
    @endif
</div>
