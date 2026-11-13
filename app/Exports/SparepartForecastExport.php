<?php

namespace App\Exports;

use App\Models\AiForecastRun;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/** Whole-run export: exactly what the engine stored, no recalculation. */
class SparepartForecastExport implements WithMultipleSheets
{
    public function __construct(private AiForecastRun $run) {}

    public function sheets(): array
    {
        $run = $this->run;
        $eval = $run->evaluation;

        $summary = [
            ['Run', $run->id],
            ['Periode', $run->period_type.' '.$run->period_start->format('Y-m-d').' s/d '.$run->period_end->format('Y-m-d')],
            ['Algoritma', $run->algorithm_version],
            ['Waktu analisis', $run->finished_at?->format('Y-m-d H:i')],
            ['Rentang histori', ($run->history_from?->format('Y-m-d') ?? '-').' s/d '.($run->history_to?->format('Y-m-d') ?? '-')],
            ['Data fingerprint', $run->data_fingerprint],
            ['Scope area', $run->scope_area ?? 'Semua'],
            ['Scope machine type', $run->scope_machine_type ?? 'Semua'],
            ['Akurasi', $eval && $eval->status === 'AVAILABLE'
                ? sprintf('MAE %s | WAPE %s%% | Bias %s%%', $eval->mae, round($eval->wape * 100, 1), round($eval->bias * 100, 1))
                : 'Accuracy Not Available: Insufficient Testing Data'],
        ];

        $forecast = [['Machine', 'Machine Type', 'Area', 'Material Number', 'Description', 'Unit', 'PM Window', 'PM Used', 'Total Qty (hist)',
            'Avg Qty', 'Interval (days)', 'Last Used', 'Trend', 'Probability', 'Upcoming PM', 'Expected Qty', 'Recommended Qty', 'Level', 'Confidence']];
        $run->results()->orderBy('machine_number')->orderByDesc('expected_qty')->chunk(1000, function ($rows) use (&$forecast) {
            foreach ($rows as $r) {
                $forecast[] = [$r->machine_number, $r->machine_type, $r->area, $r->material_number, $r->description, $r->unit,
                    $r->pm_considered, $r->usage_pm_count, $r->total_qty, $r->avg_qty, $r->mean_interval_days,
                    $r->last_used_date?->format('Y-m-d'), $r->trend, $r->probability, $r->upcoming_pm_count, $r->expected_qty,
                    $r->recommended_qty, $r->level, $r->confidence];
            }
        });

        $demand = [['Material Number', 'Description', 'Unit', 'Machines', 'Expected Qty', 'Recommended Qty']];
        $run->results()->selectRaw('material_number, description, unit, COUNT(DISTINCT machine_id) m, SUM(expected_qty) e, SUM(recommended_qty) r')
            ->groupBy('material_number', 'description', 'unit')->orderByDesc('r')->get()
            ->each(function ($r) use (&$demand) {
                $demand[] = [$r->material_number, $r->description, $r->unit, $r->m, (float) $r->e, (int) $r->r];
            });

        $machines = [['Machine', 'Machine Type', 'Area', 'Status', 'Finished PM', 'PM With Usage', 'PM Excluded (Injection)', 'Upcoming PM', 'Confidence', 'Note']];
        $run->machines()->orderBy('machine_number')->get()->each(function ($m) use (&$machines) {
            $machines[] = [$m->machine_number, $m->machine_type, $m->area, $m->status, $m->finished_pm_count, $m->pm_with_usage_count,
                $m->excluded_pm_count, $m->upcoming_pm_count, $m->confidence, $m->note];
        });

        return [
            new class('Summary', $summary) implements FromArray, WithTitle {
                public function __construct(private string $t, private array $d) {}

                public function array(): array { return $this->d; }

                public function title(): string { return $this->t; }
            },
            new class('Forecast per Machine', $forecast) implements FromArray, WithTitle {
                public function __construct(private string $t, private array $d) {}

                public function array(): array { return $this->d; }

                public function title(): string { return $this->t; }
            },
            new class('Demand per Sparepart', $demand) implements FromArray, WithTitle {
                public function __construct(private string $t, private array $d) {}

                public function array(): array { return $this->d; }

                public function title(): string { return $this->t; }
            },
            new class('Machine Status', $machines) implements FromArray, WithTitle {
                public function __construct(private string $t, private array $d) {}

                public function array(): array { return $this->d; }

                public function title(): string { return $this->t; }
            },
        ];
    }
}
