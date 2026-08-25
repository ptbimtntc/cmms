<?php

namespace App\Imports;

use App\Models\Group;
use App\Models\Machine;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class MachinesImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        $importedCount = 0;
        $duplicateCount = 0;
        $skippedCount = 0;
        $errors = [];

        // Group is looked up by name (case-insensitive, trimmed) — never
        // created automatically, and the Excel column only ever supplies a
        // name, never a group_id, so a raw Group Name can never end up in
        // machines.group_id.
        $groupsByName = Group::all()->keyBy(
            fn (Group $group) => mb_strtolower(trim($group->name))
        );

        foreach ($rows->skip(1) as $index => $row) {
            $rowNumber = $index + 1;

            $machineNumber = trim($row[0] ?? '');

            // skip empty row
            if (! $machineNumber) {
                $skippedCount++;

                continue;
            }

            $machineType = trim($row[1] ?? '');
            $area = trim($row[2] ?? '');
            $status = strtoupper(trim($row[3] ?? 'ACTIVE'));

            $pmCycleValue = $row[4] ?? null;
            $pmCycleUnit = strtoupper(trim($row[5] ?? ''));

            $groupName = trim((string) ($row[6] ?? ''));

            // validasi status
            if (! in_array($status, ['ACTIVE', 'INACTIVE'])) {
                $status = 'ACTIVE';
            }

            // Validasi PM Cycle Value
            if (! is_numeric($pmCycleValue)) {
                $pmCycleValue = null;
            }

            // Validasi PM Cycle Unit
            $allowedCycleUnit = [
                'DAY',
                'WEEK',
                'MONTH',
                'HOUR',
            ];

            if (! in_array($pmCycleUnit, $allowedCycleUnit)) {
                $pmCycleUnit = null;
            }

            // Group is optional. Empty means "leave the machine's existing
            // group untouched" (new machine => no group at all), never
            // "remove the current group". Filled-but-unmatched means the
            // whole row is invalid and must not be written to the DB — we
            // never guess, and never create a new Group automatically.
            $groupProvided = $groupName !== '';
            $resolvedGroup = $groupProvided
                ? $groupsByName->get(mb_strtolower($groupName))
                : null;

            if ($groupProvided && ! $resolvedGroup) {
                $skippedCount++;
                $errors[] = "Row {$rowNumber}: Group \"{$groupName}\" not found.";

                continue;
            }

            $machine = Machine::where('machine_number', $machineNumber)->first();
            $isNew = $machine === null;

            $attributes = [
                'machine_type' => $machineType,
                'area' => $area,
                'status' => $status,
                'pm_cycle_value' => $pmCycleValue,
                'pm_cycle_unit' => $pmCycleUnit,
            ];

            if ($groupProvided) {
                $attributes['group_id'] = $resolvedGroup->id;
            } elseif ($isNew) {
                $attributes['group_id'] = null;
            }
            // else: existing machine + empty Group column — group_id is
            // intentionally omitted so the existing assignment is kept.

            if ($machine) {
                $machine->update($attributes);
            } else {
                $attributes['machine_number'] = $machineNumber;
                $machine = Machine::create($attributes);
            }

            if ($isNew) {
                $importedCount++;
            } else {
                $duplicateCount++;
            }
        }

        session()->flash('machines_import_result', [
            'imported' => $importedCount,
            'duplicate' => $duplicateCount,
            'skipped' => $skippedCount,
            'errors' => $errors,
        ]);
    }
}
