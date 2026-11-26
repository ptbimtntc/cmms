{{-- Completion per task type (monthly or yearly, like the Greasing Report) plus the all-time dead-blower ranking. --}}
@php
    $label = 'text-xs font-medium text-text-muted';
    $ringColors = ['closing_percent' => 'var(--color-success)', 'completion_percent' => 'var(--color-chart-1)'];
    $ringBars = ['closing_percent' => 'bg-success', 'completion_percent' => 'bg-chart-1'];
    $showing = ($periodType === 'monthly' && count($selectedMonths)
            ? collect($selectedMonths)->sort()->map(fn ($m) => \Carbon\Carbon::create(null, $m, 1)->format('F'))->implode(', ').' '.$year
            : $year).($area ? ' · '.$area : '');
@endphp

<form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <fieldset class="grid gap-1.5">
        <legend class="{{ $label }} mb-1.5">Period</legend>
        <div class="inline-flex rounded-control border border-border-strong bg-surface-muted p-1">
            @foreach (['monthly' => 'Monthly', 'yearly' => 'Yearly'] as $value => $text)
                <label @class([
                    'inline-flex min-h-10 cursor-pointer items-center rounded-[calc(var(--radius-control)-4px)] px-4 lg:min-h-8 text-sm font-semibold transition-colors has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-primary',
                    'bg-primary text-white shadow-sm' => $periodType === $value,
                    'text-text-muted hover:bg-surface hover:text-text' => $periodType !== $value,
                ])>
                    <input type="radio" name="period_type" value="{{ $value }}" class="sr-only" onchange="this.form.submit()" @checked($periodType === $value)>
                    {{ $text }}
                </label>
            @endforeach
        </div>
    </fieldset>
    <div class="grid gap-1.5 sm:w-28">
        <label for="spm-dash-year" class="{{ $label }}">Year</label>
        <select id="spm-dash-year" name="year" onchange="this.form.submit()" class="select lg:min-h-9">
            @foreach ($years as $y)
                <option value="{{ $y }}" @selected((int) $year === $y)>{{ $y }}</option>
            @endforeach
        </select>
    </div>
    @if ($isAdmin)
        <div class="grid gap-1.5 sm:w-32">
            <label for="spm-dash-area" class="{{ $label }}">Area</label>
            <select id="spm-dash-area" name="area" onchange="this.form.submit()" class="select lg:min-h-9">
                <option value="" @selected(! $area)>All Area</option>
                @foreach ($areas as $areaOption)
                    <option value="{{ $areaOption }}" @selected($area === $areaOption)>{{ $areaOption }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if ($periodType === 'monthly')
        <div class="grid gap-1.5">
            <span class="{{ $label }}">Month</span>
            <x-checkbox-dropdown name="month" label="Month" :options="$months" :selected="$selectedMonths" :auto-submit="true" />
        </div>
    @endif
    <div class="flex gap-2 sm:ml-auto">
        <x-ui.button variant="ghost" href="{{ route('small-pm.index', ['tab' => $tab]) }}" class="lg:min-h-9">Reset</x-ui.button>
    </div>
    <p class="w-full text-sm text-text-muted">Showing: <span class="font-semibold text-text">{{ $showing }}</span></p>
</form>

{{-- Closing % and Completion %, per task type, for the selected period. Each ring's Jan-Dec trend shows only in the yearly view. --}}
@foreach ($kpis as $taskType => $kpi)
    <section aria-labelledby="spm-dash-heading-{{ $taskType }}">
        <h2 id="spm-dash-heading-{{ $taskType }}" class="text-sm font-semibold text-text">{{ $kpi['label'] }}</h2>

        {{-- Shown even at 0 total: WWD's cycle runs cleaning_motor the first half of the year and
             filter_panel the second half, so one of the two is legitimately empty half the time.
             That is not missing data, so it gets the same ring (0%) as any other period, not a warning box. --}}
        <div class="mt-2 grid gap-4 md:grid-cols-2">
            @foreach (['closing_percent' => 'Closing %', 'completion_percent' => 'Completion %'] as $key => $title)
                <div class="card min-w-0 p-5" aria-label="{{ $kpi['label'] }} {{ $title }}">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-text-muted">{{ $title }}</p>
                            <p class="mt-1 font-signage text-4xl font-semibold tabular-nums text-text">{{ $kpi[$key] }}%</p>
                            <p class="mt-1 text-xs text-text-muted">
                                {{ $kpi['finish_on_time'] }} Finish On Time, {{ $kpi['finish'] }} Finish, {{ $kpi['open'] }} Open dari {{ $kpi['total'] }} Total.
                            </p>
                        </div>
                        <div class="relative h-20 w-20 shrink-0 rounded-full" aria-hidden="true"
                            style="background: conic-gradient({{ $ringColors[$key] }} 0% {{ $kpi[$key] }}%, var(--color-surface-muted) {{ $kpi[$key] }}% 100%)">
                            <div class="absolute inset-2 rounded-full bg-surface"></div>
                        </div>
                    </div>

                    @if ($periodType === 'yearly' && $monthlyTrend)
                        <div class="mt-4 flex items-end gap-1.5 border-t border-border pt-5">
                            @foreach ($monthlyTrend[$taskType] as $m)
                                @php($has = $m['total'] > 0)
                                <div class="flex min-w-0 flex-1 flex-col items-center gap-1" title="{{ $m['label'] }}: {{ $has ? $m[$key].'%' : 'No schedule' }}">
                                    <span @class(['hidden text-[10px] font-semibold tabular-nums sm:block', 'text-text' => $has, 'text-text-muted' => ! $has])>{{ $has ? $m[$key].'%' : '-' }}</span>
                                    <div class="relative h-16 w-full">
                                        <div class="absolute bottom-0 w-full rounded-t {{ $has ? $ringBars[$key] : 'bg-surface-muted' }}" style="height: {{ $has ? max($m[$key], 4) : 4 }}%"></div>
                                    </div>
                                    <span class="text-[10px] text-text-muted">{{ $m['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endforeach

{{-- Other info: schedule still OPEN per task type, and open dead-blower findings right now. --}}
<div class="grid gap-4 sm:grid-cols-3">
    @foreach ($kpis as $taskType => $kpi)
        <a href="{{ route('small-pm.index', ['tab' => $kpi['tab'], 'status' => ['OPEN']]) }}"
            class="card group flex flex-col justify-between gap-3 p-4 transition-colors hover:bg-surface-sunken">
            <p class="text-sm font-semibold text-text">{{ $kpi['label'] }} - OPEN</p>
            <div class="flex items-end justify-between gap-3">
                <span class="font-signage text-3xl font-semibold leading-none text-text">{{ $openCounts[$taskType] }}</span>
                <span class="text-sm font-medium text-primary group-hover:underline">Lihat schedule</span>
            </div>
        </a>
    @endforeach
    <a href="{{ route('small-pm.index', ['tab' => \App\Http\Controllers\SmallPmController::FINDINGS_TAB]) }}"
        @class([
            'card group flex flex-col justify-between gap-3 p-4 transition-colors',
            'border-warning/40 bg-warning-light hover:bg-warning-light/70' => $openFindings > 0,
            'hover:bg-surface-sunken' => $openFindings === 0,
        ])>
        <p class="text-sm font-semibold text-text">Temuan Blower Mati - OPEN</p>
        <div class="flex items-end justify-between gap-3">
            <span @class(['font-signage text-3xl font-semibold leading-none', 'text-warning-text' => $openFindings > 0, 'text-text' => $openFindings === 0])>{{ $openFindings }}</span>
            <span class="text-sm font-medium text-primary group-hover:underline">{{ $openFindings > 0 ? 'Tindak lanjuti' : 'Tidak ada temuan terbuka' }}</span>
        </div>
    </a>
</div>

@include('partials.dashboard-top-bars', [
    'title' => 'Mesin Dengan Blower Sering Mati (All-time, lebih dari 2x)',
    'items' => $topBlowerMachines,
    'emptyText' => 'Belum ada mesin dengan lebih dari 2 temuan blower mati.',
])
