<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class SmallPmScheduleTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            ['order_number', 'plan_date', 'row', 'cycle', 'pic', 'remarks'],
            ['', '2026-11-09', 'Row 01', '26W', '', 'order_number, pic, and remarks may be empty'],
            ['', '2026-11-09', 'Row 02', '26W', '', ''],
        ];
    }
}
