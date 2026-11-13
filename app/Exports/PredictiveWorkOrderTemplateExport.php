<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Template for Action Predictive > Import WO. Area and Machine Type are not
 * columns: they come from the Machine Master by Machine Number. Due Date is not
 * a column either: it is always calculated as Date + 14 days. Date accepts
 * YYYY-MM-DD or DD-MM-YYYY.
 */
class PredictiveWorkOrderTemplateExport implements FromArray, ShouldAutoSize
{
    public function array(): array
    {
        return [
            ['Order Number', 'Machine Number', 'Date', 'Problem Description'],
            ['4100012345', 'WWD-001', '2026-11-10', 'High vibration on the motor side'],
            ['4100012346', 'WWD-002', '2026-11-10', 'Kebocoran angin di sambungan selang'],
        ];
    }
}
