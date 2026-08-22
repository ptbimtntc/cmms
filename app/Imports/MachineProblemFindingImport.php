<?php

namespace App\Imports;

use App\Models\MachineProblemFinding;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Facades\Excel;

class MachineProblemFindingImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsOnFailure
{
    use SkipsFailures;

    public int $importedCount = 0;

    public int $duplicateCount = 0;

    public function model(array $row)
    {
        $category = trim((string) ($row['category'] ?? $row['Category'] ?? ''));
        $finding = trim((string) ($row['finding'] ?? $row['Finding'] ?? ''));

        if ($category === '' || $finding === '') {
            return null;
        }

        $exists = MachineProblemFinding::where('category', $category)
            ->where('finding', $finding)
            ->first();

        if ($exists) {
            $this->duplicateCount++;

            return null;
        }

        $this->importedCount++;

        return new MachineProblemFinding([
            'category' => $category,
            'finding'  => $finding,
        ]);
    }

    public function rules(): array
    {
        return [
            '*.category' => 'required',
            '*.finding'  => 'required',
        ];
    }
}
