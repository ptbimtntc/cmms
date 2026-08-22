@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">PM Schedule</h1>
            <p class="text-sm text-slate-500">Manage preventive maintenance schedules</p>
        </div>

        @if (in_array(auth()->user()->role, ['ADMIN', 'KOORDINATOR WWD', 'KOORDINATOR BUL']))
            <form action="{{ route('pm-schedules.import') }}" method="POST" enctype="multipart/form-data"
                class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm sm:flex-row sm:items-center">
                @csrf
                <div class="flex items-center gap-2">
                    <input type="file" id="fileInput" name="file" accept=".csv" class="hidden"
                        onchange="updateFileName(this)">
                    <label for="fileInput"
                        class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                        Choose File
                    </label>
                    <span id="fileName" class="max-w-[180px] truncate text-sm text-slate-500">No file chosen</span>
                </div>
                <button
                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                    Import
                </button>
            </form>
        @endif
    </div>

    @if ($errors->has('file'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first('file') }}
        </div>
    @endif

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <?php if (session('pm_schedules_import_result')): ?>
        <?php $importResult = session('pm_schedules_import_result'); ?>
        <div class="mb-4 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <span>Import result:</span>
            <span class="font-semibold text-emerald-700"><?php echo e($importResult['imported']); ?> imported</span>
            <span class="font-semibold text-amber-700"><?php echo e($importResult['duplicate']); ?> duplicate</span>
            <span class="font-semibold text-rose-700"><?php echo e($importResult['skipped']); ?> skipped/invalid</span>
        </div>
    <?php endif; ?>

    <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <input title="Search machine / type..." type="text" name="search" value="{{ request('search') }}"
            placeholder="Search..."
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-40">

        @if (auth()->user()->role == 'ADMIN')
            <select name="area" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                <option value="">All Areas</option>
                @foreach ($areas as $area)
                    <option value="{{ $area }}" {{ request('area') == $area ? 'selected' : '' }}>{{ $area }}
                    </option>
                @endforeach
            </select>
        @endif

        <select name="machine_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Machine Type</option>
            @foreach ($machineTypes as $type)
                <option value="{{ $type }}" {{ request('machine_type') == $type ? 'selected' : '' }}>
                    {{ $type }}</option>
            @endforeach
        </select>

        <select name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Status</option>
            <option value="OPEN" {{ request('status') == 'OPEN' ? 'selected' : '' }}>OPEN</option>
            <option value="IN_PROGRESS" {{ request('status') == 'IN_PROGRESS' ? 'selected' : '' }}>IN PROGRESS</option>
            <option value="FINISHED" {{ request('status') == 'FINISHED' ? 'selected' : '' }}>FINISHED</option>
            <option value="FINISHED_ON_TIME" {{ request('status') == 'FINISHED_ON_TIME' ? 'selected' : '' }}>FINISHED ON
                TIME</option>
            <option value="MISSED" {{ request('status') == 'MISSED' ? 'selected' : '' }}>MISSED</option>
        </select>

        <select name="plan_month" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Month</option>
            @foreach ($months as $m)
                <option value="{{ $m }}" {{ request('plan_month') == $m ? 'selected' : '' }}>
                    {{ $m }}</option>
            @endforeach
        </select>

        <select name="plan_year" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Year</option>
            @foreach ($years as $y)
                <option value="{{ $y }}" {{ request('plan_year') == $y ? 'selected' : '' }}>
                    {{ $y }}</option>
            @endforeach
        </select>

        <button
            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">Filter</button>
        <a href="{{ route('pm-schedules.index') }}"
            class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">Reset</a>
    </form>

    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Area
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Machine</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Type
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Plan
                            Date
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">PIC
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Due
                            Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Month
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Year
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status
                        </th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-slate-500">
                            Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @foreach ($schedules as $pm)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-600">{{ $pm->area }}</td>
                            <td class="px-4 py-3 font-medium text-slate-800">{{ $pm->machine_number }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $pm->machine_type }}</td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $pm->plan_date ? \Carbon\Carbon::parse($pm->plan_date)->format('d-m-Y') : '-' }}</td>
                            <td class="px-3 py-2">

                                @if (str_starts_with(auth()->user()->role, 'KOORDINATOR') || auth()->user()->role == 'ADMIN')
                                    <select
                                        class="assign-pic
    w-44
    rounded-lg
    border
    border-slate-300
    bg-white
    px-3
    py-2
    text-sm
    font-medium
    shadow-sm
    transition
    hover:border-blue-500
    focus:border-blue-500
    focus:ring-2
    focus:ring-blue-200"
                                        data-id="{{ $pm->id }}">

                                        <option value="">Assign PIC</option>

                                        @foreach ($picsByArea[$pm->area] ?? [] as $pic)
                                            <option value="{{ $pic->name }}"
                                                {{ $pm->pic == $pic->name ? 'selected' : '' }}>

                                                👤 {{ \Illuminate\Support\Str::title(strtolower($pic->name)) }}

                                            </option>
                                        @endforeach

                                    </select>
                                @else
                                    {{ $pm->pic ? \Illuminate\Support\Str::title(strtolower($pm->pic)) : '-' }}
                                @endif

                            </td>
                            <td class="px-4 py-3 text-slate-600">
                                {{ $pm->due_date ? \Carbon\Carbon::parse($pm->due_date)->format('d-m-Y') : '-' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $pm->plan_month }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $pm->plan_year }}</td>
                            <td class="px-4 py-3">
                                @switch($pm->status)
                                    @case('OPEN')
                                        <span
                                            class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">OPEN</span>
                                    @break

                                    @case('IN_PROGRESS')
                                        <span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">IN
                                            PROGRESS</span>
                                    @break

                                    @case('FINISHED')
                                        <span
                                            class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-700">FINISHED</span>
                                    @break

                                    @case('FINISHED_ON_TIME')
                                        <span
                                            class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">FINISHED
                                            ON TIME</span>
                                    @break

                                    @case('MISSED')
                                        <span
                                            class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">MISSED</span>
                                    @break

                                    @default
                                        <span
                                            class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $pm->status }}</span>
                                @endswitch
                            </td>
                            <td class="px-4 py-3 text-center">
                                @php
                                    $role = auth()->user()->role;
                                @endphp

                                {{-- ADMIN --}}
                                @if ($role == 'ADMIN')
                                    <a href="{{ route('pm-schedules.edit', $pm->id) }}"
                                        class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs px-3 py-1 rounded">

                                        Edit

                                    </a>

                                    {{-- KOORDINATOR --}}
                                @elseif(str_starts_with($role, 'KOORDINATOR'))
                                    <a href="{{ route('pm-schedules.edit', $pm->id) }}"
                                        class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1 rounded">

                                        Edit

                                    </a>

                                    {{-- PIC --}}
                                @elseif(str_starts_with($role, 'PIC'))
                                    @if (in_array($pm->status, ['OPEN', 'MISSED', 'IN_PROGRESS']))
                                        <a href="{{ route('pm-schedules.edit', $pm->id) }}"
                                            class="bg-green-600 hover:bg-green-700 text-white text-xs px-3 py-1 rounded">

                                            Fill PM

                                        </a>
                                    @else
                                        <a href="{{ route('pm-schedules.edit', $pm->id) }}"
                                            class="bg-yellow-500 hover:bg-yellow-600 text-white text-xs px-3 py-1 rounded">

                                            View

                                        </a>
                                    @endif
                                @endif

                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ MOBILE: Card List (di bawah md) ============ --}}
    <div class="space-y-3 md:hidden">
        @foreach ($schedules as $pm)
            @php $role = auth()->user()->role; @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">

                {{-- Header card: Machine + Status --}}
                <div class="mb-3 flex items-start justify-between gap-2">
                    <div>
                        <div class="text-sm font-semibold text-slate-800">{{ $pm->machine_number }}</div>
                        <div class="text-xs text-slate-500">{{ $pm->machine_type }} • {{ $pm->area }}</div>
                    </div>
                    @switch($pm->status)
                        @case('OPEN')
                            <span
                                class="shrink-0 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">OPEN</span>
                        @break

                        @case('IN_PROGRESS')
                            <span class="shrink-0 rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">IN
                                PROGRESS</span>
                        @break

                        @case('FINISHED')
                            <span
                                class="shrink-0 rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-700">FINISHED</span>
                        @break

                        @case('FINISHED_ON_TIME')
                            <span
                                class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">ON
                                TIME</span>
                        @break

                        @case('MISSED')
                            <span
                                class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700">MISSED</span>
                        @break

                        @default
                            <span
                                class="shrink-0 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $pm->status }}</span>
                    @endswitch
                </div>

                {{-- Detail grid --}}
                <div class="grid grid-cols-2 gap-y-2 border-t border-slate-100 pt-3 text-xs">
                    <div>
                        <div class="text-slate-400">Plan Date</div>
                        <div class="font-medium text-slate-700">
                            {{ $pm->plan_date ? \Carbon\Carbon::parse($pm->plan_date)->format('d-m-Y') : '-' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-400">Due Date</div>
                        <div class="font-medium text-slate-700">
                            {{ $pm->due_date ? \Carbon\Carbon::parse($pm->due_date)->format('d-m-Y') : '-' }}
                        </div>
                    </div>
                    <div>
                        <div class="text-slate-400">Month / Year</div>
                        <div class="font-medium text-slate-700">{{ $pm->plan_month }} {{ $pm->plan_year }}</div>
                    </div>
                    <div>
                        <div class="text-slate-400">PIC</div>
                        <div class="font-medium text-slate-700">
                            @if (!(str_starts_with($role, 'KOORDINATOR') || $role == 'ADMIN'))
                                {{ $pm->pic ? \Illuminate\Support\Str::title(strtolower($pm->pic)) : '-' }}
                            @else
                                {{ $pm->pic ? \Illuminate\Support\Str::title(strtolower($pm->pic)) : 'Belum diassign' }}
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Assign PIC (khusus koordinator/admin) --}}
                @if (str_starts_with($role, 'KOORDINATOR') || $role == 'ADMIN')
                    <div class="mt-3 border-t border-slate-100 pt-3">
                        <select
                            class="assign-pic w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-200"
                            data-id="{{ $pm->id }}">
                            <option value="">Assign PIC</option>
                            @foreach ($picsByArea[$pm->area] ?? [] as $pic)
                                <option value="{{ $pic->name }}" {{ $pm->pic == $pic->name ? 'selected' : '' }}>
                                    👤 {{ \Illuminate\Support\Str::title(strtolower($pic->name)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                {{-- Action --}}
                <div class="mt-3 border-t border-slate-100 pt-3 text-right">
                    @if ($role == 'ADMIN')
                        <a href="{{ route('pm-schedules.edit', $pm->id) }}"
                            class="rounded-lg bg-yellow-500 px-4 py-2 text-xs font-medium text-white hover:bg-yellow-600">Edit</a>
                    @elseif(str_starts_with($role, 'KOORDINATOR'))
                        <a href="{{ route('pm-schedules.edit', $pm->id) }}"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700">Edit</a>
                    @elseif(str_starts_with($role, 'PIC'))
                        @if (in_array($pm->status, ['OPEN', 'MISSED', 'IN_PROGRESS']))
                            <a href="{{ route('pm-schedules.edit', $pm->id) }}"
                                class="rounded-lg bg-green-600 px-4 py-2 text-xs font-medium text-white hover:bg-green-700">Fill
                                PM</a>
                        @else
                            <a href="{{ route('pm-schedules.edit', $pm->id) }}"
                                class="rounded-lg bg-yellow-500 px-4 py-2 text-xs font-medium text-white hover:bg-yellow-600">View</a>
                        @endif
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $schedules->links() }}
    </div>

    <script>
        function validateFile(input) {
            const file = input.files[0];

            if (!file) return;

            const allowed = ['csv'];
            const ext = file.name.split('.').pop().toLowerCase();

            if (!allowed.includes(ext)) {
                alert("File harus .CSV saja!");
                input.value = ""; // reset file
            }
        }
    </script>

    <script>
        function updateFileName(input) {
            const fileName = input.files[0] ? input.files[0].name : 'No file chosen';
            document.getElementById('fileName').textContent = fileName;
        }
    </script>
    <script>
        document.querySelectorAll('.assign-pic').forEach(function(select) {

            select.addEventListener('change', function() {

                fetch(`/pm-schedules/${this.dataset.id}/assign-pic`, {

                    method: 'POST',

                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document
                            .querySelector('meta[name="csrf-token"]')
                            .content
                    },

                    body: JSON.stringify({

                        pic: this.value

                    })

                });

            });

        });
    </script>
@endsection
