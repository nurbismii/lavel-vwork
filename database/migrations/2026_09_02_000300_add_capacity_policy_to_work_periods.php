<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_periods', function (Blueprint $table) {
            $table->unsignedTinyInteger('standard_work_days')->default(20);
            $table->decimal('standard_hours_per_day', 4, 2)->default(8);
            $table->decimal('standard_productive_percentage', 5, 2)->default(85);
            $table->text('capacity_policy_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('work_periods', function (Blueprint $table) {
            $table->dropColumn([
                'standard_work_days', 'standard_hours_per_day',
                'standard_productive_percentage', 'capacity_policy_note',
            ]);
        });
    }
};
