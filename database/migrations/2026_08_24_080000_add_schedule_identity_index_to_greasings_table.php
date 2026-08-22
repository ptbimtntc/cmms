<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Adds a composite index matching the actual duplicate-detection
     * identity of a Greasing schedule: group_id + plan_date + order_number
     * (mirrors PMSchedule's machine_number + plan_date + order_number
     * check). This is deliberately a plain index, NOT a unique constraint —
     * order_number must never be treated as globally unique, only as part
     * of this composite identity. Purely additive; does not touch existing
     * data or the existing (group_id, plan_date) index.
     */
    public function up(): void
    {
        Schema::table('greasings', function (Blueprint $table) {
            $table->index(['group_id', 'plan_date', 'order_number'], 'greasings_schedule_identity_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('greasings', function (Blueprint $table) {
            $table->dropIndex('greasings_schedule_identity_index');
        });
    }
};
