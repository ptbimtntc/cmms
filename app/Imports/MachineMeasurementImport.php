<?php

namespace App\Imports;

use App\Models\MachineMeasurement;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class MachineMeasurementImport implements ToCollection, WithHeadingRow
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
            $measurementItem = trim((string) ($row['measurement_item'] ?? $row['measurement'] ?? $row['Measurement Item'] ?? ''));

            if ($machineType === '' || $measurementItem === '') {
                $skippedCount++;

                continue;
            }

            $measurement = MachineMeasurement::updateOrCreate(
                [
                    'machine_type'     => $machineType,
                    'measurement_item' => $measurementItem,
                ],
                [
                    'standard' => trim((string) ($row['standard'] ?? $row['Standard'] ?? '')) ?: null,
                    'unit'     => trim((string) ($row['unit'] ?? $row['Unit'] ?? '')) ?: null,
                ]
            );

            if ($measurement->wasRecentlyCreated) {
                $importedCount++;
            } else {
                $duplicateCount++;
            }
        }

        session()->flash('machine_measurements_import_result', [
            'imported' => $importedCount,
            'duplicate' => $duplicateCount,
            'skipped' => $skippedCount,
        ]);
    }
}