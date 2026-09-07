<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Finish" for Today's Activity — removes an activity from the monitor
     * WITHOUT touching the source module.
     *
     * This is a monitoring-only marker table. It does NOT store activities
     * (those stay 100% derived from pm_schedules / greasings / users /
     * manual_activities) and it is NOT a centralized activity table — it
     * only records "this monitoring activity was closed on this day, by
     * whom". No column is added to pm_schedules / greasings / users.
     *
     * A row here means: for `business_date`, the activity identified by
     * (`source`, `source_key`) is no longer active on the monitor. It never
     * changes the module's status / completion / dates.
     *
     * source_key: the source record id for PM / GREASING / MANUAL; the PIC
     * user id for the user-level OIL_AUDIT / OIL_AUDIT_ACTION markers.
     */
    public function up(): void
    {
        Schema::create('activity_monitor_closures', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32);
            $table->string('source_key');
            $table->date('business_date');
            $table->foreignId('pic_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('closed_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->unique(['source', 'source_key', 'business_date']);
            $table->index(['pic_user_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_monitor_closures');
    }
};
