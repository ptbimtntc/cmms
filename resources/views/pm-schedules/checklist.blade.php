@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6">

    {{-- Header --}}
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-gray-800">
            PM Checklist
        </h1>
    </div>

    {{-- Machine Information --}}
    <div class="mb-6 rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-semibold">
            Machine Information
        </h2>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
            <div>
                <label class="text-sm text-gray-500">Machine Number</label>
                <div class="font-semibold">{{ $pmSchedule->machine_number }}</div>
            </div>
            <div>
                <label class="text-sm text-gray-500">Machine Type</label>
                <div class="font-semibold">{{ $pmSchedule->machine_type }}</div>
            </div>
            <div>
                <label class="text-sm text-gray-500">Order Number</label>
                <div class="font-semibold">{{ $pmSchedule->order_number }}</div>
            </div>
            <div>
                <label class="text-sm text-gray-500">PIC PM</label>
                <div class="font-semibold">{{ $pmSchedule->pic }}</div>
            </div>
            <div>
                <label class="text-sm text-gray-500">PM Date</label>
                <div class="font-semibold">
                    {{ $executionDate ? $executionDate->format('d-m-Y') : ($pmSchedule->actual_date ? \Carbon\Carbon::parse($pmSchedule->actual_date)->format('d-m-Y') : '-') }}
                </div>
            </div>
            <div>
                <label class="text-sm text-gray-500">Duration</label>
                <div class="font-semibold">{{ $pmSchedule->duration_formatted }}</div>
            </div>
            <div>
                <label class="text-sm text-gray-500">Cycle PM</label>
                <div class="font-semibold">
                    {{ $pmSchedule->machine->pm_cycle_value }} {{ ucfirst($pmSchedule->machine->pm_cycle_unit) }}
                </div>
            </div>
            <div>
                <label class="text-sm text-gray-500">Next PM</label>
                <div class="font-semibold">{{ $nextPm ? $nextPm->format('d-m-Y') : '-' }}</div>
            </div>
            @if ($pmSchedule->isGearboxApplicable())
                <div>
                    <label class="text-sm text-gray-500">Gearbox Problem</label>
                    <div class="font-semibold">{{ $pmSchedule->gearbox_problem }}</div>
                </div>
            @endif
            <div>
                <label class="text-sm text-gray-500">Sparepart Cost</label>
                <div class="font-semibold
                        @if($spareCost == 0) text-gray-500
                        @elseif($spareCost < 1000) text-green-600
                        @elseif($spareCost < 3000) text-yellow-600
                        @else text-red-600
                        @endif">
                    $ {{ number_format($spareCost, 2, ',', '.') }}
                </div>
            </div>
        </div>
    </div>

    {{-- Checklist --}}
    <form action="{{ route('pm-schedules.checklist.save', $pmSchedule) }}" method="POST">
        @csrf

        <div class="max-h-[75vh] overflow-auto rounded-lg bg-white shadow">

            <table class="block w-full border-collapse md:table">

                <thead class="sticky top-0 z-20 hidden bg-gray-100 shadow-sm md:table-header-group">
                    <tr class="md:table-row">
                        <th class="border bg-gray-100 px-3 py-3 text-left md:w-5/12">Checklist Item</th>
                        <th title="Clean" class="border px-3 py-3 text-center md:w-24">
                            <span class="hidden xl:inline">Clean</span>
                            <span class="inline xl:hidden">CLN</span>
                        </th>
                        <th title="Check" class="border px-3 py-3 text-center md:w-24">
                            <span class="hidden xl:inline">Check</span>
                            <span class="inline xl:hidden">CHK</span>
                        </th>
                        <th title="Lubrication" class="border px-3 py-3 text-center md:w-28">
                            <span class="hidden xl:inline">Lubrication</span>
                            <span class="inline xl:hidden">LUB</span>
                        </th>
                        <th title="Replace" class="border px-3 py-3 text-center md:w-24">
                            <span class="hidden xl:inline">Replace</span>
                            <span class="inline xl:hidden">REP</span>
                        </th>
                        <th title="Remarks" class="border bg-gray-100 px-3 py-3 text-left">
                            <span class="hidden xl:inline">Remarks</span>
                            <span class="inline xl:hidden">Rem..</span>
                        </th>
                    </tr>
                </thead>

                <tbody class="block space-y-3 p-3 md:table-row-group md:space-y-0 md:p-0">

                    @php $currentSection = ''; @endphp

                    @foreach ($checklists as $i => $item)
                        @php $oldChecklist = $pmChecklists[$item->id] ?? null; @endphp

                        @if ($currentSection != $item->section)
                            @php $currentSection = $item->section; @endphp

                            <tr class="block md:table-row">
                                <td colspan="6"
                                    class="block border bg-slate-200 px-4 py-2 font-semibold text-slate-800 md:table-cell">
                                    {{ $item->section }}
                                </td>
                            </tr>
                        @endif

                        <tr class="block rounded-lg border bg-white p-3 shadow-sm hover:bg-gray-50 md:table-row md:rounded-none md:border-0 md:bg-transparent md:p-0 md:shadow-none">

                            {{-- Checklist item --}}
                            <td class="block border-0 px-0 py-1 md:table-cell md:border md:px-3 md:py-3">
                                <div class="flex items-center gap-2">
                                    <span>{{ $item->checklist_item }}</span>
                                    @if($item->maintenance_type == 'replace')
                                        <span class="rounded bg-orange-100 px-2 py-0.5 text-[10px] font-bold text-orange-700">
                                            REP
                                        </span>
                                    @endif
                                </div>
                                <input type="hidden" name="checklists[{{ $i }}][machine_checklist_id]" value="{{ $item->id }}">
                            </td>

                            {{-- CLEAN --}}
                            <td class="block border-0 px-0 py-2 md:table-cell md:border md:px-3 md:py-3 md:text-center">
                                @if($item->maintenance_type == 'clean')
                                    <div class="flex items-center justify-between md:justify-center">
                                        <span class="text-xs font-medium text-gray-500 md:hidden">Clean</span>
                                        <input type="hidden" name="checklists[{{ $i }}][clean]" value="NO">
                                        <input type="checkbox" name="checklists[{{ $i }}][clean]" value="YES"
                                            {{ $oldChecklist?->clean == 'YES' ? 'checked' : '' }}
                                            class="h-6 w-6 cursor-pointer rounded border-2 border-slate-400 text-green-600 transition duration-150 focus:ring-2 focus:ring-green-500">
                                    </div>
                                @endif
                            </td>

                            {{-- CHECK --}}
                            <td class="block border-0 px-0 py-2 md:table-cell md:border md:px-3 md:py-3 md:text-center">
                                @if($item->maintenance_type != 'replace')
                                    <div class="flex items-center justify-between md:justify-center">
                                        <span class="text-xs font-medium text-gray-500 md:hidden">Check</span>
                                        <input type="hidden" name="checklists[{{ $i }}][check]" value="NO">
                                        <input type="checkbox" name="checklists[{{ $i }}][check]" value="YES"
                                            {{ $oldChecklist?->check == 'YES' ? 'checked' : '' }}
                                            class="h-6 w-6 cursor-pointer rounded border-2 border-slate-400 text-green-600 transition duration-150 focus:ring-2 focus:ring-green-500">
                                    </div>
                                @endif
                            </td>

                            {{-- LUBRICATION --}}
                            <td class="block border-0 px-0 py-2 md:table-cell md:border md:px-3 md:py-3 md:text-center">
                                @if($item->maintenance_type == 'lubrication')
                                    <div class="flex items-center justify-between md:justify-center">
                                        <span class="text-xs font-medium text-gray-500 md:hidden">Lubrication</span>
                                        <input type="hidden" name="checklists[{{ $i }}][lubrication]" value="NO">
                                        <input type="checkbox" name="checklists[{{ $i }}][lubrication]" value="YES"
                                            {{ $oldChecklist?->lubrication == 'YES' ? 'checked' : '' }}
                                            class="h-6 w-6 cursor-pointer rounded border-2 border-slate-400 text-green-600 transition duration-150 focus:ring-2 focus:ring-green-500">
                                    </div>
                                @endif
                            </td>

                            {{-- REPLACE --}}
                            <td class="block border-0 px-0 py-2 md:table-cell md:border md:px-3 md:py-3 md:text-center">
                                <div class="flex items-center justify-between md:justify-center">
                                    <span class="text-xs font-medium text-gray-500 md:hidden">Replace</span>
                                    <input type="hidden" name="checklists[{{ $i }}][replace]" value="NO">
                                    <input type="checkbox" name="checklists[{{ $i }}][replace]" value="YES"
                                        {{ $oldChecklist?->replace == 'YES' ? 'checked' : '' }}
                                        class="h-6 w-6 cursor-pointer rounded border-2 text-green-600 transition duration-150 focus:ring-2 focus:ring-green-500
                                        {{ $item->maintenance_type == 'replace'
                                            ? 'border-red-600 bg-red-50 text-red-600 accent-red-600 ring-4 ring-red-500/30'
                                            : 'border-slate-400' }}">
                                </div>
                            </td>

                            {{-- REMARKS --}}
                            <td class="block border-0 px-0 py-2 md:table-cell md:border md:px-3 md:py-2 md:text-center">
                                <div class="flex items-center justify-between md:justify-center">
                                    <span class="text-xs font-medium text-gray-500 md:hidden">Remarks</span>
                                    <button type="button"
                                        class="remark-btn text-xl text-gray-500 hover:text-blue-600 {{ $oldChecklist?->remarks ? 'text-green-600' : '' }}"
                                        data-index="{{ $i }}" data-item="{{ $item->checklist_item }}">
                                        {{ $oldChecklist?->remarks ? '📝' : '✏️' }}
                                    </button>
                                    <input type="hidden" id="remark-{{ $i }}" name="checklists[{{ $i }}][remarks]"
                                        value="{{ $oldChecklist?->remarks ?? '' }}">
                                </div>
                            </td>

                        </tr>
                    @endforeach

                </tbody>
            </table>
        </div>

        <div class="mt-6 flex flex-col justify-between gap-3 sm:flex-row">
            <a href="{{ route('pm-schedules.edit', $pmSchedule->id) }}"
                class="rounded bg-gray-500 px-6 py-3 text-center text-white hover:bg-gray-600">
                ← Back to Fill PM
            </a>

            <button type="submit" class="rounded bg-green-600 px-6 py-3 text-white hover:bg-green-700">
                Save Checklist
            </button>
        </div>

    </form>

    {{-- Remarks Modal --}}
    <div id="remarkModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40">
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl mx-4">
            <h2 class="mb-4 text-lg font-bold">
                Checklist Remarks
            </h2>

            <div class="mb-4">
                <label class="mb-1 block text-sm text-gray-500">Checklist Item</label>
                <div id="remarkItem" class="rounded bg-gray-100 p-3 font-semibold"></div>
            </div>

            <div>
                <label class="mb-1 block text-sm text-gray-500">Remarks</label>
                <textarea id="remarkTextarea" rows="5" class="w-full rounded-lg border p-3"
                    placeholder="Input remarks..."></textarea>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button type="button" id="cancelRemark" class="rounded border px-4 py-2">Cancel</button>
                <button type="button" id="saveRemark" class="rounded bg-blue-600 px-4 py-2 text-white">Save</button>
            </div>
        </div>
    </div>

@endsection