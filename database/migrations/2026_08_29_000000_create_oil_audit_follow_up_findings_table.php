<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oil_audit_follow_up_findings', function (Blueprint $table) {
            $table->id();
            // Explicit short names: the auto-generated
            // "oil_audit_follow_up_findings_oil_audit_follow_up_problem_id_foreign"
            // exceeds MySQL's 64-char identifier limit.
            $table->foreignId('oil_audit_follow_up_problem_id')
                ->constrained(
                    table: 'oil_audit_follow_up_problems',
                    indexName: 'oafuf_problem_id_index'
                )
                ->cascadeOnDelete();
            $table->string('finding');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oil_audit_follow_up_findings');
    }
};
