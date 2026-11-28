@extends('layouts.app')

@section('title', 'Input Kedisiplinan PIC')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $fmt = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d-m-Y') : '-';
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Input Kedisiplinan PIC" description="Catat tanggal penyelesaian tiap item administrasi PM per PIC. Fill PM terisi otomatis dari sistem." />

        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            @if ($isAdmin)
                <div class="grid gap-1.5 sm:w-36">
                    <label for="pde-area" class="{{ $label }}">Area</label>
                    <select id="pde-area" name="area" class="select lg:min-h-9">
                        <option value="">Semua Area</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a }}" @selected($selectedArea === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="grid gap-1.5 sm:w-32">
                <label for="pde-year" class="{{ $label }}">Tahun</label>
                <select id="pde-year" name="year" class="select lg:min-h-9">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($selectedYear == $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-32">
                <label for="pde-quarter" class="{{ $label }}">Quarter</label>
                <select id="pde-quarter" name="quarter" class="select lg:min-h-9">
                    <option value="">Semua Quarter</option>
                    @for ($q = 1; $q <= 4; $q++)
                        <option value="{{ $q }}" @selected($selectedQuarter == $q)>Q{{ $q }}</option>
                    @endfor
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="pde-pic" class="{{ $label }}">PIC</label>
                <select id="pde-pic" name="pic" class="select lg:min-h-9">
                    <option value="">Semua PIC</option>
                    @foreach ($pics as $p)
                        <option value="{{ $p }}" @selected($selectedPic === $p)>{{ \Illuminate\Support\Str::title(strtolower($p)) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('pic-discipline.entries.index') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @if (! $canEdit)
            <x-ui.alert tone="info">Mode lihat saja (read-only) — hanya ADMIN dan KOORDINATOR yang bisa mengubah data ini.</x-ui.alert>
        @endif

        @if ($rows->isEmpty())
            <x-ui.empty-state title="Belum ada data." description="Belum ada PM Schedule yang selesai (actual date terisi) pada periode ini." />
        @else
            <div class="card overflow-hidden">
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Machine Number</th>
                                <th scope="col">Area</th>
                                <th scope="col">PIC</th>
                                <th scope="col">Action Date</th>
                                @foreach ($itemTypes as $itemType)
                                    <th scope="col">{{ $itemType->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="whitespace-nowrap font-semibold text-text">{{ $row['pm_schedule']->machine_number }}</td>
                                    <td>{{ $row['pm_schedule']->area }}</td>
                                    <td>{{ $row['pic'] ? \Illuminate\Support\Str::title(strtolower($row['pic']->name)) : '-' }}</td>
                                    <td class="whitespace-nowrap">{{ $fmt($row['pm_schedule']->actual_date) }}</td>
                                    @foreach ($itemTypes as $itemType)
                                        <td>
                                            @if ($row['entries']->has($itemType->id))
                                                @include('pic-discipline.entries.partials.entry-cell', ['entry' => $row['entries'][$itemType->id], 'canEdit' => $canEdit])
                                            @else
                                                <span class="text-xs text-text-muted">-</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

    @if ($canEdit)
        @include('pic-discipline.entries.partials.entry-script', ['baseUrl' => '/pic-discipline/entries'])
    @endif
@endsection
