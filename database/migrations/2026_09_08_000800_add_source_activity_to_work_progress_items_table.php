<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_progress_items', function (Blueprint $table) {
            $table->foreignId('source_activity_id')
                ->nullable()
                ->after('workload_submission_id')
                ->constrained('workload_activities')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_progress_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_activity_id');
        });
    }
};
