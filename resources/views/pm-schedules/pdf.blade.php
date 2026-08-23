<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>PM Record {{ $pmSchedule->machine_number }}</title>
    <style>
        @page {
            margin: 26px 30px;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #1e293b;
        }

        h1, h2, h3, p {
            margin: 0;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #1e293b;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .header .brand {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 2px;
        }

        .header .title {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 3px;
            color: #475569;
            margin-top: 2px;
        }

        section {
            margin-bottom: 18px;
        }

        section h2 {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: #f1f5f9;
            border-left: 3px solid #334155;
            padding: 6px 10px;
            margin-bottom: 8px;
        }

        table.info {
            width: 100%;
            border-collapse: collapse;
        }

        table.info td {
            padding: 4px 8px;
            font-size: 11px;
            vertical-align: top;
        }

        table.info td.label {
            color: #64748b;
            width: 110px;
        }

        table.info td.value {
            font-weight: bold;
            width: 40%;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        table.data th,
        table.data td {
            border: 1px solid #cbd5e1;
            padding: 5px 7px;
            text-align: left;
            word-wrap: break-word;
        }

        table.data th {
            background: #e2e8f0;
            text-transform: uppercase;
            font-size: 9px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .muted {
            color: #94a3b8;
            font-style: italic;
        }

        .checklist-section-title {
            font-weight: bold;
            font-size: 10px;
            margin: 8px 0 4px;
        }

        tfoot td {
            font-weight: bold;
            background: #f8fafc;
        }

        .footer-note {
            margin-top: 20px;
            font-size: 8px;
            color: #94a3b8;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">FREEDOMS</div>
        <div class="title">Preventive Maintenance Record</div>
    </div>

    <section>
        <h2>PM Information</h2>
        <table class="info">
            <tr>
                <td class="label">Area</td>
                <td class="value">{{ $pmSchedule->area ?: '-' }}</td>
                <td class="label">Status</td>
                <td class="value">{{ $statusLabel }}</td>
            </tr>
            <tr>
                <td class="label">Machine Number</td>
                <td class="value">{{ $pmSchedule->machine_number ?: '-' }}</td>
                <td class="label">PIC</td>
                <td class="value">{{ $pmSchedule->pic ?: '-' }}</td>
            </tr>
            <tr>
                <td class="label">Machine Type</td>
                <td class="value">{{ $pmSchedule->machine_type ?: '-' }}</td>
                <td class="label">Plan Date</td>
                <td class="value">{{ $planDate }}</td>
            </tr>
            <tr>
                <td class="label">Order Number</td>
                <td class="value">{{ $pmSchedule->order_number ?: '-' }}</td>
                <td class="label">Due Date</td>
                <td class="value">{{ $dueDate }}</td>
            </tr>
            <tr>
                <td class="label"></td>
                <td class="value"></td>
                <td class="label">Action Date</td>
                <td class="value">{{ $actionDate }}</td>
            </tr>
        </table>
    </section>

    <section>
        <h2>Measurement</h2>
        @if ($pmSchedule->measurements->isEmpty())
            <p class="muted">No measurement recorded.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Measurement Item</th>
                        <th>Standard</th>
                        <th>Value</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pmSchedule->measurements as $measurement)
                        <tr>
                            <td>{{ $measurement->measurement_item ?: '-' }}</td>
                            <td>{{ $measurement->standard ?: '-' }}</td>
                            <td>{{ $measurement->measurement_value ?: '-' }}</td>
                            <td>{{ $measurement->unit ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section>
        <h2>Problem / Finding</h2>
        @if ($pmSchedule->problems->isEmpty())
            <p class="muted">No problem/finding recorded.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Problem</th>
                        <th>Finding</th>
                        <th>Severity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pmSchedule->problems as $problem)
                        <tr>
                            <td>{{ $problem->machineProblem->problem ?? '-' }}</td>
                            <td>{{ $problem->machineProblemFinding->finding ?? '-' }}</td>
                            <td>{{ $problem->severity ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section>
        <h2>Checklist</h2>
        @if ($pmSchedule->checklists->isEmpty())
            <p class="muted">No checklist recorded.</p>
        @else
            @foreach ($checklistSections as $sectionName => $items)
                <div class="checklist-section-title">{{ $sectionName }}</div>
                <table class="data">
                    <thead>
                        <tr>
                            <th>Checklist Item</th>
                            <th>Result</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $item)
                            <tr>
                                <td>{{ $item->machineChecklist->checklist_item ?? '-' }}</td>
                                <td>
                                    @php $results = collect($item->results)->pluck('text')->implode(', '); @endphp
                                    {{ $results !== '' ? $results : '-' }}
                                </td>
                                <td>{{ $item->remarks ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endif
    </section>

    <section>
        <h2>Sparepart</h2>
        @if ($pmSchedule->spareparts->isEmpty())
            <p class="muted">No sparepart used.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Material Number</th>
                        <th>Description</th>
                        <th class="text-center">Qty</th>
                        <th class="text-right">Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($pmSchedule->spareparts as $item)
                        @php
                            $price = $item->sparepart->price ?? 0;
                            $qty = $item->qty ?? 0;
                        @endphp
                        <tr>
                            <td>{{ $item->sparepart->material_number ?? '-' }}</td>
                            <td>{{ $item->sparepart->description ?? '-' }}</td>
                            <td class="text-center">{{ $qty }}</td>
                            <td class="text-right">USD {{ number_format($price, 2) }}</td>
                            <td class="text-right">USD {{ number_format($qty * $price, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" class="text-right">Total Cost</td>
                        <td class="text-right">USD {{ number_format($totalCost, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </section>

    @if ($pmSchedule->requiresOilChange() || $pmSchedule->greasing || $pmSchedule->wo_zsbp)
        <section>
            <h2>Oil Change / Greasing / Work Order</h2>
            <table class="info">
                @if ($pmSchedule->requiresOilChange())
                    <tr>
                        <td class="label">Oil Change</td>
                        <td class="value">{{ $pmSchedule->oil_change ?: '-' }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="label">Greasing</td>
                    <td class="value">{{ $pmSchedule->greasing ?: '-' }}</td>
                </tr>
                <tr>
                    <td class="label">WO ZSBP</td>
                    <td class="value">{{ $pmSchedule->wo_zsbp ?: '-' }}</td>
                </tr>
            </table>
        </section>
    @endif

    @if ($pmSchedule->remarks)
        <section>
            <h2>Remarks</h2>
            <p>{{ $pmSchedule->remarks }}</p>
        </section>
    @endif

    <div class="footer-note">
        Generated {{ $generatedAt }} &middot; FreeDOMS CMMS
    </div>
</body>
</html>
