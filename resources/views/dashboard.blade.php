@extends('layouts.app')

@php
    $fullWidth = true;

    // One colour per PM status, shared by the donut and its legend so the two always agree.
    // Validated with the dataviz palette checker (adjacent CVD ΔE >= 8.1). Legend labels
    // carry identity, so no segment depends on colour alone.
    $statusColors = [
        'Open' => '#f59e0b',
        'In Progress' => '#2563eb',
        'Finished' => '#10b981',
        'Finished On Time' => '#047857',
        'Missed' => '#d32f2f',
    ];

    $yearOptions = collect($years)->mapWithKeys(fn ($y) => [$y => $y])->all();
    $monthOptions = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(null, $m, 1)->format('F')])->all();
@endphp

@section('content')
    <div class="space-y-6">
        <x-ui.page-header title="PM this month"
            :description="now()->format('F Y') . ($isAdmin ? ' · ' . ($selectedArea ?: 'All areas') : '')">
            <x-slot:actions>
                @if ($isAdmin)
                    <div class="flex items-center gap-2">
                        <label for="dashAreaFilter" class="text-xs font-medium text-text-muted">Area</label>
                        <select id="dashAreaFilter" onchange="dashFilter('area', this.value)"
                            class="min-h-11 rounded-control border border-border-strong bg-surface px-2 text-sm text-text lg:min-h-9">
                            <option value="" {{ $selectedArea ? '' : 'selected' }}>ALL</option>
                            @foreach ($areas as $areaOption)
                                <option value="{{ $areaOption }}" {{ $selectedArea === $areaOption ? 'selected' : '' }}>{{ $areaOption }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <x-ui.button variant="outline" href="{{ route('qr.scan') }}">Scan QR</x-ui.button>
                <x-ui.button href="{{ route('pm-schedules.index') }}">Open PM Schedule</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- This month: the focal closing figure, then what needs attention. --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="lg:col-span-2">
                @include('partials.dashboard-progress-card', [
                    'percent_value' => $monthKpi['completion_percent'],
                    'target' => $monthKpi['target'],
                    'actual' => $monthKpi['actual'],
                    'remaining' => $monthKpi['remaining'],
                    'targetPercent' => $pmTargetPercent,
                ])
            </div>

            {{-- Missed counts every PM still in MISSED status, across all months (not only this one). --}}
            <a href="{{ route('pm-schedules.index', array_filter(['status' => ['MISSED'], 'area' => $selectedArea])) }}"
                @class([
                    'card group flex flex-col justify-between gap-4 p-5 transition-colors',
                    'border-danger/40 bg-danger-light hover:bg-danger-light/70' => $overdue > 0,
                    'hover:bg-surface-sunken' => $overdue === 0,
                ])>
                <div>
                    <h2 class="text-sm font-semibold text-text">Missed PM</h2>
                    <p class="text-xs text-text-muted">All months, still in Missed status</p>
                </div>
                <div class="flex items-end justify-between gap-3">
                    <span @class(['font-signage text-6xl font-semibold leading-none', 'text-danger-hover' => $overdue > 0, 'text-text' => $overdue === 0])>{{ $overdue }}</span>
                    <span class="text-sm font-medium text-primary group-hover:underline">
                        {{ $overdue > 0 ? 'Review missed PM' : 'Nothing missed' }}
                    </span>
                </div>
            </a>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            @include('partials.dashboard-area-progress', [
                'areas' => $areaProgress,
            ])

            <div class="lg:col-span-2">
                @include('partials.dashboard-greasing-card', [
                    'greasing' => $greasing,
                    'subtitle' => $greasingSubtitle,
                    'years' => $greasingYears,
                    'selectedYear' => $selectedGreasingYear,
                    'selectedMonth' => $selectedGreasingMonth,
                ])
            </div>
        </div>

        {{-- Oil Audit (WWD-only module, hidden entirely for BUL roles) --}}
        @if ($oilAudit)
            @include('partials.dashboard-oil-audit-card', [
                'oilAudit' => $oilAudit,
            ])
        @endif

        {{-- Year view. One Year filter scopes every chart below it. --}}
        <section class="space-y-4 pt-2" aria-labelledby="year-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="year-heading" class="font-signage text-xl font-semibold text-text">Year {{ $selectedYear }}</h2>
                    <p class="text-sm text-text-muted">Trend, status, spareparts and problems for the selected year.</p>
                </div>
                @include('partials.dashboard-select', [
                    'id' => 'dashYear', 'param' => 'year', 'label' => 'Year',
                    'options' => $yearOptions, 'selected' => $selectedYear,
                ])
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @include('partials.dashboard-chart-card', [
                    'title' => 'Did monthly PM closing reach ' . $pmTargetPercent . '%?',
                    'subtitle' => 'Closing per month. Grey bars: no PM planned that month.',
                    'summary' => 'Bar chart of monthly PM closing for ' . $selectedYear . ' against a ' . $pmTargetPercent . '% target. Each bar is labelled with its value.',
                    'id' => 'pmTrendChart',
                    'height' => 'h-80 md:h-[380px]',
                ])

                @include('partials.dashboard-donut-card', [
                    'title' => 'PM Status',
                    'subtitle' => $statusSubtitle,
                    'legend' => $statusLegend,
                    'colors' => $statusColors,
                    'id' => 'pmStatusDonut',
                    'size' => 240,
                    'height' => 'md:h-[380px]',
                    'filters' => view('partials.dashboard-select', [
                        'id' => 'dashStatusMonth', 'param' => 'month', 'label' => 'Month',
                        'options' => $monthOptions, 'selected' => $selectedMonth, 'allLabel' => 'All Months',
                    ])->render(),
                ])
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @include('partials.dashboard-top-bars', [
                    'title' => 'Top 10 Sparepart Usage',
                    'items' => $topSpareparts,
                    'emptyText' => 'Sparepart usage appears once PM checklists record parts.',
                ])

                @include('partials.dashboard-top-bars', [
                    'title' => 'Top 10 Problem Categories',
                    'items' => $topProblems,
                    'emptyText' => 'Problem categories appear once findings are recorded.',
                ])
            </div>
        </section>
    </div>

    <script>
        function dashFilter(param, value) {
            const url = new URL(window.location.href);
            if (value === '' || value === null) {
                url.searchParams.delete(param);
            } else {
                url.searchParams.set(param, value);
            }
            window.location.href = url.toString();
        }

        // Chart colours mirror tokens in resources/css/theme.css (canvas cannot read Tailwind classes).
        const CHART = {
            bar: '#0284c7',        // --color-chart-1
            barCurrent: '#075985', // --color-chart-1-strong
            empty: '#e2e8f0',      // --color-border
            grid: '#e2e8f0',
            axis: '#5b6b80',       // --color-text-muted
            ink: '#1e293b',        // --color-text
            surface: '#ffffff',
            font: '"Instrument Sans", ui-sans-serif, system-ui, sans-serif',
        };

        function drawBarChart(canvasId, points, targetPercent, currentLabel) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const dpr = window.devicePixelRatio || 1;
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * dpr;
            canvas.height = rect.height * dpr;
            const ctx = canvas.getContext('2d');
            ctx.scale(dpr, dpr);

            const w = rect.width;
            const h = rect.height;
            const paddingLeft = 36;
            const paddingBottom = 28;
            const paddingTop = 22;
            const max = 100;

            ctx.clearRect(0, 0, w, h);

            if (points.length === 0) {
                ctx.fillStyle = CHART.axis;
                ctx.font = '13px ' + CHART.font;
                ctx.textAlign = 'center';
                ctx.fillText('No PM planned in this year yet', w / 2, h / 2);
                return;
            }

            const plotHeight = h - paddingBottom - paddingTop;
            const plotWidth = w - paddingLeft - 10;
            const yOf = (v) => (h - paddingBottom) - (v / max) * plotHeight;

            // Solid hairline gridlines; dashes are reserved for the target line.
            ctx.strokeStyle = CHART.grid;
            ctx.lineWidth = 1;
            ctx.fillStyle = CHART.axis;
            ctx.font = '11px ' + CHART.font;
            ctx.textAlign = 'right';
            [0, 25, 50, 75, 100].forEach((v) => {
                const y = Math.round(yOf(v)) + 0.5;
                ctx.beginPath();
                ctx.moveTo(paddingLeft, y);
                ctx.lineTo(w - 4, y);
                ctx.stroke();
                ctx.fillText(v + '%', paddingLeft - 6, y + 4);
            });

            const slot = plotWidth / points.length;
            const barWidth = Math.max(Math.min(slot - 2, slot * 0.6, 36), 4);
            const showAllValues = slot >= 28;
            // On a phone twelve "Jan".."Dec" labels collide; fall back to initials (J F M ...).
            const shortLabels = slot < 26;

            points.forEach((p, i) => {
                const cx = paddingLeft + slot * i + slot / 2;
                const barX = cx - barWidth / 2;
                const isCurrent = p.label === currentLabel;
                const barValue = p.hasData ? Math.max(p.value, 0) : 0;
                const barY = p.hasData ? yOf(barValue) : yOf(4);

                // Rounded data-end, square at the baseline.
                ctx.fillStyle = !p.hasData ? CHART.empty : (isCurrent ? CHART.barCurrent : CHART.bar);
                const r = Math.min(4, barWidth / 2, (h - paddingBottom - barY) || 0);
                ctx.beginPath();
                ctx.moveTo(barX, h - paddingBottom);
                ctx.lineTo(barX, barY + r);
                ctx.arcTo(barX, barY, barX + r, barY, r);
                ctx.lineTo(barX + barWidth - r, barY);
                ctx.arcTo(barX + barWidth, barY, barX + barWidth, barY + r, r);
                ctx.lineTo(barX + barWidth, h - paddingBottom);
                ctx.closePath();
                ctx.fill();

                if (p.hasData && (showAllValues || isCurrent)) {
                    ctx.fillStyle = CHART.ink;
                    ctx.font = (isCurrent ? '600 ' : '') + '11px ' + CHART.font;
                    ctx.textAlign = 'center';
                    ctx.fillText(p.value + '%', cx, barY - 6);
                }

                ctx.fillStyle = isCurrent ? CHART.ink : CHART.axis;
                ctx.font = (isCurrent ? '600 ' : '') + '11px ' + CHART.font;
                ctx.textAlign = 'center';
                ctx.fillText(shortLabels ? p.label.charAt(0) : p.label, cx, h - paddingBottom + 17);
            });

            // Target reference line in ink, drawn last so it sits on top of the bars.
            const targetY = Math.round(yOf(targetPercent)) + 0.5;
            ctx.strokeStyle = CHART.ink;
            ctx.setLineDash([5, 4]);
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(paddingLeft, targetY);
            ctx.lineTo(w - 4, targetY);
            ctx.stroke();
            ctx.setLineDash([]);

            const label = 'Target ' + targetPercent + '%';
            ctx.font = '600 11px ' + CHART.font;
            const lw = ctx.measureText(label).width;
            ctx.fillStyle = CHART.surface;
            ctx.fillRect(w - lw - 12, targetY - 16, lw + 8, 14);
            ctx.fillStyle = CHART.ink;
            ctx.textAlign = 'right';
            ctx.fillText(label, w - 8, targetY - 5);
        }

        function drawDonutChart(canvasId, segments) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;

            const dpr = window.devicePixelRatio || 1;
            const rect = canvas.getBoundingClientRect();
            canvas.width = rect.width * dpr;
            canvas.height = rect.height * dpr;
            const ctx = canvas.getContext('2d');
            ctx.scale(dpr, dpr);

            const w = rect.width;
            const h = rect.height;
            const cx = w / 2;
            const cy = h / 2;
            const radius = Math.min(w, h) / 2 - 6;
            const inner = radius * 0.64;
            const total = segments.reduce((sum, seg) => sum + seg.value, 0);

            ctx.clearRect(0, 0, w, h);

            if (total === 0) {
                ctx.beginPath();
                ctx.arc(cx, cy, radius, 0, Math.PI * 2);
                ctx.arc(cx, cy, inner, 0, Math.PI * 2, true);
                ctx.fillStyle = CHART.empty;
                ctx.fill();
                ctx.fillStyle = CHART.axis;
                ctx.font = '13px ' + CHART.font;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('No PM in this period', cx, cy);
                ctx.textBaseline = 'alphabetic';
                return;
            }

            let start = -Math.PI / 2;
            segments.forEach((seg) => {
                if (seg.value <= 0) return;
                const angle = (seg.value / total) * Math.PI * 2;
                ctx.beginPath();
                ctx.arc(cx, cy, radius, start, start + angle);
                ctx.arc(cx, cy, inner, start + angle, start, true);
                ctx.closePath();
                ctx.fillStyle = seg.color;
                ctx.fill();
                // 2px surface gap between segments instead of a border.
                ctx.strokeStyle = CHART.surface;
                ctx.lineWidth = 2;
                ctx.stroke();
                start += angle;
            });

            ctx.fillStyle = CHART.ink;
            ctx.font = '600 ' + Math.round(radius * 0.34) + 'px ' + CHART.font;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(total, cx, cy - radius * 0.08);

            ctx.fillStyle = CHART.axis;
            ctx.font = Math.round(radius * 0.12) + 'px ' + CHART.font;
            ctx.fillText('Total PM', cx, cy + radius * 0.2);
            ctx.textBaseline = 'alphabetic';
        }

        document.addEventListener('DOMContentLoaded', function () {
            const trend = @json($trend->values());
            const points = trend.map((t) => ({
                label: t.label,
                value: t.completion_percent,
                hasData: t.target > 0,
            }));
            const currentLabel = {!! $selectedYear == now()->year ? json_encode(now()->format('M')) : 'null' !!};
            const statusColors = @json($statusColors);
            const breakdown = @json($statusBreakdown);
            const segments = [
                { label: 'Open', value: breakdown.OPEN },
                { label: 'In Progress', value: breakdown.IN_PROGRESS },
                { label: 'Finished', value: breakdown.FINISHED },
                { label: 'Finished On Time', value: breakdown.FINISHED_ON_TIME },
                { label: 'Missed', value: breakdown.MISSED },
            ].map((s) => ({ ...s, color: statusColors[s.label] }));

            const draw = () => {
                drawBarChart('pmTrendChart', points, {{ $pmTargetPercent }}, currentLabel);
                drawDonutChart('pmStatusDonut', segments);
            };

            // Draw once the web font is in, so canvas text uses Instrument Sans; redraw on resize.
            (document.fonts ? document.fonts.ready : Promise.resolve()).then(draw);
            let resizeTimer;
            window.addEventListener('resize', () => {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(draw, 150);
            });
        });
    </script>
@endsection
