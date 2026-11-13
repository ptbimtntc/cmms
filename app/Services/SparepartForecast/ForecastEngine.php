<?php

namespace App\Services\SparepartForecast;

use Illuminate\Support\Carbon;

/**
 * Deterministic, database-free calculation engine for the AI Sparepart
 * Analyst. Same input => same output; every number the dashboard shows is
 * produced here (an LLM may later explain results but never changes them).
 *
 * Method (per machine, per sparepart) — "recent-window usage frequency":
 *  - Look at the machine's last WINDOW finished PMs (m of them).
 *  - p        = k / m, where k = PMs in the window that used the part.
 *  - avg      = total qty / k  (qty on a PM that did use it).
 *  - expected = upcoming PMs × p × avg.
 *  - LIKELY (p >= 0.5): recommended qty = ceil(expected). POSSIBLE
 *    (0.25 <= p < 0.5) and RARE (< 0.25) are shown but NOT recommended:
 *    a part used once in a while is not presumed to be needed on every PM.
 *
 * Input history per machine is a chronologically ascending list of
 *   ['pm_id' => int, 'date' => 'Y-m-d', 'usage' => [sparepart_id => qty]]
 * A machine with too little history gets NO numbers at all.
 */
class ForecastEngine
{
    public const VERSION = 'machine-frequency-1.0';

    public const WINDOW = 12;             // most recent finished PMs considered
    public const MIN_PM = 3;              // finished PMs a machine needs
    public const MIN_PM_WITH_USAGE = 2;   // ... of which must have usage records
    public const LIKELY = 0.5;
    public const POSSIBLE = 0.25;
    public const TREND_DELTA = 0.25;      // usage-rate change that counts as a trend
    public const TEST_FRACTION = 0.2;     // newest share of each machine held out
    public const MIN_TEST_PM = 10;        // evaluation needs at least this many test PMs ...
    public const MIN_TEST_MACHINES = 3;   // ... across at least this many machines

    public static function parameters(): array
    {
        return [
            'window' => self::WINDOW,
            'min_pm' => self::MIN_PM,
            'min_pm_with_usage' => self::MIN_PM_WITH_USAGE,
            'likely_probability' => self::LIKELY,
            'possible_probability' => self::POSSIBLE,
            'trend_delta' => self::TREND_DELTA,
            'test_fraction' => self::TEST_FRACTION,
            'min_test_pm' => self::MIN_TEST_PM,
            'min_test_machines' => self::MIN_TEST_MACHINES,
        ];
    }

    /**
     * @param  list<array{pm_id:int,date:string,usage:array<int,int>}>  $history  ascending by date
     * @return array{status:string,confidence:?string,note:?string,pm_count:int,pm_with_usage:int,items:array<int,array>}
     */
    public function forecastMachine(array $history, int $upcomingPms): array
    {
        $total = count($history);
        $withUsage = count(array_filter($history, fn ($pm) => $pm['usage'] !== []));

        if ($total < self::MIN_PM) {
            return $this->insufficient($total, $withUsage, "Insufficient Historical Data: {$total} PM selesai (minimal ".self::MIN_PM.').');
        }

        if ($withUsage < self::MIN_PM_WITH_USAGE) {
            return $this->insufficient($total, $withUsage, "Insufficient Historical Data: hanya {$withUsage} PM yang memiliki catatan penggunaan sparepart (minimal ".self::MIN_PM_WITH_USAGE.').');
        }

        $window = array_slice($history, -self::WINDOW);
        $m = count($window);

        // Per sparepart: the window positions it was used at.
        $byPart = [];
        foreach ($window as $pos => $pm) {
            foreach ($pm['usage'] as $partId => $qty) {
                $byPart[$partId][] = ['pos' => $pos, 'date' => $pm['date'], 'qty' => $qty];
            }
        }

        $items = [];
        foreach ($byPart as $partId => $uses) {
            $items[$partId] = $this->item($uses, $m, $upcomingPms);
        }

        return [
            'status' => 'SUFFICIENT',
            'confidence' => $m >= 8 ? 'HIGH' : ($m >= 5 ? 'MEDIUM' : 'LOW'),
            'note' => null,
            'pm_count' => $total,
            'pm_with_usage' => $withUsage,
            'items' => $items,
        ];
    }

