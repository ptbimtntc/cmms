<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('greasing_findings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('greasing_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->text('finding');

            $table->date('action_date')->nullable();

            $table->text('action')->nullable();

            $table->enum('status', [
                'OPEN',
                'COMPLETED',
            ])->default('OPEN');

            $table->timestamps();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greasing_findings');
    }
};
