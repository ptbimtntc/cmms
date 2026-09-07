<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manual Activity — an ad-hoc activity a koordinator/admin starts that
     * does NOT come from PM, Oil Audit, or Greasing (e.g. "Repair Conveyor",
     * "Meeting").
     *
     * Audit result: no existing table can hold a free-text activity that is
     * not tied to a PM schedule / greasing schedule / oil audit event, so a
     * minimal dedicated table is created (deliberately NOT named
     * maintenance_activities, and it does not duplicate any existing data).
     *
     * There is intentionally no status / ended_at column: exactly like the
     * other activity sources, "active" is derived — started_at is today and
     * this is the owner's most recently started activity (see
     * ActiveActivityResolver). Day-rollover close is derived too.
     */
    public function up(): void
    {
        Schema::create('manual_activities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            // Free text, optional — may be an informal label like
            // "Conveyor #03" that is not a real machines.machine_number, so
            // it is stored as a plain string with no foreign key.
            $table->string('machine_number')->nullable();

            $table->timestamp('started_at');

            $table->timestamps();

            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_activities');
    }
};
