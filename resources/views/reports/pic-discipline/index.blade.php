@extends('layouts.app')

@section('title', 'PIC Discipline Report')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $cell = fn ($data) => $data ?? ['achieved' => 0, 'breach' => 0, 'percentage' => 0.0];
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="PIC Discipline Report" description="Kedisiplinan PIC melengkapi administrasi PM — Pasang Tag, Fill PM, Checklist Kertas, WO Process, Create WO Rep, Closing WO SAP — per quarter." />

        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            @if ($isAdmin)
                <div class="grid gap-1.5 sm:w-36">
                    <label for="rpd-area" class="{{ $label }}">Area</label>
                    <select id="rpd-area" name="area" class="select lg:min-h-9">
                        <option value="">Semua Area</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a }}" @selected($selectedArea === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="grid gap-1.5 sm:w-32">
                <label for="rpd-year" class="{{ $label }}">Tahun</label>
                <select id="rpd-year" name="year" class="select lg:min-h-9">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($selectedYear == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-32">
                <label for="rpd-quarter" class="{{ $label }}">Quarter</label>
                <select id="rpd-quarter" name="quarter" class="select lg:min-h-9">
                    @foreach ($quarters as $q)
                        <option value="{{ $q }}" @selected($selectedQuarter == $q)>Q{{ $q }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="rpd-pic" class="{{ $label }}">PIC</label>
                <select id="rpd-pic" name="pic" class="select lg:min-h-9">
                    <option value="">Semua PIC</option>
                    @foreach ($picOptions as $p)
                        <option value="{{ $p }}" @selected($selectedPic === $p)>{{ \Illuminate\Support\Str::title(strtolower($p)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('reports.pic-discipline') }}" class="lg:min-h-9">Reset</x-ui.button>
                <x-ui.button variant="outline" href="{{ route('reports.pic-discipline.export', request()->query()) }}" class="lg:min-h-9">Export</x-ui.button>
            </div>
        </form>

        @if ($pics->isEmpty())
            <x-ui.empty-state title="Belum ada data." description="Belum ada PM Schedule yang selesai pada periode Q{{ $selectedQuarter }} {{ $selectedYear }} ini." />
        @else
            @foreach ($pics as $pic)
                @php
                    $average = $cell($overall[$pic->id] ?? null);
                @endphp
                <div class="card overflow-hidden">
                    <div class="flex items-center justify-between gap-3 border-b border-border p-4">
                        <h2 class="font-semibold text-text">{{ \Illuminate\Support\Str::title(strtolower($pic->name)) }}</h2>
                        <div class="flex items-center gap-2 text-sm">
                            <span class="{{ $label }}">Average</span>
                            <x-ui.badge tone="{{ $average['percentage'] >= 90 ? 'success' : ($average['percentage'] >= 75 ? 'warning' : 'danger') }}">
                                {{ $average['percentage'] }}%
                            </x-ui.badge>
                        </div>
                    </div>
                    <div class="relative overflow-x-auto">
                        <table class="data-table min-w-full">
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col">Achieved (A)</th>
                                    <th scope="col">Breach (B)</th>
                                    <th scope="col">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($itemTypes as $itemType)
                                    @php $row = $cell($perItem[$pic->id][$itemType->id] ?? null); @endphp
                                    <tr>
                                        <td class="font-medium text-text">{{ $itemType->name }}</td>
                                        <td>{{ $row['achieved'] }}</td>
                                        <td>{{ $row['breach'] }}</td>
                                        <td>
                                            <x-ui.badge tone="{{ $row['percentage'] >= 90 ? 'success' : ($row['percentage'] >= 75 ? 'warning' : 'danger') }}">
                                                {{ $row['percentage'] }}%
                                            </x-ui.badge>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
@endsection
