<?php

namespace App\Imports;

use App\Models\Area;
use App\Models\Machine;
use App\Models\SmallPmRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Fills the Small PM row master from a file, one line per machine. Row 1 is
 * a header and is skipped:
 *   0: row             (row name; created when it does not exist yet)
 *   1: machine_number  (must exist in the machine master)
 *   2: area            (optional; needed only for a new row when more than one Small PM area exists)
 *   3: sort_order      (optional; sets the row order, new rows default to last)
 *
 * Same effect as ticking the machine on the row's Edit page: the machine
 * moves out of any other row. Machines not in the file are left alone.
 */
class SmallPmRowImport implements ToCollection
{
    /** @var array{rows_created: int, assigned: int, moved: int, unchanged: int, skipped: int, errors: list<string>} */
    public array $result = ['rows_created' => 0, 'assigned' => 0, 'moved' => 0, 'unchanged' => 0, 'skipped' => 0, 'errors' => []];

    private const MAX_ERRORS = 20;

    public function collection(Collection $lines)
    {
        $areas = Area::active()->whereIn('name', config('small_pm.areas', []))->get()->keyBy(fn (Area $a) => mb_strtolower($a->name));
        $rows = SmallPmRow::with('area')->get()->keyBy(fn (SmallPmRow $r) => mb_strtolower(trim($r->name)));
        $nextOrder = ((int) SmallPmRow::max('sort_order')) + 1;

        DB::transaction(function () use ($lines, $areas, &$rows, &$nextOrder) {
            foreach ($lines->skip(1)->values() as $i => $line) {
                $lineNo = $i + 2;
                $rowName = trim((string) ($line[0] ?? ''));
                $machineNumber = trim((string) ($line[1] ?? ''));
                $areaName = trim((string) ($line[2] ?? ''));
                $order = trim((string) ($line[3] ?? ''));

                if ($rowName === '' && $machineNumber === '' && $areaName === '' && $order === '') {
                    continue;
                }

                if ($rowName === '' || $machineNumber === '') {
                    $this->skip($lineNo, 'the row and machine_number columns are required.');

                    continue;
                }

                if ($order !== '' && ! ctype_digit($order)) {
                    $this->skip($lineNo, "sort_order \"{$order}\" harus angka.");

                    continue;
                }

                $area = null;
                if ($areaName !== '') {
                    $area = $areas->get(mb_strtolower($areaName));
                    if (! $area) {
                        $this->skip($lineNo, "area \"{$areaName}\" bukan area Small PM.");

                        continue;
                    }
                }

                $row = $rows->get(mb_strtolower($rowName));

                if ($row && $area && $row->area_id !== $area->id) {
                    $this->skip($lineNo, "row {$row->name} sudah ada di area ".($row->area->name ?? '-').", bukan {$area->name}.");

                    continue;
                }

                if (! $row) {
                    $area ??= $areas->count() === 1 ? $areas->first() : null;

                    if (! $area) {
                        $this->skip($lineNo, "row baru {$rowName} perlu kolom area.");

                        continue;
                    }

                    $row = SmallPmRow::create([
                        'name' => $rowName,
                        'area_id' => $area->id,
                        'sort_order' => $order !== '' ? (int) $order : $nextOrder++,
                    ]);
                    $nextOrder = max($nextOrder, $row->sort_order + 1);
                    $row->setRelation('area', $area);
                    $rows->put(mb_strtolower($rowName), $row);
                    $this->result['rows_created']++;
                } elseif ($order !== '' && $row->sort_order !== (int) $order) {
                    $row->update(['sort_order' => (int) $order]);
                }

                $machine = Machine::where('machine_number', $machineNumber)->first(['id', 'machine_number', 'area', 'small_pm_row_id']);

                if (! $machine) {
                    $this->skip($lineNo, "mesin {$machineNumber} tidak ada di master mesin.");

                    continue;
                }

                if ($machine->area !== $row->area?->name) {
                    $this->skip($lineNo, "mesin {$machineNumber} ada di area {$machine->area}, row {$row->name} di area ".($row->area->name ?? '-').'.');

                    continue;
                }

                $current = $machine->small_pm_row_id === null ? null : (int) $machine->small_pm_row_id;

                if ($current === $row->id) {
                    $this->result['unchanged']++;

                    continue;
                }

                // Query update: small_pm_row_id is not fillable, same as group_id.
                Machine::whereKey($machine->id)->update(['small_pm_row_id' => $row->id]);
                $this->result[$current === null ? 'assigned' : 'moved']++;
            }
        });
    }

    private function skip(int $lineNo, string $reason): void
    {
        $this->result['skipped']++;

        if (count($this->result['errors']) < self::MAX_ERRORS) {
            $this->result['errors'][] = "Baris {$lineNo}: {$reason}";
        }
    }
}
