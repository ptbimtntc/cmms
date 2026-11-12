@extends('layouts.app')

@php
    $fullWidth = true;
    $contentMaxWidth = 'max-w-5xl';
@endphp

@section('title', 'Detail WO '.$workOrder->order_number)

@section('content')
    @php
        $overdue = $workOrder->isOpen() && $workOrder->due_date->isPast() && ! $workOrder->due_date->isToday();
        $reopenedEdit = null;
        if (old('_edit_wo') && $workOrder->isManageableBy(auth()->user())) {
            $hist = $workOrder->actions->count() + $workOrder->reopens->count();
            $reopenedEdit = [
                'id' => $workOrder->id, 'url' => route('predictive.update', $workOrder), 'order' => $workOrder->order_number,
                'order_input' => old('order_number', $workOrder->order_number), 'machine_number' => old('machine_number', $workOrder->machine_number),
                'wo_date' => old('wo_date', $workOrder->wo_date->format('Y-m-d')), 'due_date' => old('due_date', $workOrder->due_date->format('Y-m-d')),
                'problem' => old('problem_description', $workOrder->problem_description),
                'locked' => $workOrder->pic_user_id !== null || $hist > 0,
            ];
        }
        $reopenedAction = null;
        if (old('_action_wo') && $workOrder->canBeActionedBy(auth()->user())) {
            $reopenedAction = [
                'id' => $workOrder->id, 'order' => $workOrder->order_number, 'machine' => $workOrder->machine_number,
                'url' => route('predictive.action', $workOrder), 'next' => $workOrder->actions->count() + 1,
            ];
        }
    @endphp

    <div x-data="{ action: @js($reopenedAction), edit: @js($reopenedEdit) }">
        <div class="space-y-5">
        <x-ui.button variant="ghost" href="{{ route('predictive.index', ['tab' => $tab]) }}" class="-ml-2">Kembali ke daftar WO</x-ui.button>

        @if (auth()->user()->isPic() && ! $canWork)
            <div class="alert alert-warning flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between" role="status">
                <p>Aktivitas Action Predictive belum dimulai, jadi Claim dan Action nonaktif.</p>
                <x-ui.button variant="outline" class="shrink-0" href="{{ route('predictive.index', ['tab' => $tab]) }}">Buka daftar WO untuk memulai</x-ui.button>
            </div>
        @endif

        @foreach (['success' => 'success', 'error' => 'danger'] as $flash => $tone)
            @if (session($flash))
                <x-ui.alert :tone="$tone">{{ session($flash) }}</x-ui.alert>
            @endif
        @endforeach

        <section class="card overflow-hidden">
            <div class="flex items-start justify-between gap-3 border-b border-border p-5 sm:p-6">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-text-muted">{{ $workOrder->categoryLabel() }}</p>
                    <h1 class="mt-1 break-words font-signage text-3xl font-semibold leading-tight text-text">{{ $workOrder->order_number }}</h1>
                </div>
                @include('predictive.partials.status-badge', ['wo' => $workOrder])
            </div>

            <div class="p-5 sm:p-6">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm md:grid-cols-4">
                    <div><dt class="text-xs text-text-muted">Machine Number</dt><dd class="font-semibold text-text">{{ $workOrder->machine_number }}</dd></div>
                    <div><dt class="text-xs text-text-muted">Machine Type</dt><dd class="font-medium text-text">{{ $workOrder->machine_type ?: '-' }}</dd></div>
                    <div><dt class="text-xs text-text-muted">Area</dt><dd class="font-medium text-text">{{ $workOrder->area }}</dd></div>
                    <div><dt class="text-xs text-text-muted">PIC</dt><dd class="font-medium text-text">{{ $workOrder->pic?->name ?? 'Belum di-claim' }}</dd></div>
                    <div><dt class="text-xs text-text-muted">Tanggal WO</dt><dd class="font-medium text-text">{{ $workOrder->wo_date->format('d-m-Y') }}</dd></div>
                    <div><dt class="text-xs text-text-muted">Due Date</dt><dd @class(['font-medium', 'text-danger' => $overdue, 'text-text' => ! $overdue])>{{ $workOrder->due_date->format('d-m-Y') }}{{ $overdue ? ' (terlambat)' : '' }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-text-muted">Dibuka</dt><dd class="font-medium text-text">{{ $workOrder->created_at->format('d-m-Y H:i') }}</dd></div>
                </dl>

                <div class="mt-5 border-t border-border pt-4">
                    <h2 class="text-xs text-text-muted">Problem Description</h2>
                    <p class="mt-1 whitespace-pre-line break-words text-sm text-text">{{ $workOrder->problem_description }}</p>
                </div>

                <div class="mt-5">@include('predictive.partials.wo-buttons', ['wo' => $workOrder, 'size' => 'card', 'showDetail' => false])</div>
            </div>
        </section>

        <section class="card p-5 sm:p-6" aria-labelledby="pv-history-heading">
            <h2 id="pv-history-heading" class="font-semibold text-text">Riwayat Action dan Reopen</h2>
            @if ($history->isEmpty())
                <x-ui.empty-state class="mt-4" title="Belum ada Action." description="WO ini menunggu PIC melakukan claim." />
            @else
                {{-- Timeline: Action dots are green (closing), Reopen dots amber; each line also says which it is. --}}
                <ol class="ml-1 mt-4 space-y-5 border-l-2 border-border pl-5">
                    @foreach ($history as $event)
                        @if ($event['type'] === 'action')
                            @php($a = $event['model'])
                            <li class="relative">
                                <span class="absolute -left-[27px] top-1 size-3 rounded-full border-2 border-surface bg-success" aria-hidden="true"></span>
                                <p class="text-sm font-semibold text-text">Action #{{ $a->action_number }} <span class="font-normal text-text-muted">· {{ $a->actioned_at->format('d-m-Y H:i') }} · {{ $a->pic_name }}</span></p>
                                <ul class="mt-2 space-y-2 text-sm">
                                    @foreach ($a->problems as $problem)
                                        <li>
                                            <p class="break-words"><span class="text-text-muted">Problem: </span><span class="font-medium text-text">{{ $problem->problem }}</span></p>
                                            <ul class="mt-0.5 list-disc space-y-0.5 pl-5 text-text">
                                                @foreach ($problem->findings as $finding)
                                                    <li class="break-words"><span class="text-text-muted">Finding: </span>{{ $finding->finding }}</li>
                                                @endforeach
                                            </ul>
                                        </li>
                                    @endforeach
                                    @if ($a->remarks)<li><span class="text-text-muted">Remarks: </span><span class="whitespace-pre-line break-words text-text">{{ $a->remarks }}</span></li>@endif
                                </ul>
                            </li>
                        @else
                            @php($r = $event['model'])
                            <li class="relative">
                                <span class="absolute -left-[27px] top-1 size-3 rounded-full border-2 border-surface bg-warning" aria-hidden="true"></span>
                                <p class="text-sm font-semibold text-warning-text">Reopen <span class="font-normal text-text-muted">· {{ $r->reopened_at->format('d-m-Y H:i') }} · {{ $r->user_name }}</span></p>
                            </li>
                        @endif
                    @endforeach
                </ol>
            @endif
        </section>
        </div>

        @if ($workOrder->isManageableBy(auth()->user()))
            @include('predictive.partials.machine-picker-script')
            @include('predictive.partials.edit-modal')
        @endif
        @include('predictive.partials.action-modal')
    </div>
@endsection
