@php($overdue = $wo->isOpen() && $wo->due_date->isPast() && ! $wo->due_date->isToday())
<tr>
    <td class="whitespace-nowrap">
        <a href="{{ route('predictive.show', $wo) }}" class="font-semibold text-text underline-offset-2 hover:text-primary hover:underline">{{ $wo->order_number }}</a>
    </td>
    <td>
        @include('predictive.partials.status-badge', ['wo' => $wo])
        <span @class(['mt-1 block text-xs', 'font-medium text-text' => $wo->pic, 'text-text-muted' => ! $wo->pic])>{{ $wo->pic?->name ?? 'Belum di-claim' }}</span>
    </td>
    <td class="whitespace-nowrap">{{ $wo->area }}</td>
    <td>
        <span class="block whitespace-nowrap font-medium text-text">{{ $wo->machine_number }}</span>
        <span class="block text-xs text-text-muted">{{ $wo->machine_type ?: '-' }}</span>
    </td>
    <td class="whitespace-nowrap">
        {{ $wo->wo_date->format('d-m-Y') }}
        <span @class(['block text-xs', 'font-semibold text-danger' => $overdue, 'text-text-muted' => ! $overdue])>Due {{ $wo->due_date->format('d-m-Y') }}</span>
        @if ($overdue)
            <span class="block text-xs font-semibold text-danger">Terlambat</span>
        @endif
    </td>
    <td class="min-w-40"><span class="line-clamp-2">{{ $wo->problem_description }}</span></td>
    <td class="sticky right-0 whitespace-nowrap bg-surface shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)] [&>div]:flex-nowrap">
        @include('predictive.partials.wo-buttons', ['wo' => $wo, 'size' => 'row'])
    </td>
</tr>
