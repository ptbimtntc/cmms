<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pic_discipline_entries', function (Blueprint $table) {
            $table->id();

            $table->foreignId('pm_schedule_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('item_type_id')
                ->constrained('pic_discipline_item_types')
                ->cascadeOnDelete();

            $table->foreignId('pic_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->date('due_date');
            $table->date('completed_date')->nullable();
            $table->string('status')->default('PENDING');
            $table->string('source')->default('MANUAL');

            $table->foreignId('recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['pm_schedule_id', 'item_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pic_discipline_entries');
    }
};
