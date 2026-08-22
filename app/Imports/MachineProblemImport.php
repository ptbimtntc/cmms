<?php

namespace App\Imports;

use App\Models\MachineProblem;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MachineProblemImport implements ToCollection, WithHeadingRow
{
    private function normalizeRow($row): array
    {
        if (is_array($row)) {
            return $row;
        }

        if ($row instanceof \Illuminate\Support\Collection) {
            return $row->toArray();
        }

        if (is_object($row) && method_exists($row, 'toArray')) {
            return $row->toArray();
        }

        if ($row instanceof \ArrayAccess) {
            $data = [];
            foreach ($row as $key => $value) {
                $data[$key] = $value;
            }

            return $data;
        }

        if ($row instanceof \Traversable) {
            return iterator_to_array($row);
        }

        return (array) $row;
    }

    public function collection(Collection $rows)
    {
        $importedCount = 0;
        $duplicateCount = 0;
        $skippedCount = 0;

        foreach ($rows as $row) {
            $row = $this->normalizeRow($row);

            $machineType = trim((string) ($row['machine_type'] ?? $row['Machine Type'] ?? $row['machineType'] ?? ''));
            $category = trim((string) ($row['category'] ?? $row['Category'] ?? ''));
            $problem = trim((string) ($row['problem'] ?? $row['Problem'] ?? ''));

            if ($machineType === '' || $category === '' || $problem === '') {
                $skippedCount++;

                continue;
            }

            $machineProblem = MachineProblem::updateOrCreate(
                [
                    'machine_type' => $machineType,
                    'problem'      => $problem,
                    'category'     => $category,
                ],
                []
            );

            if ($machineProblem->wasRecentlyCreated) {
                $importedCount++;
            } else {
                $duplicateCount++;
            }
        }

        session()->flash('machine_problems_import_result', [
            'imported' => $importedCount,
            'duplicate' => $duplicateCount,
            'skipped' => $skippedCount,
        ]);
    }
}