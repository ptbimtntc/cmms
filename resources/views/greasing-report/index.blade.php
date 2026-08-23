@extends('layouts.app')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-800">Greasing Report</h1>
        <p class="text-sm text-slate-500">Closing &amp; Completion KPI, trend, and greasing/finding detail for the selected period.</p>
    </div>

    {{-- Filter --}}
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Period</label>
            <div class="flex overflow-hidden rounded-lg border border-slate-200">
                <label class="cursor-pointer px-4 py-2 text-sm font-medium transition {{ $periodType === 'monthly' ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-100' }}">
                    <input type="radio" name="period_type" value="monthly" class="hidden" onchange="this.form.submit()" {{ $periodType === 'monthly' ? 'checked' : '' }}>
                    Monthly
                </label>
                <label class="cursor-pointer px-4 py-2 text-sm font-medium transition {{ $periodType === 'yearly' ? 'bg-blue-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-100' }}">
                    <input type="radio" name="period_type" value="yearly" class="hidden" onchange="this.form.submit()" {{ $periodType === 'yearly' ? 'checked' : '' }}>
                    Yearly
                </label>
            </div>
        </div>

        @if ($periodType === 'monthly')
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Month</label>
                <select name="month" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                    @foreach ($months as $value => $label)
                        <option value="{{ $value }}" {{ (int) $month === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Year</label>
            <select name="year" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                @foreach ($years as $y)
                    <option value="{{ $y }}" {{ (int) $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                @endforeach
            </select>
        </div>

        @if (auth()->user()->isAdmin())
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Area</label>
                <select name="area" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                    <option value="" {{ $area ? '' : 'selected' }}>All</option>
                    <option value="WWD" {{ $area === 'WWD' ? 'selected' : '' }}>WWD</option>
                    <option value="BUL" {{ $area === 'BUL' ? 'selected' : '' }}>BUL</option>
                </select>
            </div>
        @endif

        <div class="ml-auto text-sm text-slate-500">
            Showing:
            <span class="font-semibold text-slate-800">
                {{ $periodType === 'monthly' ? \Carbon\Carbon::create(null, $month, 1)->format('F').' '.$year : $year }}
                @if ($area)
                    &middot; {{ $area }}
                @endif
            </span>
        </div>
    </form>

    {{-- KPI + Chart --}}
    <div class="mb-6 grid gap-4 md:grid-cols-2">
        {{-- Closing --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Closing %</p>
                    <p class="mt-1 text-3xl font-bold text-slate-900">{{ $kpi['closing_percent'] }}%</p>
                    <p class="mt-1 text-xs text-slate-500">(FINISH ON TIME + FINISH) / Total Schedule</p>
                    <p class="mt-2 text-xs text-slate-400">
                        {{ $kpi['finish_on_time'] }} Finish On Time + {{ $kpi['finish'] }} Finish of {{ $kpi['total'] }} Total
                    </p>
                </div>
                <div class="relative h-24 w-24 shrink-0 rounded-full" style="background: conic-gradient(#059669 0% {{ $kpi['closing_percent'] }}%, #e2e8f0 {{ $kpi['closing_percent'] }}% 100%)">
                    <div class="absolute inset-2 flex items-center justify-center rounded-full bg-white text-sm font-bold text-emerald-700">
                        {{ $kpi['closing_percent'] }}%
                    </div>
                </div>
            </div>

            @if ($periodType === 'yearly' && $monthlyTrend)
                <div class="mt-4 flex items-end gap-1.5 border-t border-slate-100 pt-6">
                    @foreach ($monthlyTrend as $m)
                        <div class="flex flex-1 flex-col items-center gap-1" title="{{ $m['label'] }}: {{ $m['total'] > 0 ? $m['closing_percent'].'%' : 'No schedule' }}">
                            <span class="text-[10px] font-medium {{ $m['total'] > 0 ? 'text-emerald-700' : 'text-slate-300' }}">
                                {{ $m['total'] > 0 ? $m['closing_percent'].'%' : '–' }}
                            </span>
                            {{-- Fixed-height track so the percentage bar below has a definite height to resolve against (a % height inside an auto-height flex item never renders). --}}
                            <div class="relative h-20 w-full">
                                <div class="absolute bottom-0 w-full rounded-t {{ $m['total'] > 0 ? 'bg-emerald-500' : 'bg-slate-100' }}" style="height: {{ $m['total'] > 0 ? max($m['closing_percent'], 4) : 4 }}%"></div>
                            </div>
                            <span class="text-[10px] text-slate-400">{{ $m['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Completion --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Completion %</p>
                    <p class="mt-1 text-3xl font-bold text-slate-900">{{ $kpi['completion_percent'] }}%</p>
                    <p class="mt-1 text-xs text-slate-500">(FINISH ON TIME x1 + FINISH x0.5) / Total Schedule</p>
                    <p class="mt-2 text-xs text-slate-400">
                        {{ $kpi['open'] }} Open of {{ $kpi['total'] }} Total
                    </p>
                </div>
                <div class="relative h-24 w-24 shrink-0 rounded-full" style="background: conic-gradient(#0284c7 0% {{ $kpi['completion_percent'] }}%, #e2e8f0 {{ $kpi['completion_percent'] }}% 100%)">
                    <div class="absolute inset-2 flex items-center justify-center rounded-full bg-white text-sm font-bold text-sky-700">
                        {{ $kpi['completion_percent'] }}%
                    </div>
                </div>
            </div>

            @if ($periodType === 'yearly' && $monthlyTrend)
                <div class="mt-4 flex items-end gap-1.5 border-t border-slate-100 pt-6">
                    @foreach ($monthlyTrend as $m)
                        <div class="flex flex-1 flex-col items-center gap-1" title="{{ $m['label'] }}: {{ $m['total'] > 0 ? $m['completion_percent'].'%' : 'No schedule' }}">
                            <span class="text-[10px] font-medium {{ $m['total'] > 0 ? 'text-sky-700' : 'text-slate-300' }}">
                                {{ $m['total'] > 0 ? $m['completion_percent'].'%' : '–' }}
                            </span>
                            <div class="relative h-20 w-full">
                                <div class="absolute bottom-0 w-full rounded-t {{ $m['total'] > 0 ? 'bg-sky-500' : 'bg-slate-100' }}" style="height: {{ $m['total'] > 0 ? max($m['completion_percent'], 4) : 4 }}%"></div>
                            </div>
                            <span class="text-[10px] text-slate-400">{{ $m['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Greasing Report (~75%) + Finding Report (~25%) side by side --}}
    <div class="grid items-start gap-4 lg:grid-cols-4">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-3">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Greasing Report</h2>
            </div>

            {{-- ============ DESKTOP: Table (md and up) ============ --}}
            <div class="hidden overflow-x-auto md:block">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Order Number</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Plan Date</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action Date</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Group</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Cycle</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">PIC</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Remarks</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Finding</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse ($greasings as $greasing)
                            <tr class="hover:bg-slate-50">
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $greasing->order_number ?? '-' }}</td>
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $greasing->plan_date->format('d M Y') }}</td>
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $greasing->action_date ? $greasing->action_date->format('d M Y') : '-' }}</td>
                                <td class="px-3 py-2 text-sm font-semibold text-slate-800">{{ $greasing->group->name ?? '-' }}</td>
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $greasing->cycle }}</td>
                                <td class="px-3 py-2 text-sm text-slate-700">{{ $greasing->pic ?? '-' }}</td>
                                <td class="px-3 py-2">
                                    <span @class([
                                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-amber-100 text-amber-700' => $greasing->status == 'OPEN',
                                        'bg-rose-100 text-rose-700' => $greasing->status == 'FINISH',
                                        'bg-emerald-100 text-emerald-700' => $greasing->status == 'FINISH ON TIME',
                                    ])>
                                        {{ $greasing->status }}
                                    </span>
                                </td>
                                <td class="max-w-50 truncate px-3 py-2 text-sm text-slate-500" title="{{ $greasing->remarks }}">{{ $greasing->remarks ?? '-' }}</td>
                                <td class="px-3 py-2 text-sm">
                                    @if ($greasing->findings_count > 0)
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">[ {{ $greasing->findings_count }} {{ \Illuminate\Support\Str::plural('Finding', $greasing->findings_count) }} ]</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-400">[ No Finding ]</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-8 text-center text-sm text-slate-500">No greasing schedule found for this period</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ============ MOBILE: Card List (below md) ============ --}}
            <div class="space-y-3 p-3 md:hidden">
                @forelse ($greasings as $greasing)
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div class="mb-3 flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-800">{{ $greasing->group->name ?? '-' }}</div>
                                <div class="text-xs text-slate-500">{{ $greasing->cycle }} • {{ $greasing->order_number ?? '-' }}</div>
                            </div>
                            <span @class([
                                'shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-amber-100 text-amber-700' => $greasing->status == 'OPEN',
                                'bg-rose-100 text-rose-700' => $greasing->status == 'FINISH',
                                'bg-emerald-100 text-emerald-700' => $greasing->status == 'FINISH ON TIME',
                            ])>
                                {{ $greasing->status }}
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                            <div>
                                <div class="text-slate-400">Plan Date</div>
                                <div class="font-medium text-slate-700">{{ $greasing->plan_date->format('d M Y') }}</div>
                            </div>
                            <div>
                                <div class="text-slate-400">Action Date</div>
                                <div class="font-medium text-slate-700">{{ $greasing->action_date ? $greasing->action_date->format('d M Y') : '-' }}</div>
                            </div>
                            <div>
                                <div class="text-slate-400">PIC</div>
                                <div class="font-medium text-slate-700">{{ $greasing->pic ?? '-' }}</div>
                            </div>
                            <div>
                                <div class="text-slate-400">Finding</div>
                                <div class="mt-0.5">
                                    @if ($greasing->findings_count > 0)
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">[ {{ $greasing->findings_count }} {{ \Illuminate\Support\Str::plural('Finding', $greasing->findings_count) }} ]</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-400">[ No Finding ]</span>
                                    @endif
                                </div>
                            </div>
                            @if ($greasing->remarks)
                                <div class="col-span-2">
                                    <div class="text-slate-400">Remarks</div>
                                    <div class="font-medium text-slate-700">{{ $greasing->remarks }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">No greasing schedule found for this period</p>
                @endforelse
            </div>

            <div class="border-t border-slate-100 p-3">
                {{ $greasings->links() }}
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm lg:col-span-1">
            <div class="border-b border-slate-200 px-4 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Finding Report</h2>
            </div>
            <div class="max-h-140 divide-y divide-slate-100 overflow-y-auto">
                @forelse ($findings as $finding)
                    <div @class([
                        'space-y-1 border-l-4 p-3 text-xs',
                        'border-amber-400 bg-amber-50/40' => $finding->status === 'OPEN',
                        'border-emerald-400 bg-emerald-50/40' => $finding->status === 'COMPLETED',
                    ])>
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold text-slate-800">{{ $finding->greasing->group->name ?? '-' }}</span>
                            <span @class([
                                'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                                'bg-emerald-600 text-white' => $finding->status === 'COMPLETED',
                                'bg-amber-500 text-white' => $finding->status === 'OPEN',
                            ])>
                                {{ $finding->status === 'COMPLETED' ? '✓ ' : '● ' }}{{ $finding->status }}
                            </span>
                        </div>
                        <p class="text-slate-500">Cycle: {{ $finding->greasing->cycle }}</p>
                        <p class="text-slate-500">PIC: {{ $finding->greasing->pic ?? '-' }}</p>
                        <p class="text-slate-500">Plan Date: {{ $finding->greasing->plan_date->format('d M Y') }}</p>
                        <p class="text-slate-700">Finding: {{ $finding->finding }}</p>

                        @if ($finding->status === 'OPEN')
                            <form action="{{ route('greasings.findings.update', [$finding->greasing, $finding]) }}" method="POST" class="mt-2 space-y-2 rounded-lg border border-amber-200 bg-white p-2">
                                @csrf
                                @method('PATCH')

                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400">Action Date</label>
                                    <input type="date" name="action_date" value="{{ optional($finding->action_date)->format('Y-m-d') }}"
                                        class="w-full rounded border border-slate-200 px-2 py-1 text-xs text-slate-700 focus:border-blue-500 focus:outline-none">
                                </div>

                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400">Action</label>
                                    <input type="text" name="action" value="{{ $finding->action }}" placeholder="Action taken"
                                        class="w-full rounded border border-slate-200 px-2 py-1 text-xs text-slate-700 focus:border-blue-500 focus:outline-none">
                                </div>

                                <div class="flex items-center gap-2">
                                    <select name="status" class="flex-1 rounded border border-slate-200 px-2 py-1 text-xs text-slate-700">
                                        <option value="OPEN" selected>OPEN</option>
                                        <option value="COMPLETED">COMPLETED</option>
                                    </select>
                                    <button class="rounded bg-slate-700 px-3 py-1 text-xs font-medium text-white transition hover:bg-slate-800">
                                        Save
                                    </button>
                                </div>
                            </form>
                        @else
                            <p class="text-slate-500">Action: {{ $finding->action ?? '-' }}</p>
                            <p class="text-slate-400">Action Date: {{ $finding->action_date ? $finding->action_date->format('d M Y') : '-' }}</p>
                        @endif
                    </div>
                @empty
                    <p class="p-4 text-xs text-slate-500">No finding recorded for this period.</p>
                @endforelse
            </div>
            <div class="border-t border-slate-100 p-2 text-xs">
                {{ $findings->links() }}
            </div>
        </div>
    </div>
@endsection
