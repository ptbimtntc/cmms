@extends('layouts.app')

@php
    $fullWidth = true;
    $canManage = auth()->user()->isAdmin() || auth()->user()->isKoordinator();
    $hasFilters = collect(request()->except('page'))->filter(fn ($v) => filled($v))->isNotEmpty();
    // Kept as "[ n Findings ]" / "[ No Finding ]": the greasing report uses the same wording and tests read it.
    $findingText = fn ($n) => $n > 0 ? '[ ' . $n . ' ' . \Illuminate\Support\Str::plural('Finding', $n) . ' ]' : '[ No Finding ]';
@endphp

@section('content')
    <div class="space-y-5">
        <x-ui.page-header title="Greasing Schedule" description="Greasing per machine group: start it, execute it, or assign a PIC.">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button variant="outline" id="greasing-import-open">Import CSV</x-ui.button>
                    <x-ui.button href="{{ route('greasings.create') }}">Add Schedule</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if ($errors->has('file'))
            <x-ui.alert tone="danger">{{ $errors->first('file') }}</x-ui.alert>
        @endif
        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert tone="danger">{{ session('error') }}</x-ui.alert>
        @endif
        @if (session('warning'))
            <x-ui.alert tone="warning">{{ session('warning') }}</x-ui.alert>
        @endif
        @if (session('greasing_import_result'))
            @php($importResult = session('greasing_import_result'))
            <x-ui.alert tone="info" title="Import result">
                <span class="font-semibold">{{ $importResult['imported'] }}</span> imported,
                <span class="font-semibold">{{ $importResult['duplicate'] }}</span> duplicate,
                <span class="font-semibold">{{ $importResult['skipped'] }}</span> skipped or invalid.
            </x-ui.alert>
        @endif

        {{-- Filters: plain GET, same parameter names. The multi-select sits on the last row so its
             open list never covers another control. --}}
        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-72">
                <label for="greasing-filter-search" class="text-xs font-medium text-text-muted">Search</label>
                <input id="greasing-filter-search" type="text" name="search" value="{{ request('search') }}"
                    placeholder="Cycle, PIC, or order number" class="input lg:min-h-9">
            </div>

            <div class="grid gap-1.5 sm:w-56">
                <label for="greasing-filter-group" class="text-xs font-medium text-text-muted">Group</label>
                <select id="greasing-filter-group" name="group_id" class="select lg:min-h-9">
                    <option value="">All Group</option>
                    @foreach ($groups as $group)
                        <option value="{{ $group->id }}" {{ request('group_id') == $group->id ? 'selected' : '' }}>{{ $group->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid gap-1.5">
                <span class="text-xs font-medium text-text-muted">Status</span>
                <x-checkbox-dropdown name="status" label="Status" :options="[
                    'OPEN' => 'OPEN',
                    'FINISH' => 'FINISH',
                    'FINISH ON TIME' => 'FINISH ON TIME',
                ]" :selected="(array) request('status', [])" />
            </div>

            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('greasings.index') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @if ($greasings->isEmpty())
            <x-ui.empty-state
                :title="$hasFilters ? 'No greasing schedule matches these filters.' : 'No greasing schedule found'"
                :description="$hasFilters
                    ? 'Widen the filters, or reset them to see every schedule you can access.'
                    : ($canManage ? 'Add a schedule or import a CSV to fill this list.' : 'Schedules assigned to you will appear here.')">
                @if ($hasFilters)
                    <x-ui.button variant="outline" href="{{ route('greasings.index') }}">Reset filters</x-ui.button>
                @elseif ($canManage)
                    <x-ui.button href="{{ route('greasings.create') }}">Add Schedule</x-ui.button>
                @endif
            </x-ui.empty-state>
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $greasings->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Group</th>
                            <th scope="col">Plan</th>
                            <th scope="col">Due</th>
                            <th scope="col">PIC</th>
                            <th scope="col">Status</th>
                            <th scope="col">Finding</th>
                            {{-- Actions stay pinned on the right when the table scrolls sideways on narrower screens. --}}
                            <th scope="col" class="sticky right-0 bg-surface-sunken"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody data-infinite-list="greasings">
                        @foreach ($greasings as $greasing)
                            @php($area = $greasing->group?->area?->name)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <div class="font-semibold text-text">{{ $greasing->group->name ?? '-' }}</div>
                                    <div class="text-xs text-text-muted">{{ $greasing->order_number ?? '-' }} · {{ $greasing->cycle }}</div>
                                </td>
                                <td class="whitespace-nowrap">{{ $greasing->plan_date->format('d M Y') }}</td>
                                <td class="whitespace-nowrap">{{ $greasing->due_date->format('d M Y') }}</td>
                                <td>
                                    @if ($canManage && $area)
                                        @include('partials.assign-pic-select', ['id' => 'greasing-pic-t-' . $greasing->id, 'recordId' => $greasing->id, 'label' => $greasing->group->name ?? 'this greasing', 'pics' => $picsByArea[$area] ?? [], 'current' => $greasing->pic])
                                    @else
                                        {{ $greasing->pic ?? '-' }}
                                    @endif
                                </td>
                                <td>@include('greasings.partials.status-badge', ['status' => $greasing->status])</td>
                                <td class="whitespace-nowrap">
                                    <span @class(['text-sm', 'font-semibold text-warning-text' => $greasing->findings_count > 0, 'text-text-muted' => $greasing->findings_count === 0])>{{ $findingText($greasing->findings_count) }}</span>
                                </td>
                                <td class="sticky right-0 bg-surface shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)] whitespace-nowrap [&>div]:flex-nowrap">@include('greasings.partials.row-actions')</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="greasings">
            @foreach ($greasings as $greasing)
                @php($area = $greasing->group?->area?->name)
                <article class="card min-w-0 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-semibold text-text">{{ $greasing->group->name ?? '-' }}</h2>
                            <p class="truncate text-sm text-text-muted">{{ $greasing->cycle }} · {{ $greasing->order_number ?? '-' }}</p>
                        </div>
                        @include('greasings.partials.status-badge', ['status' => $greasing->status])
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                        <div>
                            <dt class="text-xs text-text-muted">Plan Date</dt>
                            <dd class="font-medium text-text">{{ $greasing->plan_date->format('d M Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-muted">Due Date</dt>
                            <dd class="font-medium text-text">{{ $greasing->due_date->format('d M Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-text-muted">Finding</dt>
                            <dd @class(['font-medium', 'text-warning-text' => $greasing->findings_count > 0, 'text-text-muted' => $greasing->findings_count === 0])>{{ $findingText($greasing->findings_count) }}</dd>
                        </div>
                        @unless ($canManage && $area)
                            <div>
                                <dt class="text-xs text-text-muted">PIC</dt>
                                <dd class="font-medium text-text">{{ $greasing->pic ?? '-' }}</dd>
                            </div>
                        @endunless
                    </dl>

                    @if ($canManage && $area)
                        <div class="mt-3 border-t border-border pt-3">
                            <p class="mb-1.5 text-xs text-text-muted">PIC</p>
                            @include('partials.assign-pic-select', ['id' => 'greasing-pic-c-' . $greasing->id, 'recordId' => $greasing->id, 'label' => $greasing->group->name ?? 'this greasing', 'pics' => $picsByArea[$area] ?? [], 'current' => $greasing->pic])
                        </div>
                    @endif

                    <div class="mt-3 border-t border-border pt-3">
                        @include('greasings.partials.row-actions')
                    </div>
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$greasings" key="greasings" />
    </div>

    @if ($canManage)
        <x-ui.modal id="greasing-import-modal" title="Import greasing schedules"
            description="Upload a .csv file in the Greasing import template format.">
            <form action="{{ route('greasings.import') }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <x-ui.field label="CSV file" for="greasingFileInput" error="file" required>
                    <input type="file" id="greasingFileInput" name="file" accept=".csv" required
                        class="block w-full text-sm text-text file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-control file:border file:border-border-strong file:bg-surface file:px-3 file:text-sm file:font-medium file:text-text hover:file:bg-surface-muted">
                </x-ui.field>
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" id="greasing-import-cancel">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="success">Import</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <script>
            (function () {
                const modal = document.getElementById('greasing-import-modal');
                const show = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
                const hide = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
                document.getElementById('greasing-import-open').addEventListener('click', show);
                document.getElementById('greasing-import-cancel').addEventListener('click', hide);
                modal.addEventListener('click', (e) => { if (e.target === modal) hide(); });
                @if ($errors->has('file')) show(); @endif
            })();
        </script>
    @endif

    @include('partials.assign-pic-script', ['baseUrl' => '/greasings'])

    <x-ui.modal id="greasing-start-modal" title="Start greasing activity" size="sm">
        <p id="greasing-start-group" class="mt-1 text-sm text-text-muted"></p>

        <form id="greasing-start-form" method="POST" class="mt-5 space-y-4">
            @csrf
            <x-ui.field label="Start date & time" for="greasing-start-input" required>
                <input type="datetime-local" name="started_at" id="greasing-start-input" required class="input">
            </x-ui.field>
            <div class="flex justify-end gap-2 pt-2">
                <x-ui.button variant="ghost" id="greasing-start-cancel">Cancel</x-ui.button>
                <x-ui.button type="submit">START</x-ui.button>
            </div>
        </form>
    </x-ui.modal>

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

            // delegated: rows are appended later by infinite scroll
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.greasing-start-btn');
                if (btn) openModal(btn.dataset.id, btn.dataset.group);
            });

            document.getElementById('greasing-start-cancel').addEventListener('click', closeModal);
            modal.addEventListener('click', function (e) {
                if (e.target === modal) closeModal();
            });
        })();
    </script>

    @include('partials.activity-conflict-modal')
@endsection
