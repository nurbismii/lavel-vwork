<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workload_activities', function (Blueprint $table) {
            $table->dropUnique('workload_activity_unique');
            $table->date('activity_date')->nullable()->after('workload_submission_id');
            $table->unique(
                ['workload_submission_id', 'activity_date', 'name', 'work_type'],
                'workload_activity_daily_unique'
            );
            $table->index(['workload_submission_id', 'activity_date'], 'workload_activity_date_index');
        });
    }

    public function down(): void
    {
        $hasRepeatedActivities = DB::table('workload_activities')
            ->select('workload_submission_id', 'name', 'work_type')
            ->groupBy('workload_submission_id', 'name', 'work_type')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasRepeatedActivities) {
            throw new RuntimeException(
                'Rollback dibatalkan: terdapat aktivitas aktual berulang yang memerlukan kolom tanggal.'
            );
        }

        Schema::table('workload_activities', function (Blueprint $table) {
            $table->dropUnique('workload_activity_daily_unique');
            $table->dropIndex('workload_activity_date_index');
            $table->dropColumn('activity_date');
            $table->unique(
                ['workload_submission_id', 'name', 'work_type'],
                'workload_activity_unique'
            );
        });
    }
};
