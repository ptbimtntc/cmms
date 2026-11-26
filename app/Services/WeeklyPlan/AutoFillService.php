<?php

namespace App\Services\WeeklyPlan;

use App\Models\User;
use App\Models\WeeklyPmPlan;

/**
 * Places machines from the pool onto Monday..Friday, in one of two modes:
 *  - "due_this_week" (default): only machines due by Friday or carried over
 *    from last week.
 *  - "nearest": the whole pool, so a week with few machines due still gets
 *    the day limits filled, starting with the soonest due date.
 *
 * Rules, in order:
 *  1. Machines already placed by hand stay where they are.
 *  2. Most urgent first: carried over from last week, then overdue, then by
 *     due date. Machines of the same family are kept together so one day
 *     handles one or two types, the way the coordinator plans by hand.
 *  3. Each day first gets the machine family mix the coordinator set for it
 *     (e.g. NDE 2 + SHUIXING 2; see MachineFamily). Room left under the day's limit (0..6) is
 *     then filled Monday first with the remaining machines. Machines that do
 *     not fit stay unscheduled.
 *  4. PIC: the one already set on the PM Schedule if available that day,
 *     otherwise the available PIC with the fewest machines this week.
 *     PICs with a PicAvailability entry (Cuti, Sakit, ...) on a day are
 *     never picked for that day.
 */
class AutoFillService
{
    public const MODE_DUE_THIS_WEEK = 'due_this_week';

    public const MODE_NEAREST = 'nearest';

    public function __construct(
        private readonly PlanBoardService $board,
        private readonly PlanSaveService $saver,
    ) {}

    /** @return array{placed: int, left: int} */
    public function fill(WeeklyPmPlan $plan, User $user, string $mode = self::MODE_DUE_THIS_WEEK): array
    {
        $state = $this->board->state($plan);
        $days = array_column($state['days'], 'date');
        $unavailable = (array) $state['unavailable'];

        $placed = collect($state['items'])->filter(fn ($i) => $i['pm_schedule_id'] !== null)->values();
        $todo = collect($state['pool'])
            ->filter(fn ($c) => $mode === self::MODE_NEAREST || $c['carried_over'] || $c['due_this_week'])
            ->values();

        if ($todo->isEmpty()) {
            return ['placed' => 0, 'left' => 0];
        }

        $rank = fn (array $c) => [$c['carried_over'] ? 0 : 1, $c['overdue'] ? 0 : 1, $c['due_date'] ?? $c['plan_date'] ?? '9999-12-31', $c['machine_number']];

        // Order types by their most urgent machine, then machines inside a type by urgency.
        $ordered = $todo
            ->groupBy('type_family')
            ->map(fn ($group) => $group->sortBy($rank)->values())
            ->sortBy(fn ($group) => $rank($group->first()))
            ->flatten(1)
            ->values();

        $limits = array_combine($days, array_map(fn ($d) => $plan->dayLimit($d), $days));
        $dayCount = array_fill_keys($days, 0);
        $typeCount = [];
        $weekLoad = array_fill_keys($state['pics'], 0);
        $dayLoad = [];

        foreach ($placed as $item) {
            $dayCount[$item['day']]++;
            $typeCount[$item['day']][$item['type_family']] = ($typeCount[$item['day']][$item['type_family']] ?? 0) + 1;

            if ($item['pic'] !== null && isset($weekLoad[$item['pic']])) {
                $weekLoad[$item['pic']]++;
                $dayLoad[$item['day']][$item['pic']] = ($dayLoad[$item['day']][$item['pic']] ?? 0) + 1;
            }
        }

        $queue = $ordered->all();
        $taken = [];
        $assigned = []; // [card, day]

        $take = function (int $i, string $day) use (&$queue, &$taken, &$assigned, &$dayCount, &$typeCount) {
            $card = $queue[$i];
            $taken[$i] = true;
            $assigned[] = [$card, $day];
            $dayCount[$day]++;
            $typeCount[$day][$card['type_family']] = ($typeCount[$day][$card['type_family']] ?? 0) + 1;
        };

        // Pass 1: the type mix the coordinator set per day, most urgent machines of that type first.
        foreach ($days as $day) {
            foreach ($plan->dayTypes($day) as $rule) {
                $need = $rule['count'] - ($typeCount[$day][$rule['type']] ?? 0);

                foreach ($queue as $i => $card) {
                    if ($need <= 0 || $dayCount[$day] >= $limits[$day]) {
                        break;
                    }

                    if (! isset($taken[$i]) && $card['type_family'] === $rule['type']) {
                        $take($i, $day);
                        $need--;
                    }
                }
            }
        }

        // Pass 2: whatever room is left under each day's limit, Monday first.
        foreach ($queue as $i => $card) {
            if (isset($taken[$i])) {
                continue;
            }

            $day = collect($days)->first(fn ($d) => $dayCount[$d] < $limits[$d]);

            if ($day === null) {
                break; // every day is at its limit
            }

            $take($i, $day);
        }

        // PIC per placed machine, day by day so the weekly load stays even.
        usort($assigned, fn ($a, $b) => array_search($a[1], $days, true) <=> array_search($b[1], $days, true));
        $new = [];

        foreach ($assigned as [$card, $day]) {
            $available = array_values(array_filter(
                $state['pics'],
                fn ($pic) => ! isset($unavailable[$day][$pic])
            ));

            $pic = null;

            if ($card['pic'] !== null && in_array($card['pic'], $available, true)) {
                $pic = $card['pic'];
            } elseif ($available !== []) {
                usort($available, fn ($a, $b) => [$weekLoad[$a], $dayLoad[$day][$a] ?? 0, $a] <=> [$weekLoad[$b], $dayLoad[$day][$b] ?? 0, $b]);
                $pic = $available[0];
            }

            if ($pic !== null && isset($weekLoad[$pic])) {
                $weekLoad[$pic]++;
                $dayLoad[$day][$pic] = ($dayLoad[$day][$pic] ?? 0) + 1;
            }

            $new[] = [
                'pm_schedule_id' => $card['pm_schedule_id'],
                'day' => $day,
                'pic' => $pic,
                'construction_note' => $card['construction_note'],
            ];
        }

        $rows = $placed->map(fn ($i) => [
            'pm_schedule_id' => $i['pm_schedule_id'],
            'day' => $i['day'],
            'pic' => $i['pic'],
            'construction_note' => $i['construction_note'],
        ])->concat($new)
            // Keep each day's hand-placed machines first, new ones after, same type together.
            ->sortBy(fn ($r, $idx) => sprintf('%s-%06d', $r['day'], $idx))
            ->values()
            ->all();

        $this->saver->save($plan, $rows, $user);

        return ['placed' => count($new), 'left' => $ordered->count() - count($new)];
    }
}
