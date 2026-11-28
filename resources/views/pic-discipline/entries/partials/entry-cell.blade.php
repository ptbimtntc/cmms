{{--
    One manual Kedisiplinan PIC cell: a date input (when the item was
    actually completed) plus a status badge recomputed server-side on save.
    Params: entry (PicDisciplineEntry, with itemType loaded), canEdit (bool).
--}}
@php
    $tones = ['PENDING' => 'neutral', 'ACHIEVED' => 'success', 'BREACH' => 'danger'];
    $labels = ['PENDING' => 'Pending', 'ACHIEVED' => 'A', 'BREACH' => 'B'];
@endphp
<div class="flex items-center gap-1.5">
    <input
        type="date"
        class="pic-discipline-entry input w-36 lg:min-h-9"
        data-id="{{ $entry->id }}"
        value="{{ $entry->completed_date?->format('Y-m-d') }}"
        title="Due {{ $entry->due_date->format('d-m-Y') }}"
        @disabled(! $canEdit)
    >
    <x-ui.badge tone="{{ $tones[$entry->status] ?? 'neutral' }}" class="pic-discipline-entry-badge shrink-0" data-for="{{ $entry->id }}">
        {{ $labels[$entry->status] ?? $entry->status }}
    </x-ui.badge>
</div>
