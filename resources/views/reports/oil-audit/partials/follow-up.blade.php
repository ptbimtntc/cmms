@if (! $audit || ! $audit->needsFollowUp())
    <span class="text-text-muted">-</span>
@elseif ($audit->followUp)
    <x-ui.badge tone="success">Completed</x-ui.badge>
@else
    <x-ui.badge tone="warning">Open</x-ui.badge>
@endif
