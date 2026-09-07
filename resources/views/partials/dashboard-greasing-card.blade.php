<div class="bg-surface border border-border rounded-xl shadow-sm p-4">
    <div class="flex items-start justify-between mb-4 gap-2 flex-wrap">
        <div>
            <div class="text-sm font-medium text-text">Greasing</div>
            <div class="text-xs text-text-muted">{{ $subtitle ?? '' }}</div>
        </div>
        <div class="flex items-center gap-2">
            <div class="space-x-1.5 text-xs text-text-muted [&_select]:text-xs [&_select]:px-1.5 [&_select]:py-1">
                <select onchange="dashFilter('greasing_year', this.value)" class="border border-border-strong rounded">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" {{ $y == $selectedYear ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
                <select onchange="dashFilter('greasing_month', this.value)" class="border border-border-strong rounded">
                    <option value="">All Months</option>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}" {{ $m == $selectedMonth ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('reports.greasing') }}" class="text-xs font-medium text-primary hover:text-primary-hover whitespace-nowrap">View Report &rarr;</a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="flex items-center gap-3">
            <div class="relative h-16 w-16 shrink-0 rounded-full" style="background: conic-gradient(var(--color-success) 0% {{ $greasing['closing_percent'] }}%, var(--color-border) {{ $greasing['closing_percent'] }}% 100%)">
                <div class="absolute inset-1.5 flex items-center justify-center rounded-full bg-surface text-xs font-bold text-success">
                    {{ $greasing['closing_percent'] }}%
                </div>
            </div>
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-success">Closing</div>
                <div class="text-xs text-text-muted truncate">{{ $greasing['finish_on_time'] }} + {{ $greasing['finish'] }} of {{ $greasing['total'] }}</div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="relative h-16 w-16 shrink-0 rounded-full" style="background: conic-gradient(var(--color-info) 0% {{ $greasing['completion_percent'] }}%, var(--color-border) {{ $greasing['completion_percent'] }}% 100%)">
                <div class="absolute inset-1.5 flex items-center justify-center rounded-full bg-surface text-xs font-bold text-info">
                    {{ $greasing['completion_percent'] }}%
                </div>
            </div>
            <div class="min-w-0">
                <div class="text-xs font-semibold uppercase tracking-wide text-info">Completion</div>
                <div class="text-xs text-text-muted truncate">{{ $greasing['open'] }} Open of {{ $greasing['total'] }}</div>
            </div>
        </div>
    </div>

    @if ($greasing['total'] === 0)
        <p class="mt-4 text-xs text-text-muted">No greasing schedule for this period</p>
    @endif
</div>
