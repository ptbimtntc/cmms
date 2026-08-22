<?php

namespace App\Imports;

use App\Models\Machine;
use App\Models\PMSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class PMScheduleImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        $duplicateCount = 0;
        $importedCount = 0;
        $skippedCount = 0;

        foreach ($rows->skip(1) as $row) {

            $machineNumber = trim((string) ($row[0] ?? ''));
            $orderNumber = trim((string) ($row[5] ?? ''));

            // Skip kalau machine number kosong
            if ($machineNumber === '') {
                $skippedCount++;

                continue;
            }

            // Skip kalau order number kosong
            if ($orderNumber === '') {
                $skippedCount++;

                continue;
            }

            $machine = Machine::where(
                'machine_number',
                $machineNumber
            )->first();

            // Skip kalau machine tidak ada
            if (! $machine) {
                $skippedCount++;

                continue;
            }

            // Normalize date
            try {
                $planDate = Carbon::createFromFormat(
                    'd-m-y',
                    trim((string) $row[4])
                )->startOfDay();

            } catch (\Exception $e) {
                $skippedCount++;

                continue;
            }

            // Pastikan hanya tanggal, tanpa jam
            $planDateFormatted = $planDate->format('Y-m-d');

        
            $exists = PMSchedule::where('machine_number', $machineNumber)
                ->whereDate('plan_date', $planDate)
                ->where('order_number', $orderNumber)
                ->exists();

            if ($exists) {
                $duplicateCount++;
                continue;
            }

            // HANYA DATA YANG BELUM ADA YANG DI-INSERT
            PMSchedule::create([
                'machine_id' => $machine->id,
                'machine_number' => $machineNumber,
                'machine_type' => $row[1],
                'area' => $machine->area,
                'plan_year' => $row[2],
                'plan_month' => $row[3],
                'plan_date' => $planDateFormatted,
                'due_date' => Carbon::parse($planDate)
                    ->addDays(14)
                    ->format('Y-m-d'),
                'order_number' => $orderNumber,
                'status' => $row[6] ?? 'OPEN',
            ]);
            $importedCount++;
        }


        session()->flash(
            'pm_schedules_import_result',
            [
                'imported' => $importedCount,
                'duplicate' => $duplicateCount,
                'skipped' => $skippedCount,
            ]
        );
    }
}
