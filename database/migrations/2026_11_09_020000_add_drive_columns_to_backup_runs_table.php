<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backup terjadwal dan salinan Google Drive: siapa yang memicu run, dan
     * status file di Drive (ID, waktu unggah, error, waktu dihapus dari Drive).
     */
    public function up(): void
    {
        Schema::table('backup_runs', function (Blueprint $table) {
            $table->string('trigger', 20)->default('manual')->after('status');
            $table->string('drive_file_id')->nullable()->after('file_removed_at');
            $table->timestamp('drive_uploaded_at')->nullable()->after('drive_file_id');
            $table->string('drive_error')->nullable()->after('drive_uploaded_at');
            $table->timestamp('drive_removed_at')->nullable()->after('drive_error');
        });
    }

    public function down(): void
    {
        Schema::table('backup_runs', function (Blueprint $table) {
            $table->dropColumn(['trigger', 'drive_file_id', 'drive_uploaded_at', 'drive_error', 'drive_removed_at']);
        });
    }
};
