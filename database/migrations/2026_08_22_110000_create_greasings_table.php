<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * `cycle` is a plain string (e.g. "4W", "16W", "52W"), not a master
     * table, per the Greasing module business rules.
     *
     * `due_date` is always computed server-side as plan_date + 14 days
     * and must never be trusted from the request/import.
     */
    public function up(): void
    {
        Schema::create('greasings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('group_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('cycle');

            $table->date('plan_date');
            $table->date('due_date');

            $table->string('pic')->nullable();

            $table->date('action_date')->nullable();

            $table->enum('status', [
                'OPEN',
                'FINISH',
                'FINISH ON TIME',
            ])->default('OPEN');

            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->index(['group_id', 'plan_date']);
            $table->index('status');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('greasings');
    }
};
