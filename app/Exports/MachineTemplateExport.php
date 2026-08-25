<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class MachineTemplateExport implements FromArray
{
    public function array(): array
    {
        return [

            [
                'machine_number',
                'machine_type',
                'area',
                'status',
                'pm_cycle_value',
                'pm_cycle_unit',
                'group',
            ],

            [
                'MC001',
                'NDE',
                'WWD',
                'ACTIVE',
                104,
                'WEEK',
                '',
            ],

        ];
    }
}
