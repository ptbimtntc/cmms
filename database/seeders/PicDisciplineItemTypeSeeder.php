<?php

namespace Database\Seeders;

use App\Models\PicDisciplineItemType;
use Illuminate\Database\Seeder;

/**
 * Master list of PIC administration items tracked for the Kedisiplinan PIC
 * report. Only FILL_PM is auto-sourced (see PicDisciplineEntryGenerator);
 * every other item is filled in manually by ADMIN/KOORDINATOR via the
 * /pic-discipline/entries grid. grace_days (the H+N window that still counts
 * as on time) is admin-configurable afterwards without a code change —
 * these are just sensible starting defaults.
 */
class PicDisciplineItemTypeSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['code' => 'PASANG_TAG', 'name' => 'Pasang Tag', 'is_auto' => false, 'grace_days' => 3, 'sort_order' => 1],
            ['code' => PicDisciplineItemType::CODE_FILL_PM, 'name' => 'Fill PM', 'is_auto' => true, 'grace_days' => 5, 'sort_order' => 2],
            ['code' => 'CHECKLIST_KERTAS', 'name' => 'Checklist Kertas', 'is_auto' => false, 'grace_days' => 3, 'sort_order' => 3],
            ['code' => 'WO_PROCESS', 'name' => 'WO Process', 'is_auto' => false, 'grace_days' => 3, 'sort_order' => 4],
            ['code' => 'CREATE_WO_REP', 'name' => 'Create WO Rep', 'is_auto' => false, 'grace_days' => 3, 'sort_order' => 5],
            ['code' => 'CLOSING_WO_SAP', 'name' => 'Closing WO SAP', 'is_auto' => false, 'grace_days' => 3, 'sort_order' => 6],
        ];

        foreach ($items as $item) {
            PicDisciplineItemType::updateOrCreate(
                ['code' => $item['code']],
                $item + ['is_active' => true]
            );
        }
    }
}
