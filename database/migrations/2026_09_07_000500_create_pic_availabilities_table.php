<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PIC availability — a per-day "this PIC is not available today" marker
     * set by ADMIN / KOORDINATOR from the Activity Control Panel.
     *
     * It is a small, dedicated table (NOT maintenance_activities and NOT an
     * activity store). An INACTIVE PIC is not an activity: no card, not in
     * the donut, never a "completed" activity. It changes NOTHING in
     * pm_schedules / greasings / users / manual_activities.
     *
     * Scoped to a single `date`; a row only affects that business day.
     * One row per (user_id, date).
     */
    public function up(): void
    {
        Schema::create('pic_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason', 40);
            $table->string('notes')->nullable();
            $table->foreignId('set_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pic_availabilities');
    }
};
