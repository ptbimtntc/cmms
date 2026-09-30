<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_maintenance_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('machine_type')->unique();
            $table->boolean('requires_oil_change')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_maintenance_requirements');
    }
};
