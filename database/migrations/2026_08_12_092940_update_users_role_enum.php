<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE users
            MODIFY role ENUM(
                'ADMIN',
                'KOORDINATOR WWD',
                'KOORDINATOR BUL',
                'PIC WWD',
                'PIC BUL',
                'GUEST'
            ) NOT NULL DEFAULT 'GUEST'
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("
            ALTER TABLE users
            MODIFY role ENUM(
                'ADMIN',
                'PLANNER',
                'TECHNICIAN',
                'GUEST'
            ) NOT NULL DEFAULT 'GUEST'
        ");
    }
};