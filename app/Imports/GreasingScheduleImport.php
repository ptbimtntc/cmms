<?php

namespace App\Imports;

use App\Models\Greasing;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * CSV columns (row 1 is a header row and is skipped):
 *   0: order_number
 *   1: plan_date   (Y-m-d)
 *   2: group       (must match an existing Group.name)
 *   3: cycle       (free text, e.g. "4W", "16W", "52W")
 *   4: pic         (must match an existing User.name with role PIC WWD/PIC BUL)
 *   5: remarks     (optional)
 *
 * Day/Week/Month, Due Date, and Status are never read from the file —
 * they are either not applicable to Greasing or always computed server-side.
 *
 * Duplicate rule (group_id + plan_date + order_number), mirroring
 * PMScheduleImport's (machine_number + plan_date + order_number) check.
 */
class GreasingScheduleImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        $importedCount = 0;
        $duplicateCount = 0;
        $skippedCount = 0;

        foreach ($rows->skip(1) as $row) {
            $orderNumber = trim((string) ($row[0] ?? ''));
            $planDateRaw = trim((string) ($row[1] ?? ''));
            $groupName = trim((string) ($row[2] ?? ''));
            $cycle = trim((string) ($row[3] ?? ''));
            $picName = trim((string) ($row[4] ?? ''));
            $remarks = trim((string) ($row[5] ?? ''));

            // Skip a fully blank line (e.g. trailing newline in the CSV).
            if ($orderNumber === '' && $planDateRaw === '' && $groupName === '' && $cycle === '' && $picName === '') {
                continue;
            }

            if ($orderNumber === '') {
                $skippedCount++;

                continue;
            }

            if ($cycle === '' || ! preg_match('/^\d+W$/i', $cycle)) {
                $skippedCount++;

                continue;
            }

            try {
                $planDate = Carbon::createFromFormat('Y-m-d', $planDateRaw)->startOfDay();
            } catch (\Throwable $e) {
                $skippedCount++;

                continue;
            }

            $group = Group::where('name', $groupName)->first();

            if (! $group) {
                $skippedCount++;

                continue;
            }

            $picUser = User::where('name', $picName)
                ->whereIn('role', [User::ROLE_PIC_WWD, User::ROLE_PIC_BUL])
                ->first();

            if (! $picUser) {
                $skippedCount++;

                continue;
            }

            $exists = Greasing::where('group_id', $group->id)
                ->whereDate('plan_date', $planDate)
                ->where('order_number', $orderNumber)
                ->exists();

            if ($exists) {
                $duplicateCount++;

                continue;
            }

            Greasing::create([
                'group_id' => $group->id,
                'order_number' => $orderNumber,
                'cycle' => strtoupper($cycle),
                'plan_date' => $planDate->format('Y-m-d'),
                'due_date' => Greasing::calculateDueDate($planDate)->format('Y-m-d'),
                'pic' => $picUser->name,
                'action_date' => null,
                'status' => 'OPEN',
                'remarks' => $remarks !== '' ? $remarks : null,
            ]);

            $importedCount++;
        }

        session()->flash('greasing_import_result', [
            'imported' => $importedCount,
            'duplicate' => $duplicateCount,
            'skipped' => $skippedCount,
        ]);
    }
}
