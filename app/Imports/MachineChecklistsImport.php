<?php

namespace App\Imports;

use App\Models\MachineChecklist;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class MachineChecklistsImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        $importedCount = 0;
        $duplicateCount = 0;
        $skippedCount = 0;

        foreach ($rows->skip(1) as $row) {

            if (empty($row[0])) {
                $skippedCount++;

                continue;
            }

            $checklist = MachineChecklist::updateOrCreate(
                [
                    'machine_type'   => trim($row[0]),
                    'section'        => trim($row[1]),
                    'checklist_item' => trim($row[3]),
                ],
                [
                    'maintenance_type' => strtolower(trim($row[4] ?? 'check')),
                    'section_order' => (int) $row[2],
                    'item_order'    => (int) $row[5],
                ]
            );

            if ($checklist->wasRecentlyCreated) {
                $importedCount++;
            } else {
                $duplicateCount++;
            }
        }

        session()->flash('machine_checklists_import_result', [
            'imported' => $importedCount,
            'duplicate' => $duplicateCount,
            'skipped' => $skippedCount,
        ]);
    }
}