    /**
     * Temporal hold-out evaluation. For every machine the OLDEST ~80% of its
     * finished PMs train the model and the NEWEST ~20% (at least 1) are the
     * test set, so nothing from the future leaks into training. Prediction =
     * expected qty over the number of test PMs; actual = qty really used on
     * them. Compared per (machine, sparepart).
     *
     * @param  array<int, list<array>>  $historyByMachine  machine_id => history
     */
    public function evaluate(array $historyByMachine): array
    {
        $trainPms = $testPms = $trainRows = $testRows = 0;
        $trainMachines = $testMachines = 0;
        $trainParts = [];
        $trainDates = [];
        $testDates = [];
        $abs = $err = $actualSum = 0.0;
        $compared = 0;

        foreach ($historyByMachine as $history) {
            $n = count($history);
            $t = max(1, (int) floor($n * self::TEST_FRACTION));
            $train = array_slice($history, 0, $n - $t);
            $test = array_slice($history, $n - $t);

            if ($n < self::MIN_PM + 1) {
                continue;
            }

            $forecast = $this->forecastMachine($train, $t);
            if ($forecast['status'] !== 'SUFFICIENT') {
                continue;
            }

            $trainMachines++;
            $testMachines++;
            $trainPms += count($train);
            $testPms += count($test);
            array_push($trainDates, $train[0]['date'], end($train)['date']);
            array_push($testDates, $test[0]['date'], end($test)['date']);

            foreach ($train as $pm) {
                $trainRows += count($pm['usage']);
                foreach ($pm['usage'] as $partId => $_) {
                    $trainParts[$partId] = true;
                }
            }

            $actual = [];
            foreach ($test as $pm) {
                foreach ($pm['usage'] as $partId => $qty) {
                    $actual[$partId] = ($actual[$partId] ?? 0) + $qty;
                    $testRows++;
                }
            }

            foreach (array_unique(array_merge(array_keys($forecast['items']), array_keys($actual))) as $partId) {
                $predicted = $forecast['items'][$partId]['expected_qty'] ?? 0.0;
                $real = $actual[$partId] ?? 0;
                $abs += abs($predicted - $real);
                $err += $predicted - $real;
                $actualSum += $real;
                $compared++;
            }
        }

        $meta = [
            'split_method' => 'Temporal hold-out per machine: '.(int) ((1 - self::TEST_FRACTION) * 100).'% oldest PMs for training, '
                .(int) (self::TEST_FRACTION * 100).'% newest PMs (at least 1) for testing',
            'train_from' => $trainDates ? min($trainDates) : null,
            'train_to' => $trainDates ? max($trainDates) : null,
            'test_from' => $testDates ? min($testDates) : null,
            'test_to' => $testDates ? max($testDates) : null,
            'train_usage_rows' => $trainRows,
            'train_pm_count' => $trainPms,
            'train_machine_count' => $trainMachines,
            'train_sparepart_count' => count($trainParts),
            'test_usage_rows' => $testRows,
            'test_pm_count' => $testPms,
            'test_machine_count' => $testMachines,
            'compared_items' => $compared,
            'mae' => null,
            'wape' => null,
            'bias' => null,
        ];

        $notes = [];
        if ($testPms < self::MIN_TEST_PM) {
            $notes[] = "Data testing {$testPms} PM (minimal ".self::MIN_TEST_PM.').';
        }
        if ($testMachines < self::MIN_TEST_MACHINES) {
            $notes[] = "Data testing hanya dari {$testMachines} mesin (minimal ".self::MIN_TEST_MACHINES.').';
        }
        if ($actualSum <= 0 && $notes === []) {
            $notes[] = 'No sparepart usage in the testing data.';
        }

        if ($notes !== []) {
            return $meta + ['status' => 'NOT_AVAILABLE', 'notes' => $notes];
        }

        return array_merge($meta, [
            'status' => 'AVAILABLE',
            'mae' => round($abs / $compared, 4),
            'wape' => round($abs / $actualSum, 4),
            'bias' => round($err / $actualSum, 4),
            'notes' => ['A positive bias means the forecast runs higher than reality (over-forecast).'],
        ]);
    }

