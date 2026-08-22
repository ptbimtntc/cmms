<?php

namespace App\Imports;

use App\Models\Machine;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use App\Models\PMSchedule;
use App\Models\PMDetail;
use App\Models\PMSparepart;

class MachinesImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        $importedCount = 0;
        $duplicateCount = 0;
        $skippedCount = 0;

        foreach ($rows->skip(1) as $row) {

            $machineNumber = trim($row[0]);

            // skip empty row
            if (!$machineNumber) {
                $skippedCount++;

                continue;
            }

            $machineType   = trim($row[1] ?? null);
            $area          = trim($row[2] ?? null);
            $status        = strtoupper(trim($row[3] ?? 'ACTIVE'));

            $pmCycleValue = $row[4] ?? null;
            $pmCycleUnit  = strtoupper(trim($row[5] ?? null));

            // validasi status
            if (!in_array($status, ['ACTIVE', 'INACTIVE'])) {
                $status = 'ACTIVE';
            }

            // Validasi PM Cycle Value
            if (!is_numeric($pmCycleValue)) {
                $pmCycleValue = null;
            }

            // Validasi PM Cycle Unit
            $allowedCycleUnit = [
                'DAY',
                'WEEK',
                'MONTH',
                'HOUR',
            ];

            if (!in_array($pmCycleUnit, $allowedCycleUnit)) {
                $pmCycleUnit = null;
            }

            $machine = Machine::updateOrCreate(
                [
                    'machine_number'  => $machineNumber
                ],
                [
                    'machine_type'    => $machineType,
                    'area'            => $area,
                    'status'          => $status,
                    'pm_cycle_value'  => $pmCycleValue,
                    'pm_cycle_unit'   => $pmCycleUnit,
                ]
            );

            if ($machine->wasRecentlyCreated) {
                $importedCount++;
            } else {
                $duplicateCount++;
            }
        }

        session()->flash('machines_import_result', [
            'imported' => $importedCount,
            'duplicate' => $duplicateCount,
            'skipped' => $skippedCount,
        ]);
    }
}
