<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

/** One line per machine; a row with several machines repeats its name. */
class SmallPmRowTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            ['row', 'machine_number', 'area', 'sort_order'],
            ['Row 01', '20043', 'WWD', '1'],
            ['Row 01', '20044', 'WWD', '1'],
            ['Row 02', '20045', 'WWD', '2'],
        ];
    }
}