    /** @param list<array{pos:int,date:string,qty:int}> $uses */
    private function item(array $uses, int $m, int $upcoming): array
    {
        $k = count($uses);
        $totalQty = array_sum(array_column($uses, 'qty'));
        $avg = $totalQty / $k;
        $p = $k / $m;
        $expected = $upcoming * $p * $avg;
        $level = $p >= self::LIKELY ? 'LIKELY' : ($p >= self::POSSIBLE ? 'POSSIBLE' : 'RARE');

        // Mean gap (days) between consecutive PMs that used the part.
        $interval = null;
        if ($k >= 2) {
            $gaps = [];
            for ($i = 1; $i < $k; $i++) {
                $gaps[] = Carbon::parse($uses[$i - 1]['date'])->diffInDays(Carbon::parse($uses[$i]['date']));
            }
            $interval = round(array_sum($gaps) / count($gaps), 1);
        }

        // Trend: usage rate in the older half vs the newer half of the window.
        $trend = 'UNKNOWN';
        if ($m >= 6) {
            $half = intdiv($m, 2);
            $old = count(array_filter($uses, fn ($u) => $u['pos'] < $half)) / $half;
            $new = count(array_filter($uses, fn ($u) => $u['pos'] >= $half)) / ($m - $half);
            $delta = $new - $old;
            $trend = $delta >= self::TREND_DELTA ? 'INCREASING' : ($delta <= -self::TREND_DELTA ? 'DECREASING' : 'STABLE');
        }

        $qtys = array_column($uses, 'qty');
        $cv = $k >= 2 && $avg > 0
            ? round(sqrt(array_sum(array_map(fn ($q) => ($q - $avg) ** 2, $qtys)) / $k) / $avg, 2)
            : null;

        return [
            'pm_considered' => $m,
            'usage_pm_count' => $k,
            'total_qty' => (int) $totalQty,
            'avg_qty' => round($avg, 2),
            'mean_interval_days' => $interval,
            'last_used_date' => end($uses)['date'],
            'trend' => $trend,
            'probability' => round($p, 4),
            'upcoming_pm_count' => $upcoming,
            'expected_qty' => round($expected, 2),
            'recommended_qty' => $level === 'LIKELY' ? (int) ceil(round($expected, 6)) : 0,
            'level' => $level,
            'confidence' => ($m >= 8 && $k >= 3) ? 'HIGH' : (($m >= 5 && $k >= 2) ? 'MEDIUM' : 'LOW'),
            'basis' => [
                'window' => $m,
                'qty_cv' => $cv,
                'lines' => [
                    "Dipakai pada {$k} dari {$m} PM terakhir (probabilitas ".round($p * 100)."%).",
                    'Average qty when used: '.round($avg, 2).".",
                    "Estimasi = {$upcoming} PM terjadwal × ".round($p, 2).' × '.round($avg, 2).' = '.round($expected, 2).'.',
                ],
            ],
        ];
    }

    private function insufficient(int $total, int $withUsage, string $note): array
    {
        return [
            'status' => 'INSUFFICIENT_DATA',
            'confidence' => null,
            'note' => $note,
            'pm_count' => $total,
            'pm_with_usage' => $withUsage,
            'items' => [],
        ];
    }
}
