@extends('layouts.app')

@section('title', 'PM Status — '.$currentArea)

@section('content')

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">PM Status — {{ $currentArea }}</h1>
            <p class="text-slate-500">
                Informasi status pelaksanaan Preventive Maintenance.
            </p>
        </div>

        {{-- AREA SWITCHER — navigation to each area's own URL, not a filter. --}}
        <div class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-1" role="group" aria-label="Area switcher">
            @foreach (\App\Http\Controllers\PMStatusBoardController::AREA_CODES as $slug => $code)
                <a href="{{ route('pm-status.show', $slug) }}"
                    class="rounded-md px-4 py-2 text-sm font-semibold transition
                    {{ $areaSlug === $slug ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-200' }}">
                    {{ $code }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- LEGEND --}}
    <div class="mb-6 rounded-lg border border-slate-200 bg-blue-50 p-4 text-sm text-slate-600">
        <p class="font-medium text-slate-800 mb-2">ℹ️ Informasi</p>
        <p><strong>GAP DAY</strong> = Today − Plan Date</p>
        <p class="mt-1">Batas normal: <strong>-14 sampai +14 hari</strong></p>
        <p class="mt-1 text-slate-500">GAP DAY merah menunjukkan sudah di luar batas ±14 hari, sebaiknya tidak dipilih untuk mesin pengganti.</p>
    </div>

    {{-- FILTER FORM — period only; area is fixed by the URL above. --}}
    <form method="GET" class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <div class="flex flex-wrap gap-3">
            <div class="flex gap-2">
                <select name="plan_month" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $currentMonth == $m ? 'selected' : '' }}>
                            {{ $months[$m - 1] }}
                        </option>
                    @endfor
                </select>

                <select name="plan_year" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" {{ $currentYear == $y ? 'selected' : '' }}>
                            {{ $y }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit"
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                Filter
            </button>

            <a href="{{ route('pm-status.show', $areaSlug) }}"
                class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">
                Reset
            </a>
        </div>
    </form>
    
    {{-- DESKTOP TABLE --}}
    <div class="mb-4 hidden rounded-xl border border-slate-200 bg-white overflow-hidden md:block">
        <table class="w-full">
            <thead class="bg-slate-100 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Status</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Machine</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Plan Date</th>
                    <th class="px-4 py-3 text-center text-sm font-semibold text-slate-700">Gap Day</th>
                    <th class="px-4 py-3 text-left text-sm font-semibold text-slate-700">Actual PM Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($schedules as $schedule)
                    <tr class="hover:bg-slate-50 transition">
                        <td class="px-4 py-3">
                            <span
                                class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold
                                {{ $schedule['status'] === 'CLOSED' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800' }}">
                                {{ $schedule['status'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $schedule['machine_number'] }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $schedule['plan_date'] }}</td>
                        <td class="px-4 py-3 text-center">
                            <span
                                class="inline-block rounded-full px-3 py-1 text-sm font-medium
                                {{ $schedule['is_gap_day_out_of_range'] ? 'bg-red-100 text-red-800' : 'bg-slate-100 text-slate-800' }}">
                                {{ $schedule['gap_day'] > 0 ? '+' : '' }}{{ $schedule['gap_day'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $schedule['actual_date'] ?? '—' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-8 text-center text-slate-500">
                            No PM schedules found for this period.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE CARDS --}}
    <div class="space-y-3 md:hidden">
        @forelse($schedules as $schedule)
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold text-slate-800">{{ $schedule['machine_number'] }}</div>
                        <span
                            class="inline-flex items-center rounded-full px-2 py-1 text-xs font-semibold mt-1
                            {{ $schedule['status'] === 'CLOSED' ? 'bg-green-100 text-green-800' : 'bg-orange-100 text-orange-800' }}">
                            {{ $schedule['status'] }}
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                    <div>
                        <div class="text-slate-400">Plan Date</div>
                        <div class="font-medium text-slate-700">{{ $schedule['plan_date'] }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">Gap Day</div>
                        <div
                            class="font-medium
                            {{ $schedule['is_gap_day_out_of_range'] ? 'text-red-600' : 'text-slate-700' }}">
                            {{ $schedule['gap_day'] > 0 ? '+' : '' }}{{ $schedule['gap_day'] }}
                        </div>
                    </div>
                    <div class="col-span-2">
                        <div class="text-slate-400">Actual PM Date</div>
                        <div class="font-medium text-slate-700">{{ $schedule['actual_date'] ?? '—' }}</div>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-slate-200 bg-white p-8 text-center">
                <p class="text-slate-500">No PM schedules found for this period.</p>
            </div>
        @endforelse
    </div>

    

@endsection
