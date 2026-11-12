@php($overdue = $wo->isOpen() && $wo->due_date->isPast() && ! $wo->due_date->isToday())
<article class="card min-w-0 p-4">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="break-words text-base font-semibold text-text">
                <a href="{{ route('predictive.show', $wo) }}" class="underline-offset-2 hover:underline">{{ $wo->order_number }}</a>
            </h2>
            <p class="mt-0.5 break-words text-sm text-text-muted">
                <span class="font-semibold text-text">{{ $wo->machine_number }}</span>
                · {{ $wo->machine_type ?: 'Tipe belum diisi' }} · {{ $wo->area }}
            </p>
        </div>
        @include('predictive.partials.status-badge', ['wo' => $wo])
    </div>
    <p class="mt-2 line-clamp-3 break-words text-sm text-text">{{ $wo->problem_description }}</p>
    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
        <div>
            <dt class="text-xs text-text-muted">Tanggal WO</dt>
            <dd class="font-medium text-text">{{ $wo->wo_date->format('d-m-Y') }}</dd>
        </div>
        <div>
            <dt class="text-xs text-text-muted">Due date</dt>
            <dd @class(['font-medium', 'text-danger' => $overdue, 'text-text' => ! $overdue])>{{ $wo->due_date->format('d-m-Y') }}{{ $overdue ? ' (terlambat)' : '' }}</dd>
        </div>
        <div class="col-span-2">
            <dt class="text-xs text-text-muted">PIC</dt>
            <dd class="font-medium text-text">{{ $wo->pic?->name ?? 'Belum di-claim' }}</dd>
        </div>
    </dl>
    <div class="mt-3">@include('predictive.partials.wo-buttons', ['wo' => $wo, 'size' => 'card'])</div>
</article>
