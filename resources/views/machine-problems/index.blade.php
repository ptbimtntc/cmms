@extends('layouts.app')

@section('title', 'Machine Big Problem Master')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $canManage = ! auth()->user()->isSupervisor();
        $actions = fn ($p, $mobile = false) => ['edit' => route('machine-problems.edit', $p->id), 'delete' => route('machine-problems.destroy', $p->id), 'confirm' => 'Delete this problem?', 'mobile' => $mobile];
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Machine Big Problem Master" description="Big problem per machine type, used by the PM Result form.">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button href="{{ route('machine-problems.create') }}">Add Problem</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if ($canManage)
            @include('partials.master.import-form', ['action' => route('machine-problems.import'), 'accept' => '.csv', 'id' => 'fileInput'])
        @endif

        @if ($errors->has('file'))
            <x-ui.alert tone="danger">{{ $errors->first('file') }}</x-ui.alert>
        @endif
        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('machine_problems_import_result'))
            @include('partials.master.import-result', ['result' => session('machine_problems_import_result')])
        @endif

        {{-- Filters submit on change. --}}
        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-56">
                <label for="mp-type" class="{{ $label }}">Machine Type</label>
                <select id="mp-type" name="machine_type" class="select lg:min-h-9" onchange="this.form.submit()">
                    <option value="">All Machine Type</option>
                    @foreach ($machines->unique('machine_type') as $machine)
                        <option value="{{ $machine->machine_type }}" @selected(request('machine_type') == $machine->machine_type)>{{ $machine->machine_type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-56">
                <label for="mp-problem" class="{{ $label }}">Problem</label>
                <select id="mp-problem" name="problem" class="select lg:min-h-9" onchange="this.form.submit()">
                    <option value="">All Problems</option>
                    @foreach ($machineProblems->pluck('problem')->unique() as $problem)
                        <option value="{{ $problem }}" @selected(request('problem') == $problem)>{{ $problem }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-52">
                <label for="mp-sort" class="{{ $label }}">Sort</label>
                <select id="mp-sort" name="sort" class="select lg:min-h-9" onchange="this.form.submit()">
                    <option value="">Default Sort</option>
                    <option value="machine_type_asc" @selected(request('sort') == 'machine_type_asc')>Machine Type ↑</option>
                    <option value="machine_type_desc" @selected(request('sort') == 'machine_type_desc')>Machine Type ↓</option>
                    <option value="problem_asc" @selected(request('sort') == 'problem_asc')>Problem A-Z</option>
                    <option value="problem_desc" @selected(request('sort') == 'problem_desc')>Problem Z-A</option>
                </select>
            </div>
            @if (request()->hasAny(['machine_type', 'problem', 'sort']))
                <x-ui.button variant="ghost" href="{{ route('machine-problems.index') }}" class="sm:ml-auto lg:min-h-9">Reset Filter</x-ui.button>
            @endif
        </form>

        @if ($machineProblems->isEmpty())
            <x-ui.empty-state title="No Big Problem data found" :description="$canManage ? 'Add a problem or import a CSV file above.' : null" />
        @else
            <div class="card hidden overflow-hidden md:block">
                <div class="relative overflow-x-auto">
                    <table class="data-table min-w-full">
                        <thead>
                            <tr>
                                <th scope="col">Machine Type</th>
                                <th scope="col">Big Problem</th>
                                <th scope="col">Category</th>
                                @if ($canManage)
                                    <th scope="col"><span class="sr-only">Action</span></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($machineProblems as $p)
                                <tr>
                                    <td class="whitespace-nowrap font-semibold text-text">{{ $p->machine_type }}</td>
                                    <td class="text-text">{{ $p->problem }}</td>
                                    <td>{{ $p->category }}</td>
                                    @if ($canManage)
                                        <td>@include('partials.master.row-actions', $actions($p))</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-3 md:hidden">
                @foreach ($machineProblems as $p)
                    <article class="card min-w-0 p-4">
                        <h2 class="text-base font-semibold text-text">{{ $p->machine_type }}</h2>
                        <dl class="mt-3 grid gap-y-2 border-t border-border pt-3 text-sm">
                            <div><dt class="text-xs text-text-muted">Big Problem</dt><dd class="break-words font-medium text-text">{{ $p->problem }}</dd></div>
                            <div><dt class="text-xs text-text-muted">Category</dt><dd class="font-medium text-text">{{ $p->category }}</dd></div>
                        </dl>
                        @if ($canManage)
                            <div class="mt-3 border-t border-border pt-3">@include('partials.master.row-actions', $actions($p, true))</div>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    </div>
@endsection
