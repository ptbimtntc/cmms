<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Both columns are nullable so findings recorded before this change
     * (free-text only) stay valid; `finding` keeps holding the remarks text.
     */
    public function up(): void
    {
        Schema::table('greasing_findings', function (Blueprint $table) {
            $table->foreignId('machine_id')
                ->nullable()
                ->after('greasing_id')
                ->constrained()
                ->nullOnDelete();

            $table->string('finding_area')->nullable()->after('machine_id');
        });
    }

    public function down(): void
    {
        Schema::table('greasing_findings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machine_id');
            $table->dropColumn('finding_area');
        });
    }
};
