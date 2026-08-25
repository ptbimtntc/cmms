<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->string('gearbox_problem')->default('NO')->after('wo_zsbp');
        });

        // Backfill existing WWD PM schedules: YES when a recorded problem's
        // catalog text mentions Mainshaft or Innershaft.
        DB::table('pm_schedules')
            ->where('area', 'WWD')
            ->whereIn('id', function ($query) {
                $query->select('pm_problems.pm_schedule_id')
                    ->from('pm_problems')
                    ->join('machine_problems', 'machine_problems.id', '=', 'pm_problems.machine_problem_id')
                    ->where(function ($q) {
                        $q->whereRaw('LOWER(machine_problems.problem) LIKE ?', ['%mainshaft%'])
                            ->orWhereRaw('LOWER(machine_problems.problem) LIKE ?', ['%innershaft%']);
                    });
            })
            ->update(['gearbox_problem' => 'YES']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->dropColumn('gearbox_problem');
        });
    }
};
