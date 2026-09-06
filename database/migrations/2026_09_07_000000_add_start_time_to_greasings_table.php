<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Adds the single field required for "Start Activity" on a Greasing
     * schedule. Greasing has no existing time/datetime column — action_date
     * is a DATE that drives status via Greasing::resolveStatus() and must
     * not be repurposed — so one nullable timestamp holds the full start
     * date + time. Historical rows have none.
     *
     * No maintenance_activities table: "started" is simply start_time being
     * populated while the schedule is not yet completed
     * (see Greasing::isActiveActivity()).
     */
    public function up(): void
    {
        Schema::table('greasings', function (Blueprint $table) {
            $table->timestamp('start_time')->nullable()->after('action_date');
        });
    }

    public function down(): void
    {
        Schema::table('greasings', function (Blueprint $table) {
            $table->dropColumn('start_time');
        });
    }
};
