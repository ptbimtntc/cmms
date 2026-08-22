<?php

namespace App\Imports;

use App\Models\Sparepart;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Facades\Excel;

class SparepartsImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        $importedCount = 0;
        $duplicateCount = 0;
        $skippedCount = 0;

        foreach ($rows->skip(1) as $row) {
            $row = $row instanceof \Illuminate\Support\Collection
                ? $row->toArray()
                : (array) $row;

            $materialNumber = trim((string) ($row[0] ?? ''));

            if ($materialNumber === '') {
                $skippedCount++;

                continue;
            }

            $status = strtoupper(trim((string) ($row[9] ?? 'ACTIVE')));

            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                $status = 'ACTIVE';
            }

            $stock = is_numeric($row[4] ?? null) ? (float) $row[4] : 0;
            $rop = is_numeric($row[6] ?? null) ? (int) $row[6] : 0;
            $price = is_numeric($row[8] ?? null) ? (float) $row[8] : 0;

            $sparepart = Sparepart::updateOrCreate(
                [
                    'material_number' => $materialNumber,
                ],
                [
                    'location'     => trim((string) ($row[1] ?? '')) ?: null,
                    'description'  => trim((string) ($row[2] ?? '')) ?: null,
                    'remarks'      => trim((string) ($row[3] ?? '')) ?: null,
                    'stock'        => $stock,
                    'unit'         => trim((string) ($row[5] ?? '')) ?: null,
                    'rop'          => $rop,
                    'mrp_type'     => trim((string) ($row[7] ?? '')) ?: null,
                    'price'        => $price,
                    'status'       => $status,
                    'machine_type' => trim((string) ($row[10] ?? '')) ?: null,
                    'segment'      => trim((string) ($row[11] ?? '')) ?: null,
                    'pdt'          => trim((string) ($row[12] ?? '')) ?: null,
                ]
            );

            if ($sparepart->wasRecentlyCreated) {
                $importedCount++;
            } else {
                $duplicateCount++;
            }
        }

        session()->flash('spareparts_import_result', [
            'imported' => $importedCount,
            'duplicate' => $duplicateCount,
            'skipped' => $skippedCount,
        ]);
    }
}