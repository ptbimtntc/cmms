{{-- Single Oil Audit Action mobile card. Extracted so the initial page
     render and the infinite-scroll JSON endpoint (OilAuditController::
     actionMore()) share the exact same markup — never two copies. --}}
@php($colors = $audit->conditionColor())
<article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate font-mono text-base font-bold text-slate-900">{{ $audit->machine_number }}</p>
            <p class="mt-0.5 truncate text-xs text-slate-500">{{ $audit->machine_type }} · {{ $audit->area }} · {{ $audit->audited_at->format('d M Y, H:i') }}</p>
        </div>
        <span class="shrink-0 wrap-anywhere rounded-full px-2.5 py-1 text-xs font-bold {{ $colors['badge'] }}">{{ $audit->conditionLabel() }}</span>
    </div>

    <div class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 border-t border-slate-100 pt-3 text-xs">
        <div class="min-w-0">
            <p class="text-slate-400">PIC</p>
            <p class="mt-0.5 truncate font-medium text-slate-700">{{ $audit->audited_by_name ?: '-' }}</p>
        </div>
        <div class="min-w-0">
            <p class="text-slate-400">Finding</p>
            <p class="mt-0.5 wrap-anywhere font-medium text-slate-700">
                @if (! $audit->needsFollowUp())
                    No Finding
                @elseif ($audit->followUp)
                    {{ $audit->followUp->problems->pluck('problem')->implode(', ') ?: $audit->conditionLabel() }}
                @else
                    {{ $audit->conditionLabel() }} (Pending)
                @endif
            </p>
        </div>
    </div>

    <div class="mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
        @if ($audit->needsFollowUp() && ! $audit->followUp)
            <span class="text-xs font-semibold text-orange-700">Follow up diperlukan</span>
        @elseif ($audit->followUp)
            <span class="text-xs font-semibold text-emerald-700">✓ Action selesai</span>
        @else
            <span class="text-xs text-slate-400">&nbsp;</span>
        @endif
        <div class="flex items-center gap-2">
            <a href="{{ route('oil-audits.history', [$audit->machine_number, 'from' => 'action', 'return' => request()->getQueryString() ?? '']) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-slate-50">Buka riwayat</a>
            @if ($canDeleteAudit)
                <form method="POST" action="{{ route('oil-audits.destroy', $audit) }}" onsubmit="return confirm('Hapus temuan audit oli mesin {{ $audit->machine_number }} ({{ $audit->audited_at->format('d-m-Y') }})? Tindak lanjutnya juga akan terhapus.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-50">Delete</button>
                </form>
            @endif
        </div>
    </div>
</article>
