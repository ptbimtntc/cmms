<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One-time correction. Every row written before this deploy had its
 * now()-derived timestamps stored while config('app.timezone') was still
 * 'UTC', so they are 7 hours behind the actual Jakarta wall-clock time.
 * Now that the app runs on Asia/Jakarta, those stored values are read
 * back as WIB and therefore render 7 hours early (this is what made the
 * Oil Audit times look wrong).
 *
 * This migration adds 7 hours to those columns so historical rows line
 * up with the real event time. down() removes it again.
 *
 * Only now()-derived columns are touched. User-entered wall-clock values
 * — every `date` column (plan_date, due_date, action_date, install_date,
 * next_pm, actual_date, ...) and pm_work_sessions.start_time/end_time —
 * were already entered in local time and are deliberately left alone.
 * Framework tables (jobs, sessions, cache, password_reset_tokens) are
 * transient and excluded.
 *
 * The shift is done in PHP (chunked) so it behaves identically on MySQL
 * and on the SQLite test database.
 */
return new class extends Migration
{
    private const OFFSET_HOURS = 7;

    /**
     * @var array<string, list<string>>
     */
    private array $columns = [
        'users' => ['created_at', 'updated_at', 'email_verified_at'],
        'machines' => ['created_at', 'updated_at'],
        'machine_problems' => ['created_at', 'updated_at'],
        'machine_problem_findings' => ['created_at', 'updated_at'],
        'machine_measurements' => ['created_at', 'updated_at'],
        'machine_checklists' => ['created_at', 'updated_at'],
        'spareparts' => ['created_at', 'updated_at'],
        'pm_schedules' => ['created_at', 'updated_at'],
        'pm_measurements' => ['created_at', 'updated_at'],
        'pm_problems' => ['created_at', 'updated_at'],
        'pm_spareparts' => ['created_at', 'updated_at'],
        'pm_checklists' => ['created_at', 'updated_at'],
        'pm_work_sessions' => ['created_at', 'updated_at'],
        'groups' => ['created_at', 'updated_at'],
        'greasings' => ['created_at', 'updated_at'],
        'greasing_findings' => ['created_at', 'updated_at'],
        'oil_audits' => ['created_at', 'updated_at', 'audited_at'],
        'oil_audit_follow_ups' => ['created_at', 'updated_at', 'actioned_at'],
        'oil_audit_follow_up_problems' => ['created_at', 'updated_at'],
        'oil_audit_follow_up_findings' => ['created_at', 'updated_at'],
    ];

    public function up(): void
    {
        $this->shift(self::OFFSET_HOURS);
    }

    public function down(): void
    {
        $this->shift(-self::OFFSET_HOURS);
    }

    private function shift(int $hours): void
    {
        foreach ($this->columns as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $present = array_values(array_filter(
                $columns,
                fn (string $column) => Schema::hasColumn($table, $column)
            ));

            if ($present === []) {
                continue;
            }

            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $present, $hours) {
                foreach ($rows as $row) {
                    $update = [];

                    foreach ($present as $column) {
                        if (blank($row->{$column})) {
                            continue;
                        }

                        $update[$column] = Carbon::parse($row->{$column})
                            ->addHours($hours)
                            ->format('Y-m-d H:i:s');
                    }

                    if ($update !== []) {
                        DB::table($table)->where('id', $row->id)->update($update);
                    }
                }
            });
        }
    }
};
