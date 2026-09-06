<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Records the moment a user (PIC) starts their Oil Audit activity for a
     * given day. The daily Start prompt is shown only while this value is
     * empty or older than the current business date, so a single nullable
     * timestamp is all that is required — no per-day activity table.
     *
     * Oil Audit has no natural row to attach a start time to (an oil_audits
     * row is a completed audit event, created only after a machine is
     * scanned), so the marker lives on the user, mirroring how is_active /
     * avatar_path are user-scoped feature columns.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('oil_audit_started_at')->nullable()->after('avatar_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('oil_audit_started_at');
        });
    }
};
