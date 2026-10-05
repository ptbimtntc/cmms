<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Optional area restriction for the view-only SUPERVISOR role: a supervisor
// with no rows here sees every area; with one or more rows, only those.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('area_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('area_user');
    }
};
