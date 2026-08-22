<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * group_id is nullable because existing machines are not yet
     * assigned to a group. This keeps the migration non-destructive
     * for databases that already contain machine data.
     */
    public function up(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->foreignId('group_id')
                ->nullable()
                ->after('machine_number')
                ->constrained('groups')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('machines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });
    }
};
