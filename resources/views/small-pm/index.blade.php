@extends('layouts.app')

@section('title', 'Small PM')

@php
    $fullWidth = true;
    $isFindingsTab = $tab === \App\Http\Controllers\SmallPmController::FINDINGS_TAB;
    $hasFilters = request()->hasAny(['search', 'row_id', 'status']);
@endphp

@section('content')
    <div class="space-y-5">
        <x-ui.page-header title="Small PM" description="PM schedules per machine row. Rows are set up in Master Data, Small PM Rows.">
            @if ($canManage && $available && ! $isFindingsTab)
                <x-slot:actions>
                    <x-ui.button variant="outline" id="small-pm-import-open">Import</x-ui.button>
                    <x-ui.button href="{{ route('small-pm.create', ['tab' => $tab]) }}">Add Schedule</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        {{-- Job type tabs: each is its own schedule list over the same rows, plus the findings log. --}}
        {{-- On a phone the tabs scroll sideways; bring the current one into view so it is never off-screen. --}}
        <nav class="-mt-1 flex overflow-x-auto border-b border-border" aria-label="Jenis Small PM"
            x-data x-init="$el.querySelector('[aria-current]')?.scrollIntoView({ inline: 'center', block: 'nearest' })">
            @foreach ($tabs as $key => $t)
                @php($active = $tab === $key)
                <a href="{{ route('small-pm.index', ['tab' => $key]) }}" @if ($active) aria-current="page" @endif
                    @class([
                        '-mb-px inline-flex min-h-11 flex-1 items-center justify-center gap-2 whitespace-nowrap border-b-2 px-3 text-sm font-semibold transition-colors sm:flex-none',
                        'border-primary text-primary-hover' => $active,
                        'border-transparent text-text-muted hover:border-border-strong hover:text-text' => ! $active,
                    ])>
                    {{ $t['label'] }}
                    @unless ($t['available'])
                        <span class="text-xs font-medium text-text-muted">(not available yet)</span>
                    @endunless
                    @if ($t['count'])
                        <x-ui.badge tone="warning">{{ $t['count'] }} open</x-ui.badge>
                    @endif
                </a>
            @endforeach
        </nav>

        @foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning'] as $flash => $tone)
            @if (session($flash))
                <x-ui.alert :tone="$tone">{{ session($flash) }}</x-ui.alert>
            @endif
        @endforeach
        @if ($errors->any())
            <x-ui.alert tone="danger">{{ $errors->first() }}</x-ui.alert>
        @endif
        @if (session('small_pm_import_result'))
            @php($importResult = session('small_pm_import_result'))
            <x-ui.alert tone="info" title="Import result">
                <span class="font-semibold">{{ $importResult['imported'] }}</span> masuk,
                <span class="font-semibold">{{ $importResult['duplicate'] }}</span> duplikat,
                <span class="font-semibold">{{ $importResult['skipped'] }}</span> skipped or invalid.
            </x-ui.alert>
        @endif

        @if ($isFindingsTab)
            @include('small-pm._findings-tab')
        @elseif (! $available)
            <x-ui.empty-state :title="$tabs[$tab]['label'] . ' is not available yet.'"
                description="Its schedule is set up in this tab once Cleaning Kisi-Kisi Motor is running." />
        @else
            @if ($canManage && $unscheduledCount > 0)
                {{-- Rows with no OPEN schedule in this tab: the one thing to act on before the list. --}}
                <form method="POST" action="{{ route('small-pm.generate') }}" class="rounded-card border border-warning/40 bg-warning-light p-4 sm:p-5"
                    x-data="{ busy: false }" @submit="busy = true">
                    @csrf
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <p class="font-semibold text-text">{{ $unscheduledCount }} rows have no OPEN schedule in this tab.</p>
                    <p class="mt-1 text-sm text-warning-text">
                        Generate schedules {{ $rowsPerWeek }} rows a week in row order ({{ (int) ceil($unscheduledCount / $rowsPerWeek) }} weeks).
                        Plan date diisi hari Senin.
                    </p>
                    <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div class="grid gap-1.5">
                            <label for="start-week" class="text-xs font-medium text-text">Start week</label>
                            <input id="start-week" type="date" name="start_week" required value="{{ old('start_week', $thisMonday->toDateString()) }}" class="input sm:w-44">
                        </div>
                        <div class="grid gap-1.5">
                            <label for="gen-cycle" class="text-xs font-medium text-text">Cycle</label>
                            <input id="gen-cycle" type="text" name="cycle" required value="{{ old('cycle', $defaultCycle) }}" pattern="\d+[Ww]" title="Angka diikuti W, misalnya 26W" class="input sm:w-24">
                        </div>
                        <x-ui.button type="submit" x-bind:disabled="busy">Generate {{ $unscheduledCount }} rows</x-ui.button>
                    </div>
                </form>
            @endif

            {{-- Filters: plain GET; the multi-select sits last so its open list never covers a control. --}}
            <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="grid gap-1.5 sm:w-72">
                    <label for="spm-filter-search" class="text-xs font-medium text-text-muted">Search</label>
                    <input id="spm-filter-search" type="text" name="search" value="{{ request('search') }}" placeholder="Cycle, PIC, or order number" class="input lg:min-h-9">
                </div>
                <div class="grid gap-1.5 sm:w-48">
                    <label for="spm-filter-row" class="text-xs font-medium text-text-muted">Row</label>
                    <select id="spm-filter-row" name="row_id" class="select lg:min-h-9">
                        <option value="">All rows</option>
                        @foreach ($rows as $r)
                            <option value="{{ $r->id }}" @selected(request('row_id') == $r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <span class="text-xs font-medium text-text-muted">Status</span>
                    <x-checkbox-dropdown name="status" label="Status"
                        :options="['OPEN' => 'OPEN', 'FINISH' => 'FINISH', 'FINISH ON TIME' => 'FINISH ON TIME']"
                        :selected="(array) request('status', [])" />
                </div>
                <div class="flex gap-2 sm:ml-auto">
                    <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                    <x-ui.button variant="ghost" href="{{ route('small-pm.index', ['tab' => $tab]) }}" class="lg:min-h-9">Reset</x-ui.button>
                </div>
            </form>

            @if ($schedules->isEmpty())
                <x-ui.empty-state
                    :title="$hasFilters ? 'No schedule matches these filters.' : 'No Small PM schedule yet'"
                    :description="$hasFilters ? 'Loosen the filters, or reset them to see every schedule.' : ($canManage ? 'Generate schedules for the rows above, add them one at a time, or import a file.' : 'Schedules for rows in your area show up here.')">
                    @if ($hasFilters)
                        <x-ui.button variant="outline" href="{{ route('small-pm.index', ['tab' => $tab]) }}">Reset filter</x-ui.button>
                    @endif
                </x-ui.empty-state>
            @endif

            {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
            <div @class(['card hidden overflow-hidden', 'md:block' => $schedules->isNotEmpty()])>
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Row</th>
                                <th scope="col">Plan</th>
                                <th scope="col">Due</th>
                                <th scope="col">PIC</th>
                                <th scope="col">Status</th>
                                @if ($hasFindings)
                                    <th scope="col">Blower Mati</th>
                                @endif
                                {{-- Actions stay pinned on the right when the table scrolls sideways. --}}
                                <th scope="col" class="sticky right-0 bg-surface-sunken"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody data-infinite-list="small-pm">
                            @foreach ($schedules as $s)
                                <tr>
                                    <td class="whitespace-nowrap">
                                        <div class="font-semibold text-text">{{ $s->row->name ?? '-' }}</div>
                                        <div class="text-xs text-text-muted">{{ $s->order_number ?? '-' }} · {{ $s->cycle }}</div>
                                        @if ($s->row)
                                            <a href="{{ route('small-pm-rows.show', $s->row) }}" class="text-xs font-medium text-primary underline-offset-2 hover:underline">Lihat mesin</a>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap">{{ $s->plan_date->format('d M Y') }}</td>
                                    <td class="whitespace-nowrap">{{ $s->due_date->format('d M Y') }}</td>
                                    <td>@include('small-pm._pic', ['s' => $s, 'suffix' => 'd'])</td>
                                    <td>@include('small-pm._status', ['status' => $s->status])</td>
                                    @if ($hasFindings)
                                        <td>@include('small-pm._findings-badge', ['s' => $s])</td>
                                    @endif
                                    <td class="sticky right-0 whitespace-nowrap bg-surface shadow-[-10px_0_10px_-10px_rgb(15_23_42/0.25)] [&>div]:flex-nowrap">
                                        @include('small-pm._actions', ['s' => $s, 'mobile' => false])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Phone cards (below md). Second list with the same key. --}}
            <div class="space-y-3 md:hidden" data-infinite-list="small-pm">
                @foreach ($schedules as $s)
                    <article class="card min-w-0 p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-semibold text-text">{{ $s->row->name ?? '-' }}</h2>
                                <p class="truncate text-sm text-text-muted">{{ $s->cycle }} · {{ $s->order_number ?? '-' }}</p>
                            </div>
                            @include('small-pm._status', ['status' => $s->status])
                        </div>
                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 border-t border-border pt-3 text-sm">
                            <div>
                                <dt class="text-xs text-text-muted">Plan Date</dt>
                                <dd class="font-medium text-text">{{ $s->plan_date->format('d M Y') }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-text-muted">Due Date</dt>
                                <dd class="font-medium text-text">{{ $s->due_date->format('d M Y') }}</dd>
                            </div>
                            <div class="col-span-2">
                                <dt class="text-xs text-text-muted">PIC</dt>
                                <dd class="mt-1 font-medium text-text">@include('small-pm._pic', ['s' => $s, 'suffix' => 'm'])</dd>
                            </div>
                            @if ($hasFindings)
                                <div class="col-span-2">
                                    <dt class="text-xs text-text-muted">Dead filter blower</dt>
                                    <dd class="mt-1">@include('small-pm._findings-badge', ['s' => $s])</dd>
                                </div>
                            @endif
                        </dl>
                        @if ($s->row)
                            <a href="{{ route('small-pm-rows.show', $s->row) }}" class="mt-1 inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-2 hover:underline">Lihat mesin di row ini</a>
                        @endif
                        <div class="mt-2 border-t border-border pt-3">@include('small-pm._actions', ['s' => $s, 'mobile' => true])</div>
                    </article>
                @endforeach
            </div>

            <x-infinite-scroll :paginator="$schedules" key="small-pm" />

            <x-ui.modal id="small-pm-start-modal" title="Start Small PM" size="sm">
                <p id="small-pm-start-row" class="mt-1 text-sm text-text-muted"></p>
                <form id="small-pm-start-form" method="POST" class="mt-5 space-y-4">
                    @csrf
                    <x-ui.field label="Start date and time" for="small-pm-start-input" required>
                        <input type="datetime-local" name="started_at" id="small-pm-start-input" required class="input">
                    </x-ui.field>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button variant="ghost" id="small-pm-start-cancel">Cancel</x-ui.button>
                        <x-ui.button type="submit">START</x-ui.button>
                    </div>
                </form>
            </x-ui.modal>

            <script>
                (function () {
                    const modal = document.getElementById('small-pm-start-modal');
                    const form = document.getElementById('small-pm-start-form');
                    const input = document.getElementById('small-pm-start-input');
                    const rowLabel = document.getElementById('small-pm-start-row');

                    function nowLocal() {
                        const d = new Date();
                        d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                        return d.toISOString().slice(0, 16);
                    }

                    function closeModal() {
                        modal.classList.add('hidden');
                        modal.classList.remove('flex');
                    }

                    // delegated: rows are appended later by infinite scroll
                    document.addEventListener('click', function (e) {
                        const btn = e.target.closest('.small-pm-start-btn');
                        if (!btn) return;
                        form.action = btn.dataset.url;
                        input.value = nowLocal();
                        rowLabel.textContent = btn.dataset.row ? 'Row: ' + btn.dataset.row : '';
                        modal.classList.remove('hidden');
                        modal.classList.add('flex');
                    });

                    document.getElementById('small-pm-start-cancel').addEventListener('click', closeModal);
                    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

                    // PIC: saved on change; the line under the dropdown says how it went.
                    document.addEventListener('change', function (e) {
                        const select = e.target.closest('.assign-small-pm-pic');
                        if (!select) return;

                        const id = select.dataset.id;
                        const statuses = document.querySelectorAll(`.assign-small-pm-pic-status[data-for="${id}"]`);
                        const say = (text, tone) => statuses.forEach((s) => {
                            s.textContent = text;
                            s.classList.toggle('text-danger', tone === 'error');
                            s.classList.toggle('text-success', tone === 'ok');
                        });
                        document.querySelectorAll(`.assign-small-pm-pic[data-id="${id}"]`).forEach((other) => {
                            if (other !== select) other.value = select.value;
                        });

                        select.disabled = true;
                        say('Menyimpan...');
                        fetch(select.dataset.url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ pic_user_id: select.value }),
                        }).then(function (res) {
                            if (!res.ok) throw new Error();
                            say('Tersimpan', 'ok');
                        }).catch(function () {
                            say('Could not save the PIC. Reload the page and try again.', 'error');
                        }).finally(function () {
                            select.disabled = false;
                        });
                    });
                })();
            </script>
        @endif
    </div>

    @if ($canManage && $available && ! $isFindingsTab)
        <x-ui.modal id="small-pm-import-modal" title="Import Small PM schedules" description="Upload a .csv, .xlsx, or .xls file matching the Small PM import template for this tab.">
            <form action="{{ route('small-pm.import') }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="tab" value="{{ $tab }}">
                <x-ui.field label="File" for="smallPmFileInput" required>
                    <input type="file" id="smallPmFileInput" name="file" accept=".csv,.xlsx,.xls" required
                        class="block w-full text-sm text-text file:mr-3 file:min-h-11 file:cursor-pointer file:rounded-control file:border file:border-border-strong file:bg-surface file:px-3 file:text-sm file:font-medium file:text-text hover:file:bg-surface-muted">
                </x-ui.field>
                <div class="flex justify-end gap-2 pt-2">
                    <x-ui.button variant="ghost" id="small-pm-import-cancel">Cancel</x-ui.button>
                    <x-ui.button type="submit" variant="success">Import</x-ui.button>
                </div>
            </form>
        </x-ui.modal>

        <script>
            (function () {
                const modal = document.getElementById('small-pm-import-modal');
                const show = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
                const hide = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
                document.getElementById('small-pm-import-open').addEventListener('click', show);
                document.getElementById('small-pm-import-cancel').addEventListener('click', hide);
                modal.addEventListener('click', (e) => { if (e.target === modal) hide(); });
            })();
        </script>
    @endif

    @include('partials.activity-conflict-modal')
@endsection
