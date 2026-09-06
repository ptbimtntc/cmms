<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Per-user, per-day start marker for the Oil Audit Action (follow-up
     * monitoring) menu. It is a distinct activity from the Oil Audit scan
     * menu — a PIC may have started one and not the other — so it needs its
     * own timestamp rather than reusing oil_audit_started_at.
     *
     * A single nullable timestamp is sufficient: the daily Start prompt is
     * shown only while this value is empty or older than the current
     * business date. No activity table.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('oil_audit_action_started_at')->nullable()->after('oil_audit_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('oil_audit_action_started_at');
        });
    }
};
