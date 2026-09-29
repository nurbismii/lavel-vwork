<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('cycle_work_days')->default(5)->after('is_active');
            $table->unsignedSmallInteger('cycle_off_days')->default(2)->after('cycle_work_days');
            $table->decimal('daily_work_hours', 4, 2)->default(8)->after('cycle_off_days');
            $table->date('work_cycle_anchor_date')->default('1970-01-05')->after('daily_work_hours');
        });

        Schema::table('workload_capacities', function (Blueprint $table) {
            $table->unsignedSmallInteger('cycle_work_days')->nullable()->after('work_days');
            $table->unsignedSmallInteger('cycle_off_days')->nullable()->after('cycle_work_days');
            $table->date('work_cycle_anchor_date')->nullable()->after('hours_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('workload_capacities', function (Blueprint $table) {
            $table->dropColumn(['cycle_work_days', 'cycle_off_days', 'work_cycle_anchor_date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'cycle_work_days', 'cycle_off_days', 'daily_work_hours', 'work_cycle_anchor_date',
            ]);
        });
    }
};
