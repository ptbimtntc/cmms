@extends('layouts.app')

@section('title', 'Machine Problem Findings')

@section('content')
    @php
        $fullWidth = true;
        $label = 'text-xs font-medium text-text-muted';
        $canManage = ! auth()->user()->isSupervisor();
        $hasFilters = collect(request()->only(['search', 'category']))->filter()->isNotEmpty();
        $actions = fn ($f, $mobile = false) => ['edit' => route('machine-problem-findings.edit', ['machine_problem_finding' => $f->id, 'page' => request('page')]), 'delete' => route('machine-problem-findings.destroy', $f->id), 'confirm' => 'Delete this finding?', 'mobile' => $mobile];
    @endphp

    <div class="space-y-5">
        <x-ui.page-header title="Machine Problem Findings" description="Findings grouped by problem category, used by the PM Result form.">
            @if ($canManage)
                <x-slot:actions>
                    <x-ui.button href="{{ route('machine-problem-findings.create') }}">Add Finding</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        @if ($canManage)
            @include('partials.master.import-form', ['action' => route('machine-problem-findings.import'), 'id' => 'fileInput'])
        @endif

        @if ($errors->has('file'))
            <x-ui.alert tone="danger">{{ $errors->first('file') }}</x-ui.alert>
        @endif
        @if (session('success'))
            <x-ui.alert tone="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('machine_problem_findings_import_result'))
            @include('partials.master.import-result', ['result' => session('machine_problem_findings_import_result')])
        @endif

        <form method="GET" class="card flex flex-col gap-3 p-4 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid gap-1.5 sm:w-64">
                <label for="mpf-search" class="{{ $label }}">Search</label>
                <input id="mpf-search" type="text" name="search" value="{{ request('search') }}" placeholder="Finding or category" class="input lg:min-h-9">
            </div>
            <div class="grid gap-1.5 sm:w-48">
                <label for="mpf-category" class="{{ $label }}">Category</label>
                <select id="mpf-category" name="category" class="select lg:min-h-9">
                    <option value="">All Category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') == $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid gap-1.5 sm:w-44">
                <label for="mpf-sort" class="{{ $label }}">Sort</label>
                <select id="mpf-sort" name="sort" class="select lg:min-h-9">
                    <option value="">Default Sort</option>
                    <option value="category_asc" @selected(request('sort') == 'category_asc')>Category ↑</option>
                    <option value="category_desc" @selected(request('sort') == 'category_desc')>Category ↓</option>
                    <option value="finding_asc" @selected(request('sort') == 'finding_asc')>Finding ↑</option>
                    <option value="finding_desc" @selected(request('sort') == 'finding_desc')>Finding ↓</option>
                </select>
            </div>
            <div class="flex gap-2 sm:ml-auto">
                <x-ui.button type="submit" class="flex-1 sm:flex-none lg:min-h-9">Filter</x-ui.button>
                <x-ui.button variant="ghost" href="{{ route('machine-problem-findings.index') }}" class="lg:min-h-9">Reset</x-ui.button>
            </div>
        </form>

        @if ($findings->isEmpty())
            <x-ui.empty-state title="No finding found" :description="$hasFilters ? 'Change the filters, or reset them to see every finding.' : ($canManage ? 'Add a finding or import a file above.' : null)" />
        @endif

        {{-- Desktop table. Keep this list first: infinite scroll matches lists by key and position. --}}
        <div @class(['card hidden overflow-hidden', 'md:block' => $findings->isNotEmpty()])>
            <div class="relative overflow-x-auto">
                <table class="data-table min-w-full">
                    <thead>
                        <tr>
                            <th scope="col">Category</th>
                            <th scope="col">Finding</th>
                            @if ($canManage)
                                <th scope="col"><span class="sr-only">Action</span></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody data-infinite-list="findings">
                        @foreach ($findings as $finding)
                            <tr>
                                <td class="whitespace-nowrap font-semibold text-text">{{ $finding->category }}</td>
                                <td class="text-text">{{ $finding->finding }}</td>
                                @if ($canManage)
                                    <td>@include('partials.master.row-actions', $actions($finding))</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Phone cards (below md). Second list with the same key. --}}
        <div class="space-y-3 md:hidden" data-infinite-list="findings">
            @foreach ($findings as $finding)
                <article class="card min-w-0 p-4">
                    <p class="text-xs font-medium text-text-muted">{{ $finding->category }}</p>
                    <h2 class="break-words text-base font-semibold text-text">{{ $finding->finding }}</h2>
                    @if ($canManage)
                        <div class="mt-3 border-t border-border pt-3">@include('partials.master.row-actions', $actions($finding, true))</div>
                    @endif
                </article>
            @endforeach
        </div>

        <x-infinite-scroll :paginator="$findings" key="findings" />
    </div>
@endsection
