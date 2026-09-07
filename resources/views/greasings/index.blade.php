@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-slate-800">Greasing Schedule</h1>
            <p class="text-sm text-slate-500">Manage machine group greasing schedule</p>
        </div>

        @if (auth()->user()->isAdmin() || auth()->user()->isKoordinator())
        <div class="flex flex-col gap-3 sm:flex-row">
            <form action="{{ route('greasings.import') }}" method="POST" enctype="multipart/form-data"
                class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm sm:flex-row sm:items-center">
                @csrf
                <div class="flex items-center gap-2">
                    <input type="file" id="greasingFileInput" name="file" accept=".csv" class="hidden" onchange="updateGreasingFileName(this)">
                    <label for="greasingFileInput" class="cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100">
                        Choose File
                    </label>
                    <span id="greasingFileName" class="max-w-45 truncate text-sm text-slate-500">No file chosen</span>
                </div>
                <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                    Import
                </button>
            </form>

            {{-- <a href="{{ route('greasings.create') }}" class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
                Add Schedule
            </a> --}}
        </div>
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

    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ session('error') }}
        </div>
    @endif

    @if (session('warning'))
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
            {{ session('warning') }}
        </div>
    @endif

    @if (session('greasing_import_result'))
        @php($importResult = session('greasing_import_result'))
        <div class="mb-4 flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <span>Import result:</span>
            <span class="font-semibold text-emerald-700">{{ $importResult['imported'] }} imported</span>
            <span class="font-semibold text-amber-700">{{ $importResult['duplicate'] }} duplicate</span>
            <span class="font-semibold text-rose-700">{{ $importResult['skipped'] }} skipped/invalid</span>
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search cycle or pic..."
            class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 sm:w-64">

        <select name="group_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Group</option>
            @foreach ($groups as $group)
                <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
            @endforeach
        </select>

        <select name="status" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
            <option value="">All Status</option>
            <option value="OPEN" {{ request('status') == 'OPEN' ? 'selected' : '' }}>OPEN</option>
            <option value="FINISH" {{ request('status') == 'FINISH' ? 'selected' : '' }}>FINISH</option>
            <option value="FINISH ON TIME" {{ request('status') == 'FINISH ON TIME' ? 'selected' : '' }}>FINISH ON TIME</option>
        </select>

        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700">
            Filter
        </button>

        <a href="{{ route('greasings.index') }}" class="rounded-lg bg-slate-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-600">
            Reset
        </a>
    </form>

    {{-- ============ DESKTOP: Table (md and up) ============ --}}
    <div class="hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm md:block">
        <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Order Number</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Group</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Cycle</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Plan Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Due Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">PIC</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Finding</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
                @forelse ($greasings as $greasing)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $greasing->order_number ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-slate-800">{{ $greasing->group->name ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $greasing->cycle }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $greasing->plan_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ $greasing->due_date->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            @php($area = $greasing->group?->inferredArea())
                            @if ((auth()->user()->isAdmin() || auth()->user()->isKoordinator()) && $area)
                                <select data-id="{{ $greasing->id }}"
                                    class="assign-pic w-40 rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm font-medium shadow-sm transition hover:border-blue-500 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <option value="">Assign PIC</option>
                                    @foreach ($picsByArea[$area] ?? [] as $picOption)
                                        <option value="{{ $picOption->name }}" {{ $greasing->pic == $picOption->name ? 'selected' : '' }}>
                                            👤 {{ \Illuminate\Support\Str::title(strtolower($picOption->name)) }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                {{ $greasing->pic ?? '-' }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2.5 py-1 text-xs font-semibold',
                                'bg-amber-100 text-amber-700' => $greasing->status == 'OPEN',
                                'bg-rose-100 text-rose-700' => $greasing->status == 'FINISH',
                                'bg-emerald-100 text-emerald-700' => $greasing->status == 'FINISH ON TIME',
                            ])>
                                {{ $greasing->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">
                            @if ($greasing->findings_count > 0)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">[ {{ $greasing->findings_count }} {{ \Illuminate\Support\Str::plural('Finding', $greasing->findings_count) }} ]</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-400">[ No Finding ]</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                @if (auth()->user()->isPic() && ! in_array($greasing->status, ['FINISH', 'FINISH ON TIME'], true))
                                    @if ($greasing->start_time)
                                        <span
                                            class="inline-flex flex-col items-center rounded-lg bg-slate-200 px-3 py-1 text-sm font-semibold text-slate-700"
                                            title="Greasing activity started">
                                            STARTED
                                            <span
                                                class="text-[10px] font-normal text-slate-500">{{ $greasing->start_time->format('d M Y H:i') }}</span>
                                        </span>
                                    @else
                                        <button type="button"
                                            class="greasing-start-btn rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-blue-700"
                                            data-id="{{ $greasing->id }}" data-group="{{ $greasing->group->name ?? '' }}">
                                            START
                                        </button>
                                    @endif
                                @endif
                                <a href="{{ route('greasings.execute', $greasing->id) }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-emerald-700">
                                    {{ ! auth()->user()->isAdmin() && $greasing->status !== 'OPEN' ? 'Edit' : 'Execute' }}
                                </a>
                                @if (auth()->user()->isAdmin() || auth()->user()->isKoordinator())
                                <a href="{{ route('greasings.edit', $greasing->id) }}" class="rounded-lg bg-amber-500 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-amber-600">
                                    Edit
                                </a>
                                <form method="POST" action="{{ route('greasings.destroy', $greasing->id) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-rose-700"
                                        onclick="return confirm('Delete greasing schedule?')">
                                        Delete
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">No greasing schedule found</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    {{-- ============ MOBILE: Card List (below md) ============ --}}
    <div class="space-y-3 md:hidden">
        @forelse ($greasings as $greasing)
            @php($area = $greasing->group?->inferredArea())
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
                        <div class="text-slate-400">Due Date</div>
                        <div class="font-medium text-slate-700">{{ $greasing->due_date->format('d M Y') }}</div>
                    </div>
                    <div class="col-span-2">
                        <div class="text-slate-400">PIC</div>
                        <div class="font-medium text-slate-700">
                            @if ((auth()->user()->isAdmin() || auth()->user()->isKoordinator()) && $area)
                                <select data-id="{{ $greasing->id }}"
                                    class="assign-pic mt-1 w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-sm font-medium shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200">
                                    <option value="">Assign PIC</option>
                                    @foreach ($picsByArea[$area] ?? [] as $picOption)
                                        <option value="{{ $picOption->name }}" {{ $greasing->pic == $picOption->name ? 'selected' : '' }}>
                                            👤 {{ \Illuminate\Support\Str::title(strtolower($picOption->name)) }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                {{ $greasing->pic ?? '-' }}
                            @endif
                        </div>
                    </div>
                    <div class="col-span-2">
                        <div class="text-slate-400">Finding</div>
                        <div class="mt-1">
                            @if ($greasing->findings_count > 0)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">[ {{ $greasing->findings_count }} {{ \Illuminate\Support\Str::plural('Finding', $greasing->findings_count) }} ]</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-400">[ No Finding ]</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                    @if (auth()->user()->isPic() && ! in_array($greasing->status, ['FINISH', 'FINISH ON TIME'], true))
                        @if ($greasing->start_time)
                            <span class="flex-1 rounded-lg bg-slate-200 px-3 py-2 text-center text-xs font-semibold text-slate-700">
                                STARTED · {{ $greasing->start_time->format('d M Y H:i') }}
                            </span>
                        @else
                            <button type="button"
                                class="greasing-start-btn flex-1 rounded-lg bg-blue-600 px-3 py-2 text-center text-xs font-medium text-white transition hover:bg-blue-700"
                                data-id="{{ $greasing->id }}" data-group="{{ $greasing->group->name ?? '' }}">START</button>
                        @endif
                    @endif
                    <a href="{{ route('greasings.execute', $greasing->id) }}" class="flex-1 rounded-lg bg-emerald-600 px-3 py-2 text-center text-xs font-medium text-white transition hover:bg-emerald-700">
                        {{ ! auth()->user()->isAdmin() && $greasing->status !== 'OPEN' ? 'Edit' : 'Execute' }}
                    </a>
                    @if (auth()->user()->isAdmin() || auth()->user()->isKoordinator())
                        <a href="{{ route('greasings.edit', $greasing->id) }}" class="flex-1 rounded-lg bg-amber-500 px-3 py-2 text-center text-xs font-medium text-white transition hover:bg-amber-600">
                            Edit
                        </a>
                        <form method="POST" action="{{ route('greasings.destroy', $greasing->id) }}" class="flex-1">
                            @csrf
                            @method('DELETE')
                            <button class="w-full rounded-lg bg-rose-600 px-3 py-2 text-xs font-medium text-white transition hover:bg-rose-700"
                                onclick="return confirm('Delete greasing schedule?')">
                                Delete
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-sm text-slate-500">No greasing schedule found</p>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $greasings->links() }}
    </div>

    <script>
        function updateGreasingFileName(input) {
            const fileName = input.files.length ? input.files[0].name : 'No file chosen';
            document.getElementById('greasingFileName').textContent = fileName;
        }
    </script>

    <script>
        document.querySelectorAll('.assign-pic').forEach(function (select) {
            select.addEventListener('change', function () {
                fetch(`/greasings/${this.dataset.id}/assign-pic`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        pic: this.value,
                    }),
                });
            });
        });
    </script>

    {{-- ============ START GREASING ACTIVITY MODAL ============ --}}
    <div id="greasing-start-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-semibold text-slate-800">Start Greasing Activity</h3>
            <p id="greasing-start-group" class="mt-1 text-sm text-slate-500"></p>

            <form id="greasing-start-form" method="POST" class="mt-4 space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Start Date &amp; Time</label>
                    <input type="datetime-local" name="started_at" id="greasing-start-input" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-700 focus:border-blue-500 focus:outline-none">
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" id="greasing-start-cancel"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">CANCEL</button>
                    <button type="submit"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">START</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const modal = document.getElementById('greasing-start-modal');
            if (!modal) return;

            const form = document.getElementById('greasing-start-form');
            const input = document.getElementById('greasing-start-input');
            const groupLabel = document.getElementById('greasing-start-group');
            const baseAction = "{{ url('greasings') }}";

            // Current local (WIB) date/time as the editable default.
            function nowLocal() {
                const d = new Date();
                d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                return d.toISOString().slice(0, 16);
            }

            function openModal(id, group) {
                form.action = `${baseAction}/${id}/start`;
                input.value = nowLocal();
                groupLabel.textContent = group ? `Group: ${group}` : '';
                modal.classList.remove('hidden');
                modal.classList.add('flex');
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }

            document.querySelectorAll('.greasing-start-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openModal(this.dataset.id, this.dataset.group);
                });
            });

            document.getElementById('greasing-start-cancel').addEventListener('click', closeModal);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
        })();
    </script>
@endsection
