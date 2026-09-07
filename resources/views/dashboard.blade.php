@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        @if ($isAdmin)
            <div class="flex items-center justify-end gap-2">
                <label for="dashAreaFilter" class="text-xs font-medium text-text-muted">Area</label>
                <select id="dashAreaFilter" onchange="dashFilter('area', this.value)" class="border border-border-strong bg-surface text-text rounded px-2 py-1 text-sm">
                    <option value="" {{ $selectedArea ? '' : 'selected' }}>ALL</option>
                    <option value="WWD" {{ $selectedArea === 'WWD' ? 'selected' : '' }}>WWD</option>
                    <option value="BUL" {{ $selectedArea === 'BUL' ? 'selected' : '' }}>BUL</option>
                </select>
            </div>
        @endif

        {{-- KPI Summary --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            @include('partials.dashboard-kpi-card', [
                'title' => 'PM Completion (%)  (Current Month)',
                'value' => $monthKpi['completion_percent'] . '%',
                'icon' =>
                    '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"/></svg>',
            ])

            @include('partials.dashboard-kpi-card', [
                'title' => 'Target Machine (Current Month)',
                'value' => $monthKpi['target'],
                'icon' =>
                    '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v4a1 1 0 001 1h3m10 0h3a1 1 0 001-1V7M7 7V5a2 2 0 012-2h6a2 2 0 012 2v2"/></svg>',
            ])

            @include('partials.dashboard-kpi-card', [
                'title' => 'Actual Machine (Current Month)',
                'value' => $monthKpi['actual'],
                'icon' =>
                    '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2v4h6v-4c0-1.105-1.343-2-3-2zM6 20h12"/></svg>',
            ])

            @include('partials.dashboard-kpi-card', [
                'title' => 'Remaining PM  (Current Month)',
                'value' => $monthKpi['remaining'],
                'icon' =>
                    '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/></svg>',
            ])

            @include('partials.dashboard-kpi-card', [
                'title' => 'Overdue PM (Current Month)',
                'value' => $overdue,
                'icon' =>
                    '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12A9 9 0 1112 3a9 9 0 019 9z"/></svg>',
            ])
        </div>

        {{-- Main content grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="lg:col-span-2 space-y-4">
                {{-- PM Completion Progress Card --}}
                @include('partials.dashboard-progress-card', [
                    'percentage' => $monthKpi['completion_percent'] . '%',
                    'percent_value' => $monthKpi['completion_percent'],
                    'target' => $monthKpi['target'],
                    'actual' => $monthKpi['actual'],
                    'remaining' => $monthKpi['remaining'],
                ])

                {{-- Completion by Area --}}
                @include('partials.dashboard-area-progress', [
                    'areas' => $areaProgress,
                ])


            </div>

            <div class="space-y-4">
                @include('partials.dashboard-greasing-card', [
                    'greasing' => $greasing,
                    'subtitle' => $greasingSubtitle,
                    'years' => $greasingYears,
                    'selectedYear' => $selectedGreasingYear,
                    'selectedMonth' => $selectedGreasingMonth,
                ])

                {{-- Quick Actions --}}
                <div class="grid grid-cols-2 gap-3">
                    <a href="#" class="bg-white rounded-xl shadow-sm p-4 flex items-center space-x-3">
                        <div class="p-2 bg-indigo-50 text-indigo-600 rounded-lg">
                            <!-- PM Schedule Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 002-2V7H3v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">PM Schedule</div>
                            <div class="text-sm font-medium text-gray-800">Open</div>
                        </div>
                    </a>

                    <a href="#" class="bg-white rounded-xl shadow-sm p-4 flex items-center space-x-3">
                        <div class="p-2 bg-green-50 text-green-600 rounded-lg">
                            <!-- Scan QR Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 7h4V3m0 0H3v4m0 0v10a2 2 0 002 2h10v-2H7a2 2 0 01-2-2V7zM21 17h-4v4h4v-4zM17 3h4v4h-4V3zM7 17h10v-4H7v4z" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Scan QR</div>
                            <div class="text-sm font-medium text-gray-800">Scanner</div>
                        </div>
                    </a>

                    <a href="#" class="bg-white rounded-xl shadow-sm p-4 flex items-center space-x-3">
                        <div class="p-2 bg-yellow-50 text-yellow-600 rounded-lg">
                            <!-- Reports Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 17v-6a2 2 0 012-2h2a2 2 0 012 2v6M7 21h10" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Reports</div>
                            <div class="text-sm font-medium text-gray-800">View</div>
                        </div>
                    </a>

                    <a href="#" class="bg-white rounded-xl shadow-sm p-4 flex items-center space-x-3">
                        <div class="p-2 bg-pink-50 text-pink-600 rounded-lg">
                            <!-- Machine Master Icon -->
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 8c-1.657 0-3 .895-3 2v4h6v-4c0-1.105-1.343-2-3-2zM6 20h12" />
                            </svg>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500">Machine Master</div>
                            <div class="text-sm font-medium text-gray-800">Manage</div>
                        </div>
                    </a>
                </div>

            </div>
        </div>

        {{-- ROW 1: PM Completion Trend + PM Status Breakdown — 50/50, PM stays the most prominent row --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @include('partials.dashboard-chart-card', [
                'title' => 'PM Completion Trend',
                'subtitle' => 'Actual vs ' . $pmTargetPercent . '% target · gray bars = no PM planned that month',
                'id' => 'pmTrendChart',
                'height' => 'h-72 md:h-[380px]',
                'filters' =>
                    '<select onchange="dashFilter(\'year\', this.value)" class="border rounded px-2 py-1">' .
                    collect($years)->map(fn($y) => '<option value="' .
                        $y .
                        '" ' .
                        ($y == $selectedYear ? 'selected' : '') .
                        '>' .
                        $y .
                        '</option>')->implode('') .
                    '</select>',
            ])

            @include('partials.dashboard-donut-card', [
                'title' => 'PM Status Breakdown',
                'subtitle' => $statusSubtitle,
                'legend' => $statusLegend,
                'id' => 'pmStatusDonut',
                'size' => 240,
                'height' => 'h-72 md:h-[380px]',
                'filters' =>
                    '<select onchange="dashFilter(\'year\', this.value)" class="border rounded px-1.5 py-1">' .
                    collect($years)->map(fn($y) => '<option value="' .
                        $y .
                        '" ' .
                        ($y == $selectedYear ? 'selected' : '') .
                        '>' .
                        $y .
                        '</option>')->implode('') .
                    '</select> ' .
                    '<select onchange="dashFilter(\'month\', this.value)" class="border rounded px-1.5 py-1">' .
                    '<option value="">All Months</option>' .
                    collect(range(1, 12))->map(fn($m) => '<option value="' .
                        $m .
                        '" ' .
                        ($m == $selectedMonth ? 'selected' : '') .
                        '>' .
                        \Carbon\Carbon::create(null, $m, 1)->format('F') .
                        '</option>')->implode('') .
                    '</select>',
            ])
        </div>

        {{-- ROW 2: Sparepart Usage + Greasing — 50/50 --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @include('partials.dashboard-top-bars', [
                'title' => 'Top 10 Sparepart Usage',
                'items' => $topSpareparts,
                'filters' =>
                    '<select onchange="dashFilter(\'year\', this.value)" class="border rounded px-2 py-1">' .
                    collect($years)->map(fn($y) => '<option value="' .
                        $y .
                        '" ' .
                        ($y == $selectedYear ? 'selected' : '') .
                        '>' .
                        $y .
                        '</option>')->implode('') .
                    '</select>',
            ])

            {{-- Top 10 Problem Categories --}}
            @include('partials.dashboard-top-bars', [
                'title' => 'Top 10 Problem Categories',
                'items' => $topProblems,
                'filters' =>
                    '<select onchange="dashFilter(\'year\', this.value)" class="border rounded px-2 py-1">' .
                    collect($years)->map(fn($y) => '<option value="' .
                        $y .
                        '" ' .
                        ($y == $selectedYear ? 'selected' : '') .
                        '>' .
                        $y .
                        '</option>')->implode('') .
                    '</select>',
            ])

            {{-- Today's PM Schedule --}}
            @include('partials.dashboard-today-schedule-table', [
                'schedules' => $todaySchedules,
            ])


        </div>

        {{-- ROW 3: Oil Audit (WWD-only module — hidden entirely for BUL roles) --}}
        @if ($oilAudit)
            @include('partials.dashboard-oil-audit-card', [
                'oilAudit' => $oilAudit,
            ])
        @endif


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
            const paddingLeft = 34;
            const paddingBottom = 28;
            const paddingTop = 22;
            const max = 100;

            ctx.clearRect(0, 0, w, h);

            if (points.length === 0) {
                ctx.fillStyle = '#9ca3af';
                ctx.font = '12px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('No data available', w / 2, h / 2);
                return;
            }

            const plotHeight = h - paddingBottom - paddingTop;
            const plotWidth = w - paddingLeft - 10;
            const yOf = (v) => (h - paddingBottom) - (v / max) * plotHeight;

            // Y-axis gridlines at 0/25/50/75/100 for readability at this larger size.
            ctx.strokeStyle = '#f1f5f9';
            ctx.fillStyle = '#9ca3af';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'right';
            [0, 25, 50, 75, 100].forEach((v) => {
                const y = yOf(v);
                ctx.beginPath();
                ctx.moveTo(paddingLeft, y);
                ctx.lineTo(w - 4, y);
                ctx.stroke();
                ctx.fillText(v + '%', paddingLeft - 6, y + 3);
            });

            const slot = plotWidth / points.length;
            const barWidth = Math.min(slot * 0.5, 42);

            points.forEach((p, i) => {
                const cx = paddingLeft + slot * i + slot / 2;
                const barX = cx - barWidth / 2;
                const isCurrent = p.label === currentLabel;
                const barValue = p.hasData ? p.value : 0;
                const barY = yOf(barValue);

                ctx.fillStyle = !p.hasData ? '#e5e7eb' : (isCurrent ? '#4338ca' : '#818cf8');
                ctx.beginPath();
                const r = 4;
                ctx.moveTo(barX, h - paddingBottom);
                ctx.lineTo(barX, barY + r);
                ctx.arcTo(barX, barY, barX + r, barY, r);
                ctx.lineTo(barX + barWidth - r, barY);
                ctx.arcTo(barX + barWidth, barY, barX + barWidth, barY + r, r);
                ctx.lineTo(barX + barWidth, h - paddingBottom);
                ctx.closePath();
                ctx.fill();

                ctx.fillStyle = p.hasData ? '#374151' : '#9ca3af';
                ctx.font = isCurrent ? 'bold 11px sans-serif' : '11px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText(p.hasData ? p.value + '%' : '–', cx, barY - 6);

                ctx.fillStyle = '#9ca3af';
                ctx.font = '10px sans-serif';
                ctx.fillText(p.label, cx, h - paddingBottom + 16);
            });

            // Target reference line, drawn last so it sits on top of the bars.
            const targetY = yOf(targetPercent);
            ctx.strokeStyle = '#ef4444';
            ctx.setLineDash([5, 4]);
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(paddingLeft, targetY);
            ctx.lineTo(w - 4, targetY);
            ctx.stroke();
            ctx.setLineDash([]);

            ctx.fillStyle = '#ef4444';
            ctx.font = 'bold 10px sans-serif';
            ctx.textAlign = 'left';
            ctx.fillText('Target ' + targetPercent + '%', paddingLeft + 4, targetY - 5);
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
            const total = segments.reduce((sum, seg) => sum + seg.value, 0);

            ctx.clearRect(0, 0, w, h);

            if (total === 0) {
                ctx.fillStyle = '#9ca3af';
                ctx.font = '12px sans-serif';
                ctx.textAlign = 'center';
                ctx.fillText('No data available', cx, cy);
                return;
            }

            let start = -Math.PI / 2;
            segments.forEach((seg) => {
                if (seg.value <= 0) return;
                const angle = (seg.value / total) * Math.PI * 2;
                ctx.beginPath();
                ctx.moveTo(cx, cy);
                ctx.arc(cx, cy, radius, start, start + angle);
                ctx.closePath();
                ctx.fillStyle = seg.color;
                ctx.fill();
                start += angle;
            });

            ctx.beginPath();
            ctx.arc(cx, cy, radius * 0.62, 0, Math.PI * 2);
            ctx.fillStyle = '#ffffff';
            ctx.fill();

            ctx.fillStyle = '#1f2937';
            ctx.font = 'bold ' + Math.round(radius * 0.32) + 'px sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(total, cx, cy - radius * 0.08);

            ctx.fillStyle = '#9ca3af';
            ctx.font = Math.round(radius * 0.12) + 'px sans-serif';
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
            drawBarChart('pmTrendChart', points, {{ $pmTargetPercent }}, currentLabel);

            const breakdown = @json($statusBreakdown);
            drawDonutChart('pmStatusDonut', [{
                label: 'Open',
                value: breakdown.OPEN,
                color: '#f59e0b'
            },
            {
                label: 'In Progress',
                value: breakdown.IN_PROGRESS,
                color: '#94a3b8'
            },
            {
                label: 'Finished',
                value: breakdown.FINISHED,
                color: '#8b5cf6'
            },
            {
                label: 'Finished On Time',
                value: breakdown.FINISHED_ON_TIME,
                color: '#10b981'
            },
            {
                label: 'Missed',
                value: breakdown.MISSED,
                color: '#f43f5e'
            },
            ]);
        });
    </script>
@endsection