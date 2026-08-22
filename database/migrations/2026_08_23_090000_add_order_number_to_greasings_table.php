<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * order_number is nullable at the schema level (existing rows have
     * none), matching the pattern used by pm_schedules.order_number.
     * It is enforced as required at the application layer for manual
     * creation and CSV import, where it also doubles as part of the
     * duplicate-detection key (group_id + plan_date + order_number).
     */
    public function up(): void
    {
        Schema::table('greasings', function (Blueprint $table) {
            $table->string('order_number')->nullable()->after('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('greasings', function (Blueprint $table) {
            $table->dropColumn('order_number');
        });
    }
};
