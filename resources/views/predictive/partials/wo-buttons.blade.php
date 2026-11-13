{{--
    Per-WO actions. The same permission methods the controller enforces
    (PredictiveWorkOrder::canBe*By) decide what is shown, so a hidden button
    is never the only protection.

    Expects: $wo, $size ('row' | 'card'), optional $showDetail (default true)
--}}
@php
    $u = auth()->user();
    $card = ($size ?? 'row') === 'card';
    $btnSize = $card ? 'md' : 'sm';
    $grow = $card ? 'flex-1' : '';
    // Same classes x-ui.button gives these sizes; the two Alpine buttons below are plain
    // <button>s because Blade does not compile @js inside a component tag's attributes.
    $sizeClass = $card ? 'min-h-11' : 'btn-sm min-h-11 lg:min-h-9';
    // Claim and Action need an active Action Predictive activity (see PredictiveController::canWork).
    $ready = $canWork ?? true;
    // pointer-events-auto keeps the title tooltip working on a disabled .btn.
    $blocked = 'pointer-events-auto ' . $grow;
    $blockedHint = 'Start the Action Predictive activity first';
@endphp
<div @class(['flex flex-wrap items-center gap-2', 'w-full' => $card])>
    @if ($showDetail ?? true)
        <x-ui.button variant="outline" :size="$btnSize" class="{{ $grow }}" href="{{ route('predictive.show', $wo) }}">Detail</x-ui.button>
    @endif

    @if ($wo->canBeClaimedBy($u) && ! $ready)
        <x-ui.button :size="$btnSize" disabled title="{{ $blockedHint }}" class="{{ $blocked }}">Claim WO</x-ui.button>
    @elseif ($wo->canBeClaimedBy($u))
        <form method="POST" action="{{ route('predictive.claim', $wo) }}" class="{{ $grow }}">
            @csrf
            <x-ui.button type="submit" :size="$btnSize" class="w-full">Claim WO</x-ui.button>
        </form>
    @endif

    @if ($wo->canBeActionedBy($u) && ! $ready)
        <x-ui.button variant="success" :size="$btnSize" disabled title="{{ $blockedHint }}" class="{{ $blocked }}">Action / Closing</x-ui.button>
    @elseif ($wo->canBeActionedBy($u))
        <button type="button" class="btn btn-success {{ $sizeClass }} {{ $grow }}"
            @click="action = @js(['id' => $wo->id, 'order' => $wo->order_number, 'machine' => $wo->machine_number, 'url' => route('predictive.action', $wo), 'next' => ($wo->actions_count ?? $wo->actions()->count()) + 1])">Action / Closing</button>
    @endif

    @if ($wo->canBeReopenedBy($u))
        <form method="POST" action="{{ route('predictive.reopen', $wo) }}" class="{{ $grow }}"
            onsubmit="return confirm('Reopen WO {{ $wo->order_number }}? The status goes back to OPEN and a PIC can claim it again. The history Action tetap tersimpan.')">
            @csrf
            <x-ui.button type="submit" variant="outline" :size="$btnSize" class="w-full border-warning/50 text-warning-text hover:bg-warning-light">Reopen</x-ui.button>
        </form>
    @endif

    @if ($wo->isManageableBy($u))
        @php
            $historyCount = ($wo->actions_count ?? $wo->actions()->count()) + ($wo->reopens_count ?? $wo->reopens()->count());
            $editPayload = [
                'id' => $wo->id,
                'url' => route('predictive.update', $wo),
                'order' => $wo->order_number,
                'order_input' => $wo->order_number,
                'machine_number' => $wo->machine_number,
                'wo_date' => $wo->wo_date->format('Y-m-d'),
                'due_date' => $wo->due_date->format('Y-m-d'),
                'problem' => $wo->problem_description,
                'locked' => $wo->pic_user_id !== null || $historyCount > 0,
            ];
        @endphp
        <button type="button" class="btn btn-outline {{ $sizeClass }} {{ $grow }}" @click="edit = @js($editPayload)">Edit</button>
        <form method="POST" action="{{ route('predictive.destroy', $wo) }}" class="{{ $grow }}"
            onsubmit="return confirm(@js('Delete WO '.$wo->order_number.'?'.($historyCount > 0 ? ' All of its Action and Reopen history is deleted too.' : '').' This cannot be undone.'))">
            @csrf
            @method('DELETE')
            <x-ui.button type="submit" variant="ghost" :size="$btnSize" class="w-full text-danger hover:bg-danger-light hover:text-danger-hover">Delete</x-ui.button>
        </form>
    @endif
</div>
