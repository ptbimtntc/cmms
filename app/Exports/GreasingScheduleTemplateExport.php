<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class GreasingScheduleTemplateExport implements FromArray
{
    public function array(): array
    {
        return [

            [
                'order_number',
                'plan_date',
                'group',
                'cycle',
                'pic',
                'remarks',
            ],

            [
                'WO-0001',
                '2026-08-01',
                'Line 1',
                '4W',
                'Budi',
                'Optional remarks',
            ],

        ];
    }
}
