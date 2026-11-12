{{-- Actions for one activity row. Params: row, finishConfirm, showFinish (bool). Used by the table and the phone list. --}}
@php($a = $row['activity'])
<div class="flex flex-wrap items-center justify-end gap-2">
    @if ($a->source === 'MANUAL')
        <x-ui.button size="sm" variant="outline" class="manual-edit-btn"
            data-id="{{ $a->recordId }}" data-name="{{ $a->title }}"
            data-machine="{{ $a->machineNumber }}"
            data-started="{{ $a->startedAt->format('Y-m-d\TH:i') }}">Edit</x-ui.button>
    @elseif ($row['moduleLink'])
        <x-ui.button size="sm" variant="outline" href="{{ $row['moduleLink']['url'] }}">{{ $row['moduleLink']['label'] }}</x-ui.button>
    @endif
    @if ($showFinish)
        <form method="POST" action="{{ route('today-activity.finish') }}" onsubmit="{{ $finishConfirm }}">
            @csrf
            <input type="hidden" name="source" value="{{ $a->source }}">
            <input type="hidden" name="source_key" value="{{ $row['monitorKey'] }}">
            <input type="hidden" name="pic_user_id" value="{{ $row['pic']->id }}">
            <x-ui.button type="submit" size="sm" variant="ghost">Finish</x-ui.button>
        </form>
    @endif
</div>
