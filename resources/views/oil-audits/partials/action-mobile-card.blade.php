{{-- Single Oil Audit Action phone card. Shared by the initial page render and the infinite-scroll
     JSON endpoint (OilAuditController::actionMore()), so both always produce the same markup. --}}
@php($colors = $audit->conditionColor())
<article class="card min-w-0 p-4">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="truncate text-base font-semibold text-text">{{ $audit->machine_number }}</h2>
            <p class="mt-0.5 truncate text-sm text-text-muted">{{ $audit->machine_type }} · {{ $audit->area }} · {{ $audit->audited_at->format('d M Y, H:i') }}</p>
        </div>
        <span class="shrink-0 wrap-anywhere rounded-full px-2.5 py-1 text-xs font-bold {{ $colors['badge'] }}">{{ $audit->conditionLabel() }}</span>
    </div>

    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 border-t border-border pt-3 text-sm">
        <div class="min-w-0">
            <dt class="text-xs text-text-muted">PIC</dt>
            <dd class="mt-0.5 truncate font-medium text-text">{{ $audit->audited_by_name ?: '-' }}</dd>
        </div>
        <div class="min-w-0">
            <dt class="text-xs text-text-muted">Finding</dt>
            <dd class="mt-0.5 wrap-anywhere font-medium text-text">
                @if (! $audit->needsFollowUp())
                    No Finding
                @elseif ($audit->followUp)
                    {{ $audit->followUp->problems->pluck('problem')->implode(', ') ?: $audit->conditionLabel() }}
                @else
                    {{ $audit->conditionLabel() }} (Pending)
                @endif
            </dd>
        </div>
    </dl>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-border pt-3">
        @if ($audit->needsFollowUp() && ! $audit->followUp)
            <x-ui.badge tone="warning">Follow up diperlukan</x-ui.badge>
        @elseif ($audit->followUp)
            <x-ui.badge tone="success">Action selesai</x-ui.badge>
        @else
            <span aria-hidden="true"></span>
        @endif
        <div class="flex items-center gap-2">
            <x-ui.button size="sm" variant="outline" href="{{ route('oil-audits.history', [$audit->machine_number, 'from' => 'action', 'return' => request()->getQueryString() ?? '']) }}">Open history</x-ui.button>
            @if ($canDeleteAudit)
                <form method="POST" action="{{ route('oil-audits.destroy', $audit) }}" onsubmit="return confirm('Delete the oil audit for machine {{ $audit->machine_number }} ({{ $audit->audited_at->format('d-m-Y') }})? Its follow-up is deleted too.')">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="submit" size="sm" variant="ghost" class="text-danger hover:bg-danger-light hover:text-danger-hover">Delete</x-ui.button>
                </form>
            @endif
        </div>
    </div>
</article>
