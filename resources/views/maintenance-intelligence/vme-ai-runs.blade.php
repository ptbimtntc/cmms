@extends('layouts.app')

@php
    $hideSidebar = true;
    $hideTopbar = true;
    $fullWidth = true;
    $contentMaxWidth = 'max-w-[110rem]';

    $statusClass = ['ALERT' => 'badge-danger', 'WORKING' => 'badge-success', 'IDLE' => 'badge-neutral'];
    $statusLabel = ['ALERT' => 'Needs attention', 'WORKING' => 'Working', 'IDLE' => 'Idle'];
@endphp

@section('content')
<div class="space-y-6">
    <div class="card p-5 sm:p-6">
        <a href="{{ route('maintenance-intelligence.vme') }}" class="btn btn-ghost -ml-2 min-h-11">Virtual Maintenance Environment</a>
        <h1 class="mt-3 font-signage text-2xl font-semibold leading-tight text-text sm:text-3xl">AI Result Log</h1>
        <p class="mt-1 text-sm text-text-muted">
            Every AI character output is kept here, with a new row each time the result changes. This data is read from other features only; AI characters never change FreeDOMS data.
        </p>

        <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm text-text-muted">Character
                <select name="character" class="select mt-1 lg:min-h-9">
                    <option value="">All</option>
                    @foreach ($characters as $key => $name)
                        <option value="{{ $key }}" @selected($filters['character'] === $key)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-sm text-text-muted">From date
                <input type="date" name="from" value="{{ $filters['from'] }}" class="select mt-1 lg:min-h-9">
            </label>
            <label class="text-sm text-text-muted">Sampai tanggal
                <input type="date" name="to" value="{{ $filters['to'] }}" class="select mt-1 lg:min-h-9">
            </label>
            <button class="btn btn-primary min-h-11">Terapkan</button>
            <a href="{{ route('maintenance-intelligence.vme.ai-runs') }}" class="btn btn-ghost min-h-11">Reset</a>
        </form>
        @if ($errors->any())
            <p class="mt-3 text-sm text-danger">{{ $errors->first() }}</p>
        @endif
    </div>

    <section class="card relative overflow-x-auto">
        <table class="data-table min-w-full">
            <thead>
                <tr>
                    <th class="px-4 py-3">Waktu</th>
                    <th class="px-4 py-3">Character</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Ringkasan</th>
                    <th class="px-4 py-3">Sumber</th>
                    <th class="px-4 py-3">Referensi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border" data-infinite-list="runs">
                @forelse ($runs as $run)
                    <tr class="align-top">
                        <td class="whitespace-nowrap px-4 py-3 text-text-muted">{{ $run->created_at?->format('d-m-Y H:i:s') }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-medium text-text">{{ $characters[$run->character_key] ?? $run->character_key }}</td>
                        <td class="px-4 py-3"><span class="badge {{ $statusClass[$run->status] ?? 'badge-neutral' }}">{{ $statusLabel[$run->status] ?? $run->status }}</span></td>
                        <td class="px-4 py-3 text-text">
                            {{ $run->summary }}
                            @if (!empty($run->result))
                                <ul class="mt-1 text-xs text-text-muted">
                                    @foreach ($run->result as $line)
                                        <li>{{ $line['label'] ?? '' }}: {{ $line['value'] ?? '' }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-text-muted">{{ $run->source }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-text-muted">{{ $run->source_ref_type ? $run->source_ref_type.' #'.$run->source_ref_id : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-text-muted">No AI result stored yet{{ array_filter($filters) ? ' untuk filter ini' : '' }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <div><x-infinite-scroll :paginator="$runs" key="runs" /></div>
</div>
@endsection
