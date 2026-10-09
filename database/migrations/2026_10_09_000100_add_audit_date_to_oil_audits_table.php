<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One Oil Audit per machine per business (calendar) day, enforced by the
 * database. `audited_at` is stored by Laravel as a wall-clock string in the
 * app timezone (Asia/Jakarta), so the business date is simply its date part
 * — no timezone conversion is applied here or anywhere else.
 *
 * `audit_date` is nullable on purpose: legacy data may already contain
 * several audits for one machine on one day. Historical rows are never
 * deleted or altered; the earliest audit (lowest id) of each machine/day
 * owns the date, and any later legacy duplicate keeps audit_date NULL so
 * the unique index (which permits multiple NULLs) can still be created.
 * Every audit created from now on always has audit_date set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oil_audits', function (Blueprint $table) {
            $table->date('audit_date')->nullable()->after('audited_at');
        });

        $claimed = [];

        DB::table('oil_audits')
            ->orderBy('id')
            ->select(['id', 'machine_id', 'audited_at'])
            ->chunkById(500, function ($audits) use (&$claimed) {
                foreach ($audits as $audit) {
                    $date = substr((string) $audit->audited_at, 0, 10);
                    $key = $audit->machine_id.'|'.$date;

                    if (isset($claimed[$key])) {
                        continue;
                    }

                    $claimed[$key] = true;

                    DB::table('oil_audits')->where('id', $audit->id)->update(['audit_date' => $date]);
                }
            });

        Schema::table('oil_audits', function (Blueprint $table) {
            $table->unique(['machine_id', 'audit_date'], 'oil_audits_machine_audit_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('oil_audits', function (Blueprint $table) {
            $table->dropUnique('oil_audits_machine_audit_date_unique');
            $table->dropColumn('audit_date');
        });
    }
};
