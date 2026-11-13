<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A PANTAU audit can be actioned with an oil refill only (nothing else to
 * record) and/or a full check (the usual problem/finding/action fields).
 * Existing rows are full checks, so full_check defaults to true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oil_audit_follow_ups', function (Blueprint $table) {
            $table->boolean('refill_oil')->default(false)->after('oil_audit_id');
            $table->boolean('full_check')->default(true)->after('refill_oil');
            $table->string('problem')->nullable()->change();
            $table->text('action_taken')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('oil_audit_follow_ups', function (Blueprint $table) {
            $table->dropColumn(['refill_oil', 'full_check']);
        });
    }
};
