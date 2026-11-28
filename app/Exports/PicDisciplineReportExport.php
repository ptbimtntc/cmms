<?php

namespace App\Exports;

use App\Models\PicDisciplineItemType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;

class PicDisciplineReportExport implements FromArray
{
    /**
     * @param  Collection<int, PicDisciplineItemType>  $itemTypes
     * @param  Collection<int, \App\Models\User>  $pics
     * @param  array<int, array<int, array>>  $perItem  keyed by [pic_user_id][item_type_id]
     * @param  array<int, array>  $overall  keyed by pic_user_id
     */
    public function __construct(
        private Collection $itemTypes,
        private Collection $pics,
        private array $perItem,
        private array $overall,
    ) {}

    public function array(): array
    {
        $rows = [['PIC', 'Item', 'Achieved (A)', 'Breach (B)', '%']];

        foreach ($this->pics as $pic) {
            foreach ($this->itemTypes as $itemType) {
                $cell = $this->perItem[$pic->id][$itemType->id] ?? ['achieved' => 0, 'breach' => 0, 'percentage' => 0.0];

                $rows[] = [
                    Str::title(strtolower($pic->name)),
                    $itemType->name,
                    $cell['achieved'],
                    $cell['breach'],
                    $cell['percentage'].'%',
                ];
            }

            $average = $this->overall[$pic->id] ?? ['achieved' => 0, 'breach' => 0, 'percentage' => 0.0];

            $rows[] = [
                Str::title(strtolower($pic->name)),
                'Average',
                $average['achieved'],
                $average['breach'],
                $average['percentage'].'%',
            ];
        }

        return $rows;
    }
}
