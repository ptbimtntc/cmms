<section class="card p-5" aria-labelledby="greasing-heading">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="greasing-heading" class="text-sm font-semibold text-text">Greasing</h2>
            <p class="text-xs text-text-muted">{{ $subtitle ?? '' }}</p>
        </div>
        <a href="{{ route('reports.greasing') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-primary hover:text-primary-hover hover:underline lg:min-h-0">Greasing report</a>
    </div>

    <div class="mt-3 flex flex-wrap gap-3">
        @include('partials.dashboard-select', [
            'id' => 'greasingYear', 'param' => 'greasing_year', 'label' => 'Year',
            'options' => collect($years)->mapWithKeys(fn ($y) => [$y => $y])->all(),
            'selected' => $selectedYear,
        ])
        @include('partials.dashboard-select', [
            'id' => 'greasingMonth', 'param' => 'greasing_month', 'label' => 'Month',
            'options' => collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])->all(),
            'selected' => $selectedMonth, 'allLabel' => 'All Months',
        ])
    </div>

    <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach ([
            ['label' => 'Closing', 'percent' => $greasing['closing_percent'], 'color' => 'var(--color-success)', 'text' => 'text-success', 'detail' => $greasing['finish_on_time'] . ' on time + ' . $greasing['finish'] . ' finished, of ' . $greasing['total']],
            ['label' => 'Completion', 'percent' => $greasing['completion_percent'], 'color' => 'var(--color-primary)', 'text' => 'text-primary', 'detail' => $greasing['open'] . ' open of ' . $greasing['total']],
        ] as $ring)
            <div class="flex items-center gap-3">
                <div class="relative h-16 w-16 shrink-0 rounded-full" aria-hidden="true"
                    style="background: conic-gradient({{ $ring['color'] }} 0% {{ $ring['percent'] }}%, var(--color-surface-muted) {{ $ring['percent'] }}% 100%)">
                    <div class="absolute inset-1.5 flex items-center justify-center rounded-full bg-surface text-xs font-bold {{ $ring['text'] }}">
                        {{ $ring['percent'] }}%
                    </div>
                </div>
                <div class="min-w-0">
                    <div class="text-sm font-semibold text-text">{{ $ring['label'] }} <span class="sr-only">{{ $ring['percent'] }}%</span></div>
                    <div class="text-xs text-text-muted">{{ $ring['detail'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($greasing['total'] === 0)
        <p class="mt-4 text-xs text-text-muted">No greasing schedule for this period</p>
    @endif
</section>
